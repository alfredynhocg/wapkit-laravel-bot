<?php

return [

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token'    => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret'      => env('WHATSAPP_APP_SECRET'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version'     => env('WHATSAPP_API_VERSION', 'v20.0'),
    ],

    'bot' => [
        'initial_state'    => 'menu',
        'support_state'    => 'soporte',
        'default_intent'   => 'saludo',
        'welcome_message'  => env('BOT_WELCOME_MESSAGE', '👋 ¡Hola, {nombre}! ¿En qué puedo ayudarte hoy?'),
        'thinking_message' => env('BOT_THINKING_MESSAGE', '⏳ Un momento, estoy buscando la respuesta...'),
        'support_message'  => env('BOT_SUPPORT_MESSAGE', '📩 Tu mensaje fue recibido. Un asesor te atenderá pronto.'),
        'fallback_message' => env('BOT_FALLBACK_MESSAGE', 'No entendí tu consulta. ¿Puedo ayudarte con algo más?'),
    ],

    'ai' => [
        'provider'        => env('AI_PROVIDER', 'ollama'),
        'model'           => env('AI_MODEL', 'qwen2.5:7b'),
        'host'            => env('OLLAMA_HOST', 'http://localhost:11434'),
        'max_tokens'      => (int) env('AI_MAX_TOKENS', 512),
        'temperature'     => (float) env('AI_TEMPERATURE', 0.3),
        'timeout'         => (int) env('AI_TIMEOUT_SECONDS', 30),
        'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 500),
        'rate_limit'      => (int) env('AI_RATE_LIMIT', 20),
        'enabled'         => (bool) env('AI_ENABLED', true),
    ],

    'routes' => [
        'enabled'    => true,
        'prefix'     => 'webhook',
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Base keywords
    |--------------------------------------------------------------------------
    | These are the default intents the engine resolves from free text.
    | Add your domain-specific keywords in your app's config/whatsapp-bot.php
    | by merging arrays, or override this section entirely.
    |
    | Format:  'intent_name' => ['keyword1', 'keyword2', ...]
    */
    'keywords' => [
        'saludo'  => ['hola', 'buenas', 'buenos dias', 'inicio', 'menu', 'start', 'empezar', 'comenzar'],
        'soporte' => ['ayuda', 'soporte', 'hablar con alguien', 'humano', 'persona', 'asesor', 'quiero hablar'],
    ],

];
