<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 42px 46px; }
        body { color: #17213b; font: 10px DejaVu Sans, sans-serif; line-height: 1.45; }
        h1 { color: #12385a; font-size: 17px; margin: 5px 0 2px; text-align: center; }
        h2 { border-bottom: 1px solid #93aa99; color: #24543f; font-size: 11px; margin: 16px 0 7px; padding-bottom: 3px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #cbd5d1; padding: 5px; vertical-align: top; }
        th { background: #edf4ef; color: #12385a; text-align: left; }
        .brand { border-bottom: 3px solid #2f6b4f; padding-bottom: 9px; text-align: center; }
        .muted { color: #56636b; }
        .declaration { background: #f3f7f4; border: 1px solid #b7c9bc; margin-top: 15px; padding: 9px; }
        .hash { color: #56636b; font: 7px DejaVu Sans Mono, monospace; word-break: break-all; }
        .footer { bottom: -29px; color: #64716b; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <div class="brand">
        <strong>ESCUELA POLITÉCNICA NACIONAL</strong><br>
        Departamento de Biología · Museo de Historia Natural Gustavo Orcés V.<br>
        <span class="muted">Laboratorio de Invertebrados · Colección Entomológica</span>
    </div>
    <h1>Solicitud de {{ mb_strtolower($solicitud->tipo_tramite) }} de invertebrados</h1>
    <p style="text-align:center" class="muted">Expediente {{ $solicitud->numero }} · versión {{ $solicitud->solicitud_documento_version ?? 1 }}</p>

    <h2>1. Datos de depósito de material MEPN · columnas A–J (consultor)</h2>
    <table>
        @foreach(array_slice($datosMepn, 0, 10, true) as $campo => $valor)
            <tr><th style="width:38%">{{ $campo }}</th><td>{{ $valor !== null && $valor !== '' ? $valor : 'No indicado' }}</td></tr>
        @endforeach
    </table>

    <h2>2. Seguimiento interno MEPN · columnas K–O (recepción y curaduría)</h2>
    <table>
        @foreach(array_slice($datosMepn, 10, 5, true) as $campo => $valor)
            <tr><th style="width:38%">{{ $campo }}</th><td>{{ $valor !== null && $valor !== '' ? $valor : 'Se completará internamente' }}</td></tr>
        @endforeach
    </table>

    <h2>3. Anexo de registros biológicos normalizados (Darwin Core)</h2>
    <table>
        <thead><tr><th>#</th><th>Nombre científico</th><th>Catálogo / ocurrencia</th><th>Localidad</th><th>Fecha</th></tr></thead>
        <tbody>
        @foreach($registros as $registro)
            @php($dwc = $registro->datos_dwc ?? [])
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><em>{{ $registro->nombre_corregido ?: $registro->nombre_cientifico }}</em></td>
                <td>{{ $dwc['catalogNumber'] ?? $dwc['occurrenceID'] ?? 'Por asignar' }}</td>
                <td>{{ $dwc['locality'] ?? $solicitud->localidad }}</td>
                <td>{{ $dwc['eventDate'] ?? 'No indicada' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="declaration">
        <strong>Declaración del solicitante.</strong> Declaro que la información registrada es verídica, que el material tiene procedencia lícita y que los permisos y documentos incorporados al expediente son auténticos. Solicito al Laboratorio de Invertebrados de la EPN evaluar el material bajo sus procedimientos de ingreso, revisión, custodia y devolución o incorporación, según corresponda.
    </div>
    <p class="hash">Huella del expediente: {{ $huellaExpediente }}</p>
    <p><strong>Firma electrónica del depositante:</strong> incorporada como firma PAdES en este documento por el Firmador HubDigital.</p>

    <div class="footer">Documento generado por HubDigital. La copia oficial firmada se conserva en almacenamiento privado con huella SHA-256 y trazabilidad de validación.</div>
</body>
</html>
