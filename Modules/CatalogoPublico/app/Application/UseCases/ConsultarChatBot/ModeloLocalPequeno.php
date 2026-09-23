<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Http;

final class ModeloLocalPequeno
{
    public function resumir(string $pregunta, string $fuente): ?string
    {
        if (! config('chatbot.use_local_model')) {
            return null;
        }

        $url = rtrim((string) config('chatbot.ollama_url'), '/');
        $modelo = (string) config('chatbot.model');
        $maximo = (int) config('chatbot.max_model_bytes');

        try {
            $inventario = Http::timeout(2)->get($url.'/api/tags');
            if (! $inventario->successful()) {
                return null;
            }

            $coincidencias = array_filter(
                (array) $inventario->json('models', []),
                static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $modelo,
            );
            $instalado = reset($coincidencias);
            $tamano = is_array($instalado) ? ($instalado['size'] ?? null) : null;
            if (! is_int($tamano) || $tamano <= 0 || $tamano > $maximo) {
                return null;
            }

            $respuesta = Http::timeout(30)->post($url.'/api/chat', [
                'model' => $modelo,
                'stream' => false,
                'think' => false,
                'options' => ['temperature' => 0.2, 'num_predict' => 180],
                'messages' => [
                    ['role' => 'system', 'content' => 'Responde en español, breve y claro. Usa solo el texto de la fuente. No inventes datos, enlaces ni hechos del museo. Si la fuente no responde, indícalo.'],
                    ['role' => 'user', 'content' => "Pregunta: {$pregunta}\n\nFuente: {$fuente}"],
                ],
            ]);

            $texto = trim((string) $respuesta->json('message.content', ''));

            return $respuesta->successful() && $texto !== '' ? $texto : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
