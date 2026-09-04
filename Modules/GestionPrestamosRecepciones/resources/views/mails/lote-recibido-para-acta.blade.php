<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Acta final pendiente</title></head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#172033">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px"><tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#fff;border-radius:12px;overflow:hidden">
<tr><td style="background:#17375e;padding:28px 38px;color:#fff">
<div style="font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#b7cee7">Laboratorio de Invertebrados · EPN</div>
<h1 style="margin:7px 0 0;font-size:22px">Acta final pendiente de firma</h1>
</td></tr>
<tr><td style="padding:28px 38px">
<p style="margin:0 0 16px;line-height:1.6">{{ $nombreCurador ? $nombreCurador.',' : 'Curaduría:' }} recepción EPN constató el lote <strong>{{ $numero ?: 'de la solicitud' }}</strong>{{ $nombreReceptor ? ' (responsable: '.$nombreReceptor.')' : '.' }}</p>
@if($conObservaciones)<p style="padding:12px 14px;background:#fff5df;border-left:4px solid #c88200">El lote fue aceptado con observaciones; revísalas antes de emitir el acta.</p>@endif
<p style="margin:0 0 22px;line-height:1.6">Ingresa al expediente, genera el PDF oficial y fírmalo con el firmador local de HubDigital. El alta en colección permanecerá bloqueada hasta validar la firma.</p>
<p style="text-align:center;margin:0"><a href="{{ $url }}" style="display:inline-block;background:#19704a;color:#fff;text-decoration:none;font-weight:700;padding:13px 24px;border-radius:8px">Generar y firmar acta</a></p>
</td></tr>
</table></td></tr></table>
</body>
</html>
