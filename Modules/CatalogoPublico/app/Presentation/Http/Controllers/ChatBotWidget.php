<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Component;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;

final class ChatBotWidget extends Component
{
    public bool $abierto = false;

    public string $pregunta = '';

    public bool $procesando = false;

    /** @var list<array{rol: 'visitante'|'chatbot', texto: string, referencias?: list<string>}> */
    public array $mensajes = [];

    public function alternar(): void
    {
        $this->abierto = ! $this->abierto;

        if (! $this->abierto) {
            $this->dispatch('chat-cerrado');
        }
    }

    public function enviar(AsistentePortal $asistente, ConsultarChatBotHandler $handler): void
    {
        $pregunta = trim($this->pregunta);

        if ($pregunta === '') {
            return;
        }

        $this->mensajes[] = ['rol' => 'visitante', 'texto' => $pregunta];
        $this->pregunta = '';
        $this->procesando = true;

        $output = $asistente->responder($pregunta, $handler);

        $this->mensajes[] = [
            'rol' => 'chatbot',
            'texto' => $output['texto'],
            'opciones' => $output['opciones'],
        ];

        $this->procesando = false;
    }

    public function sugerir(string $pregunta, AsistentePortal $asistente, ConsultarChatBotHandler $handler): void
    {
        $this->pregunta = $pregunta;
        $this->enviar($asistente, $handler);
    }

    public function render(): View
    {
        return view('catalogopublico::livewire.chat-bot-widget');
    }
}
