<#
.SYNOPSIS
Valida, publica en Git y crea un paquete de despliegue OCI para HubDigital.

.PARAMETER SinPostgres
Modo seguro para cambios exclusivamente frontend. Compila Vite pero no inicia
PostgreSQL. Se rechaza automaticamente si hay archivos backend modificados.

.EXAMPLE
crear-paquete-oci -SinPostgres

.EXAMPLE
crear-paquete-oci -SinPostgres -DescripcionCambio "ajusta colores del portal"

.EXAMPLE
crear-paquete-oci -OmitirCompilacion -DescripcionCambio "corrige repositorio de depositos"
#>
[CmdletBinding()]
param(
    [string]$Proyecto,
    [string]$Destino,
    [string]$DescripcionCambio,
    [switch]$OmitirCompilacion,
    [switch]$OmitirPruebasPHP,
    [Alias('OmitirPruebasPostgreSQL', 'SoloFrontend')]
    [switch]$SinPostgres
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$directorioScript = Split-Path -Parent $MyInvocation.MyCommand.Path
$raizRepositorio = Split-Path -Parent (Split-Path -Parent $directorioScript)
if ([string]::IsNullOrWhiteSpace($Proyecto)) { $Proyecto = $raizRepositorio }

function Invoke-Comando {
    param(
        [Parameter(Mandatory)] [string]$Programa,
        [Parameter(Mandatory)] [string[]]$Argumentos,
        [Parameter(Mandatory)] [string]$Descripcion,
        [string]$DirectorioTrabajo
    )

    Write-Host "`n==> $Descripcion" -ForegroundColor Cyan
    if ($DirectorioTrabajo) { Push-Location $DirectorioTrabajo }
    try {
        & $Programa @Argumentos
        if ($LASTEXITCODE -ne 0) { throw "Fallo: $Descripcion (codigo $LASTEXITCODE)." }
    }
    finally {
        if ($DirectorioTrabajo) { Pop-Location }
    }
}

function Convertir-NombreSeguro {
    param([Parameter(Mandatory)] [string]$Texto)

    $normalizado = $Texto.Normalize([Text.NormalizationForm]::FormD)
    $sinAcentos = -join ($normalizado.ToCharArray() | Where-Object {
        [Globalization.CharUnicodeInfo]::GetUnicodeCategory($_) -ne [Globalization.UnicodeCategory]::NonSpacingMark
    })
    $seguro = ($sinAcentos.Normalize([Text.NormalizationForm]::FormC) -replace '[^A-Za-z0-9._-]+', '-').Trim('-', '.', '_')
    return $seguro.ToLowerInvariant()
}

function Invoke-TarConProgreso {
    param(
        [Parameter(Mandatory)] [string[]]$Argumentos,
        [Parameter(Mandatory)] [string]$ArchivoSalida
    )

    Write-Host "`n==> Creando bundle fuente filtrado" -ForegroundColor Cyan
    Write-Host 'La compresion puede tardar varios minutos. Se mostrara actividad cada 5 segundos.'
    $argumentosNativos = $Argumentos | ForEach-Object {
        if ($_ -match '[\s"]') { '"' + $_.Replace('"', '\"') + '"' } else { $_ }
    }
    $inicioProceso = [Diagnostics.ProcessStartInfo]::new()
    $inicioProceso.FileName = (Get-Command 'tar.exe').Source
    $inicioProceso.Arguments = $argumentosNativos -join ' '
    $inicioProceso.UseShellExecute = $false
    $proceso = [Diagnostics.Process]::new()
    $proceso.StartInfo = $inicioProceso
    if (-not $proceso.Start()) { throw 'No se pudo iniciar tar.exe.' }

    $inicio = Get-Date
    while (-not $proceso.WaitForExit(5000)) {
        $transcurrido = (Get-Date) - $inicio
        $tamanoActual = if (Test-Path -LiteralPath $ArchivoSalida) {
            [Math]::Round((Get-Item -LiteralPath $ArchivoSalida).Length / 1MB, 2)
        } else { 0 }
        Write-Host ('  Trabajando... tiempo {0:hh\:mm\:ss} | paquete {1:N2} MiB' -f $transcurrido, $tamanoActual)
        $proceso.Refresh()
    }

    $proceso.WaitForExit()
    $codigoSalida = $proceso.ExitCode
    $proceso.Dispose()
    if ($codigoSalida -ne 0) {
        if (Test-Path -LiteralPath $ArchivoSalida) { Remove-Item -LiteralPath $ArchivoSalida -Force }
        throw "Fallo la creacion del paquete (codigo $codigoSalida)."
    }
}

function Get-SalidaGit {
    param([Parameter(Mandatory)] [string[]]$Argumentos)

    $salida = @(& git.exe -C $Proyecto @Argumentos)
    if ($LASTEXITCODE -ne 0) { throw "Fallo git $($Argumentos -join ' ')." }
    return $salida
}

$Proyecto = [IO.Path]::GetFullPath($Proyecto)
if ([string]::IsNullOrWhiteSpace($Destino)) { $Destino = Join-Path $Proyecto 'artifacts\oci' }
$Destino = [IO.Path]::GetFullPath($Destino)
if (-not (Test-Path -LiteralPath (Join-Path $Proyecto 'artisan') -PathType Leaf)) {
    throw "No se encontro el proyecto Laravel en: $Proyecto"
}
foreach ($programa in @('git.exe', 'tar.exe')) {
    if (-not (Get-Command $programa -ErrorAction SilentlyContinue)) { throw "No se encontro $programa en PATH." }
}

while ([string]::IsNullOrWhiteSpace($DescripcionCambio)) {
    $DescripcionCambio = Read-Host 'Describe el cambio; se usara para el commit y el paquete'
}
$DescripcionCambio = $DescripcionCambio.Trim() -replace '(?i)^HT:\s*', ''
if ([string]::IsNullOrWhiteSpace($DescripcionCambio)) { throw 'La descripcion del cambio no puede quedar vacia.' }
$nombreSeguro = Convertir-NombreSeguro -Texto $DescripcionCambio
if ([string]::IsNullOrWhiteSpace($nombreSeguro)) { throw 'La descripcion debe contener al menos una letra o un numero.' }
$mensajeCommit = "HT: $DescripcionCambio"
Write-Host "`nCommit previsto:  $mensajeCommit" -ForegroundColor Yellow
Write-Host "Nombre base:      $nombreSeguro" -ForegroundColor Yellow

$rama = (Get-SalidaGit -Argumentos @('symbolic-ref', '--short', 'HEAD') | Select-Object -First 1).Trim()
$upstream = (Get-SalidaGit -Argumentos @('rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}') | Select-Object -First 1).Trim()
$separador = $upstream.IndexOf('/')
if ($separador -lt 1) { throw "El upstream de Git no es valido: $upstream" }
$remoto = $upstream.Substring(0, $separador)
$ramaRemota = $upstream.Substring($separador + 1)

Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'fetch', '--prune', $remoto, $ramaRemota) -Descripcion "Conectando con Git y actualizando $upstream"
$conteo = (Get-SalidaGit -Argumentos @('rev-list', '--left-right', '--count', "HEAD...$upstream") | Select-Object -First 1) -split '\s+'
if ($conteo.Count -lt 2) { throw 'No se pudo comparar la rama local con Git remoto.' }
$atras = [int]$conteo[1]
if ($atras -gt 0) {
    throw "Git remoto tiene $atras commit(s) que no estan localmente. Se detiene para no pisar codigo; revise y fusione esos cambios primero."
}

