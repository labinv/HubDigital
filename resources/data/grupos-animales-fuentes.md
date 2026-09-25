# Grupos animales del depósito

El selector del paso 4 usa `recepciones.catalogo_grupos_invertebrados`. Sus nombres
científicos son taxones superiores de Animalia (filo, subfilo o clase); el nombre
en español es una etiqueta de presentación que el curador puede modificar.

Referencias consultadas el 24-09-2026:

- [GBIF Backbone Taxonomy](https://www.gbif.org/dataset/d7dddbf4-2cf0-4f39-9b2a-bb099caae36c)
- [GBIF Species API](https://techdocs.gbif.org/en/openapi/v1/species), usada para
  contrastar nombres y rangos taxonómicos.
- [Guía de datos de GBIF](https://ipt.gbif.org/manual/en/ipt/latest/data-quality-checklist):
  los rangos científicos se registran por separado del nombre común.

`OTRO_INVERTEBRADO` queda desactivado porque no es un taxón. Para agregar un
grupo nuevo, el curador debe comprobar su nombre científico y rango en GBIF.

La lista de localidades incluye parroquias de la [transcripción del clasificador
geográfico INEC 2025](https://github.com/GAumala/geografia.ec), emparejadas con
las provincias y cantones del catálogo existente. La fuente primaria vigente es
el [clasificador 2026 del INEC](https://aplicaciones2.ecuadorencifras.gob.ec/SIN/descargas/cge2026.pdf).
El usuario puede especificar un sitio más concreto dentro del cantón seleccionado.
