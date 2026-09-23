<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\Services\BuscadorTaxonesCercanos;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;

final class AsistentePortal
{
    public function __construct(
        private readonly ModeloLocalPequeno $modeloLocal,
        private readonly BuscadorTaxonesCercanos $buscadorTaxones,
    ) {}

    /** @return array{texto:string, opciones:list<array{label:string,url:string}>} */
    public function responder(string $pregunta, ConsultarChatBotHandler $catalogo): array
    {
        $normal = preg_replace('/^[\s\x{00bf}?]+/u', '', Str::lower(Str::ascii(trim($pregunta)))) ?? '';
        $opciones = $this->opcionesBase();

        if (preg_match('/^(menu|ayuda|que puedo hacer|que necesitas)/', $normal)) {
            return $this->menuPrincipal();
        }
        if (preg_match('/^(buscar un especimen|buscar especimen)$/', $normal)) {
            return ['texto' => '¿Qué dato tienes para buscar en los registros publicados?', 'opciones' => [
                ['label' => 'Código de catálogo', 'pregunta' => 'Tengo el código'],
                ['label' => 'Género o especie', 'pregunta' => 'Sé el nombre científico'],
                ['label' => 'Localidad', 'pregunta' => 'Buscar por localidad'],
            ]];
        }
        if (preg_match('/^(tengo el codigo|se el nombre cientifico)/', $normal)) {
            return ['texto' => 'Escribe el código o el nombre científico en tu próxima pregunta. Consultaré solo registros publicados y pediré que confirmes cualquier nombre parecido.', 'opciones' => [
                ['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')],
            ]];
        }
        if (preg_match('/^buscar por localidad/', $normal)) {
            return ['texto' => 'Abre el catálogo y usa el filtro de localidad para consultar los especímenes publicados.', 'opciones' => [
                ['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')],
            ]];
        }
        if (preg_match('/^(consultar la coleccion|consultar coleccion)/', $normal)) {
            return ['texto' => '¿Qué deseas conocer de la colección publicada?', 'opciones' => [
                ['label' => 'Cantidad de especies', 'pregunta' => '¿Cuántas especies hay en la colección?'],
                ['label' => 'Familias con más registros', 'pregunta' => '¿Qué familias tienen más registros?'],
                ['label' => 'Localidades de un taxón', 'pregunta' => 'Sé el nombre científico'],
            ]];
        }
        if (preg_match('/^(usar el portal|aprender a usar el portal)/', $normal)) {
            return ['texto' => 'Elige la tarea que quieres completar.', 'opciones' => [
                ['label' => 'Buscar y filtrar', 'pregunta' => 'Buscar un espécimen'],
                ['label' => 'Mi cuenta y roles', 'pregunta' => '¿Cómo configuro mi cuenta?'],
                ['label' => 'Trámites', 'pregunta' => 'Depósitos y préstamos'],
            ]];
        }
        if (preg_match('/^(depositos y prestamos|tramites)/', $normal)) {
            return ['texto' => '¿Qué trámite necesitas?', 'opciones' => [
                ['label' => 'Depositar o donar', 'pregunta' => '¿Cómo hago un depósito?'],
                ['label' => 'Solicitar préstamo', 'pregunta' => '¿Cómo solicito un préstamo?'],
            ]];
        }

        if (preg_match('/deposit|donaci|entregar|custodia|solicitud de material/', $normal)) {
            return [
                'texto' => 'Para registrar material, entra al portal de depositos, inicia sesion como Depositante y crea una solicitud. El asistente te guiara por tramite, origen, documentos, datos MEPN, detalle biologico y firma. Una donacion transfiere el material a la coleccion; un deposito es temporal.',
                'opciones' => [
                    ['label' => 'Ir a depositos', 'url' => route('depositos.portal')],
                    ['label' => 'Iniciar sesion', 'url' => route('login')],
                ],
            ];
        }

        if (preg_match('/prestam|solicitante|pedir especimen/', $normal)) {
            return [
                'texto' => 'Para solicitar especimenes en prestamo, entra con tu cuenta y activa el rol Solicitante desde Configuracion. Luego abre Mis solicitudes y registra el material que necesitas.',
                'opciones' => [
                    ['label' => 'Iniciar sesion', 'url' => route('login')],
                    ['label' => 'Explorar catalogo', 'url' => route('portal.catalogo')],
                ],
            ];
        }

        if (preg_match('/\bcuenta\b|\bclave\b|\bcontrasena\b|\busuario\b|\bregistrarse\b|\bconfiguraci|\brol\b|\bingresar\b|\bacceso\b|\bcorreo\b/', $normal)) {
            return [
                'texto' => 'Puedes registrarte desde el acceso al sistema. Si un administrador creo tu cuenta, usa la clave inicial y cambiala cuando el sistema te lo pida. En Configuracion puedes activar o cambiar entre los roles de Depositante y Solicitante.',
                'opciones' => [
                    ['label' => 'Entrar al sistema', 'url' => route('login')],
                    ['label' => 'Portal de depositos', 'url' => route('depositos.portal')],
                ],
            ];
        }

        if (preg_match('/^(catalogo|coleccion|filtrar catalogo|usar catalogo)$/', $normal)) {
            return [
                'texto' => 'En el catálogo puedes buscar por código, nombre científico y localidad. Escríbeme el dato que tienes o abre los filtros.',
                'opciones' => [
                    ['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')],
                    ['label' => 'Consultar la colección', 'pregunta' => 'Consultar la colección'],
                ],
            ];
        }

        $preguntaBiologica = (bool) preg_match('/^(que (son|es|hacen|funcion)|para que sirven|por que|como viven|cual es la funcion)/', $normal)
            && (bool) preg_match('/(artr[oó]pod|insect|invertebr|hormig|maripos|abej|avisp|escarabaj|crustace|molusc|nudibranquio|aracnid|aran)/', $normal)
            && ! preg_match('/(catalog|colecci|registr|deposit|prestam|localidad|provincia|cuant|nombre cientifico)/', $normal);
        if ($preguntaBiologica && ($respuestaBiologica = $this->biologiaLocal($normal)) !== null) {
            return $respuestaBiologica;
        }

        if (! $preguntaBiologica && preg_match('/catalog|colecci|buscar|especimen|registro|taxon|familia|genero|especie|invertebr|distribuci|provincia|[A-Z]{2,8}-\d+/i', Str::ascii($pregunta))) {
            try {
                $salida = $catalogo->handle(new ConsultarChatBotInput($pregunta));
                if ($salida->dentroDeDominio) {
                    if ($salida->respuesta === ChatBotMensajes::SIN_RESULTADOS) {
                        $sugeridos = $this->buscadorTaxones->sugerir($pregunta);
                        if ($sugeridos !== []) {
                            return [
                                'texto' => 'No encontré registros publicados con ese nombre. ¿Quisiste decir '.implode(', ', $sugeridos).'? Confirma el nombre antes de buscar.',
                                'opciones' => $opciones,
                            ];
                        }
                    }
                    return ['texto' => $salida->respuesta, 'opciones' => $opciones];
                }
            } catch (\Throwable $error) {
                report($error);
            }
        }

        if (! preg_match('/\b(artr[oó]pod|insect|invertebr|hormig|maripos|ara[nñ]|escarabaj|crust[aá]ce|molusc|biodivers|ecolog|taxonom|animal|especie|abej|avisp|cole[oó]pter|lepid[oó]pter)\w*/iu', $pregunta)
            && ! preg_match('/\b[A-Z][a-z]{2,}\s+[a-z]{3,}\b/u', $pregunta)) {
            return $this->menuPrincipal();
        }

        $biologiaLocal = $this->biologiaLocal($normal);
        if ($biologiaLocal !== null) {
            return $biologiaLocal;
        }

        if (! config('chatbot.use_external_biology', false)) {
            return ['texto' => 'Puedo responder sobre grupos comunes de invertebrados y consultar los registros publicados. Indica el grupo o un nombre científico para precisar la respuesta.', 'opciones' => [
                ['label' => 'Artrópodos', 'pregunta' => '¿Qué son los artrópodos?'],
                ['label' => 'Insectos', 'pregunta' => '¿Qué son los insectos?'],
                ['label' => 'Buscar espécimen', 'pregunta' => 'Buscar un espécimen'],
            ]];
        }

        $libre = $this->consultarWikipedia($pregunta);
        if ($libre !== null) {
            $respuesta = $this->modeloLocal->resumir($pregunta, $libre['extracto']) ?? $libre['extracto'];

            return ['texto' => $respuesta.' Fuente externa: '.$libre['url'], 'opciones' => $opciones];
        }

        return $this->menuPrincipal();
    }

    /** @return array{texto:string,opciones:array} */
    private function menuPrincipal(): array
    {
        return [
            'texto' => '¿Qué necesitas hacer? Elige una tarea o escribe tu pregunta.',
            'opciones' => [
                ['label' => 'Buscar espécimen', 'pregunta' => 'Buscar un espécimen'],
                ['label' => 'Consultar colección', 'pregunta' => 'Consultar la colección'],
                ['label' => 'Usar el portal', 'pregunta' => 'Usar el portal'],
                ['label' => 'Depósitos y préstamos', 'pregunta' => 'Depósitos y préstamos'],
            ],
        ];
    }

    /** @return array{texto:string,opciones:array}|null */
    private function biologiaLocal(string $normal): ?array
    {
        $temas = [
            'artropod' => [
                'Los artrópodos son invertebrados con exoesqueleto, cuerpo segmentado y apéndices articulados. Incluyen insectos, arácnidos y crustáceos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/the-cambrian-period.html',
            ],
            'insect' => [
                'Los insectos son artrópodos. En su etapa adulta tienen seis patas y el cuerpo dividido en cabeza, tórax y abdomen.',
                'Smithsonian', 'https://naturalhistory.si.edu/education/teaching-resources/life-science/what-insect',
            ],
            'invertebr' => [
                'Los invertebrados son animales sin columna vertebral. Incluyen, entre otros, artrópodos y moluscos; no todos son insectos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/molluscs.html',
            ],
            'molusc' => [
                'Los moluscos son invertebrados; el grupo incluye caracoles, almejas y pulpos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/molluscs.html',
            ],
            'aracnid' => [
                'Los arácnidos son artrópodos distintos de los insectos. Entre ellos están las arañas, los escorpiones y las garrapatas.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/the-cambrian-period.html',
            ],
            'hormig' => [
                'Las hormigas son insectos sociales. Su función depende de la especie: algunas depredan otros invertebrados, otras dispersan semillas y las cortadoras cultivan hongos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/life-in-soil.html',
            ],
            'maripos' => [
                'Las mariposas son insectos del orden Lepidoptera. Muchas visitan flores y pueden transportar polen, aunque su aporte cambia según la especie y el hábitat.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/insect-pollination.html',
            ],
            'abej' => [
                'Muchas abejas transportan polen al visitar flores. La polinización favorece la reproducción de numerosas plantas; no todas las especies de abejas viven en colonias.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/insect-pollination.html',
            ],
            'escarabaj' => [
                'Los escarabajos son insectos del orden Coleoptera. Algunas especies visitan flores y transportan polen; sus funciones ecológicas varían mucho entre grupos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/insect-pollination.html',
            ],
            'crustace' => [
                'Los crustáceos son artrópodos; entre ellos hay cangrejos, camarones y langostas. Sus formas y hábitats son diversos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/the-cambrian-period.html',
            ],
            'nudibranquio' => [
                'Los nudibranquios son moluscos marinos del grupo de los gasterópodos. No son insectos ni crustáceos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/molluscs.html',
            ],

        ];
        if (str_contains($normal, 'aran')) {
            $normal .= ' aracnid';
        }
        foreach ($temas as $palabra => [$texto, $fuente, $url]) {
            if (str_contains($normal, $palabra)) {
                return ['texto' => $texto, 'opciones' => [
                    ['label' => 'Fuente: '.$fuente, 'url' => $url],
                    ['label' => 'Explorar catálogo', 'url' => route('portal.catalogo')],
                ]];
            }
        }

        return null;
    }

    /** @return list<array{label:string,url:string}> */
    public function opcionesBase(): array
    {
        return [
            ['label' => 'Catalogo de especimenes', 'url' => route('portal.catalogo')],
            ['label' => 'Depositos', 'url' => route('depositos.portal')],
            ['label' => 'Acceder', 'url' => route('login')],
        ];
    }

    /** @return array{extracto:string,url:string}|null */
    private function consultarWikipedia(string $pregunta): ?array
    {
        $tema = preg_replace('/^[\\s\\x{00bf}\\?]*(?:qu[e\\x{00e9}]|cu[a\\x{00e1}]l(?:es)?|c[o\\x{00f3}]mo|d[o\\x{00f3}]nde)\\s+(?:son|es|se|viven|hacen)?\\s*(?:los|las|el|la|un|una)?\\s*/iu', '', trim($pregunta));
        $tema = trim((string) $tema, " \\t\\n\\r\\0\\x0B?\\x{00bf}");

        $clave = 'chatbot:fuente:'.hash('sha256', Str::lower($tema));
        $enCache = Cache::get($clave);
        if (is_array($enCache) && isset($enCache['extracto'], $enCache['url'])) {
            return $enCache;
        }

        try {
            $resultado = Http::withHeaders([
                'User-Agent' => 'HubDigital/1.0 (consulta educativa; contacto: hubdigital.epn@kintiflow.com)',
            ])->timeout(4)->get('https://es.wikipedia.org/w/api.php', [
                'action' => 'query',
                'generator' => 'search',
                'gsrsearch' => Str::limit($tema !== '' ? $tema : trim($pregunta), 160, ''),
                'gsrlimit' => 5,
                'prop' => 'extracts|info',
                'exintro' => 1,
                'explaintext' => 1,
                'exchars' => 800,
                'inprop' => 'url',
                'format' => 'json',
                'formatversion' => 2,
            ]);

            if (! $resultado->successful()) {
                return null;
            }

            $paginas = (array) $resultado->json('query.pages', []);
            $raiz = mb_substr(Str::lower(Str::ascii($tema)), 0, 5);
            $pagina = collect($paginas)->first(static fn (mixed $item): bool => is_array($item)
                && $raiz !== '' && str_starts_with(Str::lower(Str::ascii((string) ($item['title'] ?? ''))), $raiz))
                ?? ($paginas[0] ?? []);
            $resumen = trim((string) ($pagina['extract'] ?? ''));
            $enlace = (string) ($pagina['fullurl'] ?? '');
            if ($resumen === '' || ! str_starts_with($enlace, 'https://es.wikipedia.org/')) {
                return null;
            }

            $fuente = ['extracto' => Str::limit($resumen, 760), 'url' => $enlace];
            Cache::put($clave, $fuente, now()->addDay());

            return $fuente;
        } catch (\Throwable) {
            return null;
        }
    }
}