$archivosRastreadosCambiados = @(Get-SalidaGit -Argumentos @('diff', '--name-only', 'HEAD'))
$archivosNuevos = @(Get-SalidaGit -Argumentos @('ls-files', '--others', '--exclude-standard'))
$archivosCambiados = @(($archivosRastreadosCambiados + $archivosNuevos) | Sort-Object -Unique)
$rutasProhibidas = @($archivosCambiados | Where-Object {
    $_ -match '(^|/)\.env($|\.(?!example$))' -or $_ -match '(^|/)\.codex-' -or $_ -match '\.(key|pem|p12|pfx|pass)$'
})
if ($rutasProhibidas) {
    throw "Hay archivos sensibles no ignorados. No se agrego nada a Git: $($rutasProhibidas -join ', ')"
}

if ($SinPostgres -and -not $OmitirPruebasPHP) {
    $cambiosQueRequierenPostgres = @($archivosCambiados | Where-Object {
        $_ -match '(?i)\.php$' -or
        $_ -match '(?i)^(database|config|routes|bootstrap)/' -or
        $_ -match '(?i)^(composer\.json|composer\.lock|phpunit\.xml|artisan)$'
    })
    if ($cambiosQueRequierenPostgres) {
        throw "No se puede usar -SinPostgres porque hay cambios backend que requieren la suite PostgreSQL: $($cambiosQueRequierenPostgres -join ', ')"
    }
    Write-Host "`nModo frontend: PostgreSQL no se encendera; no se detectaron cambios backend." -ForegroundColor Yellow
}

Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'diff', 'HEAD', '--check') -Descripcion 'Validando espacios, conflictos y formato basico del diff'

