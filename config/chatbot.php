<?php

return [
    // El nombre del modelo es fijo para impedir usar accidentalmente uno mayor.
    'use_local_model' => env('CHATBOT_USE_LOCAL_MODEL', false),
    // Las respuestas habituales se resuelven con reglas y datos locales.
    'use_external_biology' => env('CHATBOT_USE_EXTERNAL_BIOLOGY', false),
    'ollama_url' => env('CHATBOT_OLLAMA_URL', 'http://127.0.0.1:11434'),
    'model' => 'qwen3:0.6b',
    'max_model_bytes' => 1_000_000_000,
];
