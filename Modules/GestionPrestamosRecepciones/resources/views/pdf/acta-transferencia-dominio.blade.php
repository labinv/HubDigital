@php
    $logoEpn = public_path('images/logo-epn.png');
    $logoDb = public_path('images/logo-DB.jpg');
    $logoEpnB64 = is_file($logoEpn) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoEpn)) : null;
    $logoDbB64 = is_file($logoDb) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoDb)) : null;
    $nombreDepositante = $depositante?->name ?: ($solicitud->nombre_investigador_documento ?: 'Depositante responsable');
    $nombreCurador = $curador?->name ?: 'Curaduría del Laboratorio de Invertebrados';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de transferencia de dominio {{ $solicitud->numero }}</title>
    <style>
        @page { margin: 1.6cm 1.7cm 2cm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #1b2a3a; font-size: 9.5pt; line-height: 1.45; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .membrete { margin-bottom: 16px; }
        .membrete td { vertical-align: middle; }
        .membrete .derecha { text-align: right; }
        .logo-epn { height: 58px; }
        .logo-db { height: 50px; }
        h1 { color: #102b46; font-size: 16pt; margin: 18px 0 4px; text-align: center; }
        .subtitulo { color: #1d6a4d; font-size: 9pt; font-weight: bold; letter-spacing: .08em; margin-bottom: 18px; text-align: center; text-transform: uppercase; }
        .meta { font-size: 8.5pt; margin-bottom: 17px; }
        .meta td { padding: 4px 0; }
        .meta .derecha { text-align: right; }
        p { margin: 0 0 10px; text-align: justify; }
        .datos { margin: 13px 0 16px; }
        .datos th, .datos td { border: 1px solid #b9c7d2; padding: 6px 8px; vertical-align: top; }
        .datos th { background: #edf4f0; color: #102b46; text-align: left; width: 34%; }
        .registros { font-size: 8pt; margin: 13px 0 18px; }
        .registros th, .registros td { border: 1px solid #b9c7d2; padding: 5px; vertical-align: top; }
        .registros th { background: #eef4f8; color: #102b46; text-align: left; }
        .responsables { margin-top: 24px; page-break-inside: avoid; }
        .responsables th, .responsables td { border: 1px solid #b9c7d2; padding: 7px 9px; text-align: left; }
        .responsables th { background: #edf4f0; color: #102b46; width: 30%; }
        .pie { bottom: -1.35cm; color: #526576; font-size: 7pt; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <div class="pie">Laboratorio de Invertebrados · Departamento de Biología · Escuela Politécnica Nacional · Quito, Ecuador</div>

    <table class="membrete">
        <tr>
            <td>@if($logoEpnB64)<img class="logo-epn" src="{{ $logoEpnB64 }}" alt="Escuela Politécnica Nacional">@endif</td>
            <td class="derecha">@if($logoDbB64)<img class="logo-db" src="{{ $logoDbB64 }}" alt="Departamento de Biología">@endif</td>
        </tr>
    </table>

    <h1>ACTA DE TRANSFERENCIA DE DOMINIO</h1>
    <div class="subtitulo">Donación de material biológico al MEPN</div>

    <table class="meta">
        <tr>
            <td>Quito, {{ $fecha->format('d/m/Y') }}</td>
            <td class="derecha">Expediente No. <strong>{{ $solicitud->numero }}</strong></td>
        </tr>
    </table>

    <p>
        Por medio de la presente acta, <strong>{{ $nombreDepositante }}</strong> entrega en calidad de donación el material biológico detallado en este expediente al Museo de Historia Natural “Gustavo Orcés V.” (MEPN), Departamento de Biología de la Escuela Politécnica Nacional. La recepción documental fue aprobada por Curaduría y el material quedará sujeto a la constatación física y al acta final de recepción.
    </p>

    <table class="datos">
        <tr><th>Grupo biológico declarado</th><td>{{ $solicitud->grupo_animal ?: 'No especificado' }}</td></tr>
        <tr><th>Procedencia / localidad</th><td>{{ $solicitud->localidad ?: ($solicitud->origen_recoleccion ?: 'No especificada') }}</td></tr>
        <tr><th>Permiso de recolección</th><td>{{ $solicitud->nro_permiso_recoleccion ?: 'No declarado' }}</td></tr>
        <tr><th>Guía de movilización</th><td>{{ $solicitud->nro_permiso_movilizacion ?: 'No declarada' }}</td></tr>
        <tr><th>Material declarado</th><td>{{ $solicitud->nro_individuos ?? '—' }} individuos · {{ $solicitud->nro_morfoespecies ?? '—' }} morfoespecies · {{ $solicitud->nro_lotes ?? '—' }} lotes</td></tr>
    </table>

    <p>
        La transferencia se refiere al material descrito y a los datos asociados del expediente digital. La incorporación definitiva a la colección se realizará únicamente tras la recepción física, la constatación por personal EPN y la generación del acta final firmada electrónicamente por Curaduría.
    </p>

    @if($registros->isNotEmpty())
        <table class="registros">
            <thead>
                <tr><th>Registro</th><th>Identificación científica declarada</th><th>Localidad / observación</th></tr>
            </thead>
            <tbody>
                @foreach($registros as $registro)
                    @php $dwc = $registro->datos_dwc ?? []; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $registro->nombre_corregido ?: $registro->nombre_cientifico }}</td>
                        <td>{{ $dwc['verbatimLocality'] ?? $dwc['locality'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="responsables">
        <tr><th>Depositante responsable</th><td>{{ $nombreDepositante }}</td></tr>
        <tr><th>Curaduría responsable</th><td>{{ $nombreCurador }}</td></tr>
        <tr><th>Condición del documento</th><td>Generado por el sistema al aprobar la donación; la firma electrónica corresponde al acta final de recepción.</td></tr>
    </table>
</body>
</html>