$archivosJson = @($archivosCambiados | Where-Object { $_ -match '\.json$' })
if ($archivosJson) {
    Write-Host "`n==> Validando archivos JSON" -ForegroundColor Cyan
    foreach ($archivo in $archivosJson) {
        $rutaJson = Join-Path $Proyecto $archivo
        if (Test-Path -LiteralPath $rutaJson -PathType Leaf) {
            $null = Get-Content -Raw -LiteralPath $rutaJson | ConvertFrom-Json
            Write-Host "  OK $archivo"
        }
    }
}

$composer = Get-Command 'composer' -ErrorAction SilentlyContinue
if (-not $composer) {
    $composerAlternativo = Join-Path $env:LOCALAPPDATA 'Microsoft\WindowsApps\composer.cmd'
    if (Test-Path -LiteralPath $composerAlternativo -PathType Leaf) {
        $composer = Get-Item -LiteralPath $composerAlternativo
    }
}
if (-not $composer) { throw 'No se encontro Composer. Ejecute composer --version para revisar su instalacion.' }
$composerPrograma = if ($composer.PSObject.Properties['Source'] -and $composer.Source) { $composer.Source } else { $composer.FullName }
Invoke-Comando -Programa $composerPrograma -Argumentos @('validate', '--no-check-publish') -Descripcion 'Validando composer.json y composer.lock' -DirectorioTrabajo $Proyecto
Invoke-Comando -Programa $composerPrograma -Argumentos @('install', '--no-interaction', '--prefer-dist', '--no-progress') -Descripcion 'Sincronizando dependencias PHP desde composer.lock' -DirectorioTrabajo $Proyecto

$php = Get-Command 'php' -ErrorAction SilentlyContinue
if (-not $php) {
    $phpAlternativo = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
    if (Test-Path -LiteralPath $phpAlternativo -PathType Leaf) {
        $php = Get-Item -LiteralPath $phpAlternativo
    }
}
$phpPrograma = if ($php -and $php.PSObject.Properties['Source'] -and $php.Source) { $php.Source } elseif ($php) { $php.FullName } else { $null }
$archivosPhp = @($archivosCambiados | Where-Object { $_ -match '\.php$' -and $_ -notmatch '\.blade\.php$' })
if ($php) {
    if ($archivosPhp) {
        Write-Host "`n==> Validando sintaxis PHP" -ForegroundColor Cyan
        foreach ($archivo in $archivosPhp) {
            $rutaPhp = Join-Path $Proyecto $archivo
            if (Test-Path -LiteralPath $rutaPhp -PathType Leaf) {
                & $phpPrograma -l $rutaPhp | Out-Host
                if ($LASTEXITCODE -ne 0) { throw "Sintaxis PHP invalida: $archivo" }
            }
        }
    }
    if (-not $OmitirPruebasPHP) {
        Invoke-Comando -Programa $phpPrograma -Argumentos @('artisan', 'about', '--only=environment') -Descripcion 'Validando el arranque de Laravel' -DirectorioTrabajo $Proyecto

        if ($SinPostgres) {
            Write-Host "`n==> Suite PostgreSQL omitida mediante -SinPostgres" -ForegroundColor Yellow
        } else {
            $validadorPostgres = Join-Path $raizRepositorio 'scripts\deployment\windows\validar-postgresql-temporal.ps1'
            if (-not (Test-Path -LiteralPath $validadorPostgres -PathType Leaf)) { throw "No se encontro el validador PostgreSQL: $validadorPostgres" }
            Invoke-Comando -Programa 'powershell.exe' -Argumentos @(
                '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $validadorPostgres,
                '-Proyecto', $Proyecto, '-Php', $phpPrograma
            ) -Descripcion 'Validando con PostgreSQL temporal y apagado automatico'
        }

    }
} else {
    Write-Host "`nADVERTENCIA: PHP no esta instalado en PATH; la sintaxis y pruebas PHP se validaran nuevamente al preparar la release en OCI." -ForegroundColor Yellow
}

