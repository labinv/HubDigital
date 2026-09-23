<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ModeloLocalPequeno;

uses(\Tests\InfrastructureTestCase::class);

test('el modelo local no se invoca por defecto ni si supera un gigabyte', function (): void {
    config()->set('chatbot.use_local_model', false);
    Http::fake();
    expect((new ModeloLocalPequeno)->resumir('pregunta', 'fuente'))->toBeNull();
    Http::assertNothingSent();

    config()->set('chatbot.use_local_model', true);
    Http::fake(['*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 1_000_000_001]]])]);
    expect((new ModeloLocalPequeno)->resumir('pregunta', 'fuente'))->toBeNull();
    Http::assertSentCount(1);
});


test('el modelo pequeno responde solo con una fuente si esta habilitado y bajo el limite', function (): void {
    config()->set('chatbot.use_local_model', true);
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => 'Los insectos tienen tres pares de patas.']]),
    ]);

    expect((new ModeloLocalPequeno)->resumir('¿Cuántas patas tienen los insectos?', 'Los insectos tienen seis patas.'))
        ->toContain('tres pares');
    Http::assertSentCount(2);
});
