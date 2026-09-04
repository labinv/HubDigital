param(
    [string] $AccountId = '23a38e4645087d179a36fb6a345b07ae',
    [string] $BucketName = 'labinvepn-depositos-desarrollo',
    [string] $LocationHint = 'enam'
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($env:CLOUDFLARE_API_TOKEN)) {
    throw 'Falta CLOUDFLARE_API_TOKEN. Debe permanecer en el almacen de secretos, no en archivos.'
}

if ($BucketName -notmatch '^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$') {
    throw 'El nombre del bucket R2 no es valido.'
}

$baseUri = "https://api.cloudflare.com/client/v4/accounts/$AccountId/r2/buckets"
$headers = @{
    Authorization = "Bearer $env:CLOUDFLARE_API_TOKEN"
    'Content-Type' = 'application/json'
}

function Invoke-CloudflareApi {
    param(
        [Parameter(Mandatory)] [string] $Uri,
        [ValidateSet('GET', 'POST', 'PUT', 'DELETE')] [string] $Method = 'GET',
        [string] $Body
    )

    try {
        $parameters = @{
            Uri = $Uri
            Headers = $headers
            Method = $Method
        }
        if ($PSBoundParameters.ContainsKey('Body')) {
            $parameters.Body = $Body
        }

        return Invoke-RestMethod @parameters
    }
    catch {
        $status = if ($_.Exception.Response) { [int] $_.Exception.Response.StatusCode } else { 0 }
        if ($status -eq 403) {
            throw 'Cloudflare rechazo R2. Si el codigo es 10042, active primero Storage & databases > R2 > Overview.'
        }

        throw
    }
}

# Creacion idempotente. R2 nace privado y no recibe un dominio publico.
$listado = Invoke-CloudflareApi -Uri "${baseUri}?name=$BucketName"
$bucket = @($listado.result.buckets | Where-Object { $_.name -eq $BucketName }) | Select-Object -First 1

if ($null -eq $bucket) {
    $payload = @{
        name = $BucketName
        locationHint = $LocationHint
        storageClass = 'Standard'
    } | ConvertTo-Json
    $creado = Invoke-CloudflareApi -Uri $baseUri -Method POST -Body $payload
    $bucket = $creado.result
}

if ($bucket.storage_class -and $bucket.storage_class -ne 'Standard') {
    throw "El bucket existe con clase $($bucket.storage_class), pero desarrollo exige Standard para conservar el free tier."
}

# Defensa en profundidad: fuerza el dominio administrado r2.dev a deshabilitado.
$managedPayload = @{ enabled = $false } | ConvertTo-Json
$null = Invoke-CloudflareApi -Uri "$baseUri/$BucketName/domains/managed" -Method PUT -Body $managedPayload
$managed = Invoke-CloudflareApi -Uri "$baseUri/$BucketName/domains/managed"
if ($managed.result.enabled -ne $false) {
    throw 'El dominio publico r2.dev no quedo deshabilitado.'
}

# El navegador nunca accede directamente a R2: no se necesita CORS.
try {
    $null = Invoke-CloudflareApi -Uri "$baseUri/$BucketName/cors" -Method DELETE
}
catch {
    # La ausencia previa de una politica CORS tambien es el estado esperado.
    if ($_.Exception.Message -notmatch '404|not found|no existe') {
        throw
    }
}
$cors = Invoke-CloudflareApi -Uri "$baseUri/$BucketName/cors"
if (@($cors.result.rules).Count -ne 0) {
    throw 'El bucket conserva una politica CORS inesperada.'
}

$custom = Invoke-CloudflareApi -Uri "$baseUri/$BucketName/domains/custom"
if (@($custom.result.domains).Count -ne 0) {
    throw 'El bucket tiene dominios personalizados. Retirelos antes de almacenar expedientes.'
}

# No se borran expedientes por edad hasta que Archivo/DPO aprueben la retencion.
$lifecycle = Invoke-CloudflareApi -Uri "$baseUri/$BucketName/lifecycle"
$reglasBorrado = @($lifecycle.result.rules | Where-Object { $null -ne $_.deleteObjectsTransition })
if ($reglasBorrado.Count -ne 0) {
    throw 'El bucket tiene reglas de borrado automatico no autorizadas.'
}

[pscustomobject] @{
    account_id = $AccountId
    bucket = $BucketName
    storage_class = 'Standard'
    private = $true
    r2_dev_enabled = $false
    custom_domains = 0
    cors_rules = 0
    automatic_deletion_rules = 0
} | ConvertTo-Json