if (-not $OmitirCompilacion) {
    if (-not (Get-Command 'npm.cmd' -ErrorAction SilentlyContinue)) { throw 'No se encontro npm.cmd en PATH.' }
    Invoke-Comando -Programa 'npm.cmd' -Argumentos @('run', 'build') -Descripcion 'Compilando y validando JavaScript, CSS y Tailwind con Vite' -DirectorioTrabajo $Proyecto
}

$requeridos = @(
    'vendor/autoload.php', 'public/build/manifest.json', 'deploy/oracle/scripts/stage-linux-candidate.sh',
    'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/cache/.gitignore',
    'composer.json', 'composer.lock', 'modules_statuses.json'
)
foreach ($ruta in $requeridos) {
    if (-not (Test-Path -LiteralPath (Join-Path $Proyecto $ruta) -PathType Leaf)) { throw "Falta un archivo requerido para el paquete: $ruta" }
}

$estadoAntesCommit = @(Get-SalidaGit -Argumentos @('status', '--porcelain=v1', '--untracked-files=all'))
if ($estadoAntesCommit) {
    Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'add', '-A') -Descripcion 'Agregando cambios a Git'
    Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'diff', '--cached', '--check') -Descripcion 'Validando el contenido preparado para commit'
    Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'commit', '-m', $mensajeCommit) -Descripcion "Creando commit: $mensajeCommit"
} else {
    Write-Host "`n==> Git no tiene cambios nuevos; no se crea un commit vacio." -ForegroundColor Yellow
}

$estadoDespuesCommit = @(Get-SalidaGit -Argumentos @('status', '--porcelain=v1', '--untracked-files=all'))
if ($estadoDespuesCommit) { throw 'Quedaron cambios fuera del commit. Se detiene antes de publicar para no crear un paquete distinto de Git.' }

Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'push', $remoto, "HEAD:$ramaRemota") -Descripcion "Publicando por push normal en $upstream"
Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'fetch', $remoto, $ramaRemota) -Descripcion 'Verificando el commit publicado'
$commit = (Get-SalidaGit -Argumentos @('rev-parse', 'HEAD') | Select-Object -First 1).Trim()
$commitRemoto = (Get-SalidaGit -Argumentos @('rev-parse', $upstream) | Select-Object -First 1).Trim()
if ($commit -ne $commitRemoto) { throw 'El commit local y el remoto no coinciden. No se crea el paquete.' }

New-Item -ItemType Directory -Path $Destino -Force | Out-Null
$marcaTiempo = (Get-Date).ToString('yyyyMMdd-HHmmss')
$nombre = "$nombreSeguro-$marcaTiempo.tar.gz"
$nombreScriptTransferencia = "$nombreSeguro-$marcaTiempo-cloudshell-vm.sh"
$paquete = Join-Path $Destino $nombre
$suma = "$paquete.sha256"
$scriptTransferencia = Join-Path $Destino $nombreScriptTransferencia
foreach ($salida in @($paquete, $suma, $scriptTransferencia)) {
    if (Test-Path -LiteralPath $salida) { throw "Ya existe un archivo de salida: $salida" }
}

