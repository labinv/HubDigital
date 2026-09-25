# Autoridades raíz para firmas de Ecuador

Los archivos `.crt` contienen únicamente certificados X.509 públicos. Se extrajeron de los datos de certificados publicados por el proyecto oficial [FirmaEC de MINTEL](https://minka.gob.ec/mintel/ge/firmaec/firmadigital-libreria), commit `014b32719ad9d0ead566e9b007ceffbdefcbe0e0`, el 24 de septiembre de 2026. No se copió código de esa biblioteca.

`securitydata-ca2.crt` proviene directamente del [portal público EJBCA de Security Data](https://portal-operador2.securitydata.net.ec/ejbca/retrieve/ca_certs.jsp), certificado raíz de SubCA-2. Su huella SHA-256 es `50:3B:59:60:FA:8C:C5:8F:33:67:64:2A:91:1F:D8:F8:27:7E:47:4D:68:91:63:7F:E5:6C:A2:A6:9F:06:9C:BD`.

La [lista de entidades acreditadas de ARCOTEL](https://www.arcotel.gob.ec/listado-de-las-entidades-de-certificacion-de-informacion-y-servicios-relacionados-acreditados-y-terceros-vinculados-debidamente-acreditadas/) es la referencia para revisar nuevas altas y bajas. Antes de agregar una raíz, se debe contrastar su huella con la publicación de la entidad emisora. El validador no confía automáticamente en una raíz incluida dentro de un PDF o P12 presentado por un usuario.
