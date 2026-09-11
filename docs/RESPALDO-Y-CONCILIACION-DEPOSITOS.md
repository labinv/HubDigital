# Respaldo y conciliación documental de depósitos

## Alcance y seguridad

Estas herramientas cubren PostgreSQL y los objetos privados referenciados por expedientes de depósito. No reparan, eliminan ni sustituyen objetos automáticamente. La detección de huérfanos exige un prefijo explícito bajo `depositos/`; nunca enumera el bucket completo por defecto.

Los resultados no contienen bytes documentales, credenciales ni metadatos de firma privados. Los manifiestos y dumps deben guardarse fuera de Git, con permisos restringidos y en un destino privado. Un prefijo distinto dentro del mismo bucket protege frente a errores de clave, pero **no es una copia independiente ante pérdida del bucket o de la cuenta**.

## Conciliación solo lectura

```sh
php artisan depositos:conciliar-documentos \
  --expediente=MEPN-INV-DEP-99999 \
  --prefijo=depositos/qa-operacion-20260910 \
  --salida=/evidencia/conciliacion.json
```

Se recorren solicitudes y recepciones por lotes. Para R2, el inventario usa `ListObjectsV2` paginado hasta que `IsTruncated` sea falso. Cada referencia se comprueba con existencia, `HEAD`, lectura secuencial y SHA-256. Estados: `OK`, `AUSENTE`, `ALTERADO`, `VERSION_INCONSISTENTE` y `NO_SE_PUDO_CONSULTAR`.

Códigos: `0` íntegro, `2` inconsistencias u objetos huérfanos, `3` fallo operativo/configuración. Un error del proveedor nunca se degrada a `AUSENTE`.

## Respaldo documental reanudable

```sh
php artisan depositos:respaldar-documentos \
  --id=20260910T180000Z-qa \
  --prefijo-destino=respaldos-depositos/20260910T180000Z-qa \
  --salida-manifiesto=/privado/manifiesto-documentos.json
```

El comando mantiene un bloqueo exclusivo, copia un objeto por vez, verifica SHA-256 en destino y actualiza atómicamente un manifiesto local después de cada objeto. `--reanudar` reutiliza solamente copias cuyo SHA vuelva a verificarse. El estado final es `COMPLETO` o `INCOMPLETO`; códigos `0`, `3` (operativo) y `4` (interrumpido, incompleto o bloqueado).

## Respaldo coordinado y restauración

`scripts/depositos/respaldo-coordinado.sh` establece una barrera de escritura, detiene worker y scheduler, copia documentos, genera `pg_dump -Fc`, registra colas y hashes, y luego restablece los servicios. El bloqueo permite reconocer una ejecución viva y apartar uno abandonado; el manejador de salida intenta levantar los servicios y sacar la aplicación de mantenimiento incluso si una etapa falla. Debe ejecutarse en una ventana controlada: una terminación no capturable exige comprobar servicios y reanudar con el mismo identificador.

`scripts/depositos/restaurar-aislado.sh` rechaza manifiestos incompletos, valida ambos hashes y exige una base/proyecto Compose y un directorio de objetos aislados y vacíos. Restaura PostgreSQL sin propietarios ni privilegios del origen y entrega los objetos a `www-data` con directorios `0750` y archivos `0640`. No admite sobrescribir el entorno compartido. Tras restaurar, ejecutar conciliación, validación de firmas, worker controlado y Playwright antes de aceptar el respaldo.

## Supervisión y alertas

Automatizar externamente la ejecución y alertar por código distinto de cero, manifiesto `INCOMPLETO`, antigüedad mayor a la frecuencia que defina la institución, objetos `NO_SE_PUDO_CONSULTAR`, jobs fallidos o una duración anómala. La frecuencia, retención, destino independiente, responsables, RPO y RTO son decisiones institucionales pendientes; este repositorio no activa eliminación por retención ni compra recursos.