$incluir = @(
    'app', 'config', 'database', 'lang', 'Modules', 'public', 'resources', 'routes', 'vendor', 'deploy',
    'scripts/depositos',
    'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/cache/.gitignore',
    'artisan', 'composer.json', 'composer.lock', 'modules_statuses.json'
)
$argumentosTar = @(
    '-czf', $paquete,
    '--exclude=.git', '--exclude=.env', '--exclude=.env.*', '--exclude=.codex-*',
    '--exclude=.ai', '--exclude=.claude', '--exclude=.agents', '--exclude=.tools', '--exclude=.local',
    '--exclude=artifacts', '--exclude=docs', '--exclude=tests', '--exclude=postman', '--exclude=docker', '--exclude=dist',
    '--exclude=node_modules', '--exclude=public/hot', '--exclude=public/storage',
    '--exclude=Modules/*/tests', '--exclude=Modules/*/tests/*',
    '--exclude=*.key', '--exclude=*.pem', '--exclude=*.p12', '--exclude=*.pfx', '--exclude=*.pass',
    '-C', $Proyecto
) + $incluir

$dependenciasDesarrolloRetiradas = $false
try {
    Invoke-Comando -Programa $composerPrograma -Argumentos @(
        'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--prefer-dist', '--no-progress'
    ) -Descripcion 'Preparando dependencias PHP exclusivas de produccion' -DirectorioTrabajo $Proyecto
    $dependenciasDesarrolloRetiradas = $true

    Invoke-TarConProgreso -Argumentos $argumentosTar -ArchivoSalida $paquete

    Write-Host "`n==> Validando contenido y exclusiones" -ForegroundColor Cyan
    $contenido = & tar.exe -tzf $paquete
    if ($LASTEXITCODE -ne 0 -or -not $contenido) { throw 'El paquete se creo, pero no pudo volver a leerse.' }
    $prohibidos = @($contenido | Where-Object {
        $_ -match '(^|/)\.git(/|$)' -or $_ -match '(^|/)\.env($|\.(?!example$))' -or
        $_ -match '(^|/)\.codex-' -or $_ -match '(^|/)\.(ai|claude|agents|tools|local)(/|$)' -or
        $_ -match '(^|/)(artifacts|docs|tests|postman|docker)(/|$)' -or
        $_ -match '\.(key|pem|p12|pfx|pass)$' -or
        $_ -match '(^|/)node_modules(/|$)' -or $_ -match '(^|/)dist(/|$)' -or
        $_ -match '(^|/)vendor/(phpunit|pestphp)(/|$)'
    })
    if ($prohibidos) {
        Remove-Item -LiteralPath $paquete -Force
        throw "El paquete contenia rutas prohibidas: $($prohibidos -join ', ')"
    }

    $contenidoScript = @'
#!/usr/bin/env bash
set -Eeuo pipefail

package_name='__PACKAGE__'
checksum_name='__CHECKSUM__'
vm_host="${VM_HOST:-129.153.23.57}"
vm_user="${VM_USER:-ubuntu}"
key_path="${SSH_KEY_PATH:-${HOME}/ssh-key-2026-09-18.key}"
self_path="$(readlink -f -- "$0")"
base_dir="$(dirname -- "${self_path}")"
stage_script='/srv/hubdigital/current/deploy/oracle/scripts/stage-linux-candidate.sh'

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

verify_transfer() {
    [[ -f "${base_dir}/${package_name}" ]] || fail "Falta ${base_dir}/${package_name}"
    [[ -f "${base_dir}/${checksum_name}" ]] || fail "Falta ${base_dir}/${checksum_name}"
    (cd "${base_dir}" && sha256sum -c "${checksum_name}")
}

if [[ -f "${stage_script}" ]]; then
    printf 'Entorno detectado: VM OCI.\n'
    verify_transfer
    stage_log="/tmp/${package_name%.tar.gz}-stage.log"
    sudo "${stage_script}" "${base_dir}/${package_name}" | tee "${stage_log}"

    candidate="$(awk -F= '$1 == "candidate" {print $2}' "${stage_log}" | tail -n 1)"
    checksum="$(awk -F= '$1 == "checksum" {print $2}' "${stage_log}" | tail -n 1)"
    staging="$(awk -F= '$1 == "staging" {print $2}' "${stage_log}" | tail -n 1)"
    [[ "${candidate}" =~ ^[A-Za-z0-9._-]+\.tar\.gz$ ]] || fail 'El staging no devolvio un candidato valido.'
    [[ "${checksum}" =~ ^[0-9a-f]{64}$ ]] || fail 'El staging no devolvio un checksum valido.'
    [[ "${staging}" =~ ^/srv/hubdigital/staging/[0-9a-f]{16}$ ]] || fail 'El staging no devolvio una ruta valida.'
    release_id="${checksum:0:16}"

    rm -f -- "${base_dir}/${package_name}" "${base_dir}/${checksum_name}" "${self_path}"
    printf '\nCandidato preparado correctamente.\n'
    printf 'Archivos temporales enviados a /tmp eliminados.\n'
    printf 'Registro conservado: %s\n' "${stage_log}"
    printf 'Candidato: %s\n' "${candidate}"
    printf 'Staging: %s\n' "${staging}"
    printf 'Release ID previsto: %s\n\n' "${release_id}"
    printf 'Siguiente comando, sin migraciones:\n'
    printf 'sudo env APPLY_MIGRATIONS=0 %q %q %q\n' \
        "${staging}/deploy/oracle/scripts/deploy-release.sh" \
        "/srv/hubdigital/staging/${candidate}" \
        "/srv/hubdigital/staging/${candidate}.sha256"
    exit 0
fi

printf 'Entorno detectado: OCI Cloud Shell.\n'
verify_transfer
[[ -f "${key_path}" ]] || fail "No se encontro la clave SSH: ${key_path}"
chmod 600 "${key_path}"
scp -i "${key_path}" \
    "${base_dir}/${package_name}" \
    "${base_dir}/${checksum_name}" \
    "${self_path}" \
    "${vm_user}@${vm_host}:/tmp/"

printf '\nTransferencia a la VM completada.\n'
printf 'Conectate con:\nssh -i %q %q\n' "${key_path}" "${vm_user}@${vm_host}"
printf '\nDentro de la VM verifica y ejecuta:\n'
printf 'cd /tmp\nsha256sum -c %q\nbash %q\n' "${checksum_name}" "$(basename -- "${self_path}")"
'@
    $contenidoScript = $contenidoScript.Replace('__PACKAGE__', $nombre).Replace('__CHECKSUM__', [IO.Path]::GetFileName($suma))
    [IO.File]::WriteAllText($scriptTransferencia, ($contenidoScript.TrimStart() + "`n"), [Text.UTF8Encoding]::new($false))

    $hash = (Get-FileHash -LiteralPath $paquete -Algorithm SHA256).Hash.ToLowerInvariant()
    $hashScript = (Get-FileHash -LiteralPath $scriptTransferencia -Algorithm SHA256).Hash.ToLowerInvariant()
    $contenidoChecksum = "$hash  $nombre`n$hashScript  $nombreScriptTransferencia`n"
    [IO.File]::WriteAllText($suma, $contenidoChecksum, [Text.UTF8Encoding]::new($false))
    $tamanoMiB = [Math]::Round((Get-Item -LiteralPath $paquete).Length / 1MB, 2)
}
finally {
    if ($dependenciasDesarrolloRetiradas) {
        Invoke-Comando -Programa $composerPrograma -Argumentos @(
            'install', '--no-interaction', '--prefer-dist', '--no-progress'
        ) -Descripcion 'Restaurando dependencias PHP de desarrollo locales' -DirectorioTrabajo $Proyecto
    }
}

Write-Host "`nProceso completo." -ForegroundColor Green
Write-Host "Commit:    $commit"
Write-Host "Mensaje:   $mensajeCommit"
Write-Host "Git:       $upstream sincronizado"
Write-Host "Paquete:   $paquete"
Write-Host "Checksum:  $suma"
Write-Host "Script:    $scriptTransferencia"
Write-Host "SHA-256:   $hash"
Write-Host "Tamano:    $tamanoMiB MiB"
Write-Host "`nSiguiente paso: carga los tres archivos en OCI Cloud Shell y ejecuta bash $nombreScriptTransferencia"
