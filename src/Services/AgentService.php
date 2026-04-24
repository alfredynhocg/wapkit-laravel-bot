<?php

namespace Wapkit\LaravelBot\Services;

use Wapkit\LaravelBot\Contracts\ContextBuilderInterface;
use Wapkit\LaravelBot\Core\ConversationManager;
use Wapkit\LaravelBot\Support\TextHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AgentService
{
    private string $model;

    private int $maxTokens;

    private float $temperature;

    private int $maxInputChars;

    private int $rateLimit;

    private array $forbidden = [
        'ignore previous', 'forget instructions', 'system prompt', 'ignore all',
        'new instructions', 'you are now', 'act as', 'pretend you', 'jailbreak',
        'dan mode', 'ignore your', 'disregard', 'override', 'bypass',
        'do anything now', 'sin restricciones', 'ignora tus instrucciones',
        'olvida tus instrucciones', 'actua como', 'finge ser',
    ];

    public function __construct(
        private readonly ConversationManager  $manager,
        private readonly ContextBuilderInterface $contextBuilder,
    ) {
        $this->model         = config('whatsapp-bot.ai.model', 'qwen2.5:7b');
        $this->maxTokens     = config('whatsapp-bot.ai.max_tokens', 512);
        $this->temperature   = config('whatsapp-bot.ai.temperature', 0.3);
        $this->maxInputChars = config('whatsapp-bot.ai.max_input_chars', 500);
        $this->rateLimit     = config('whatsapp-bot.ai.rate_limit', 20);
    }

    public function respond(string $phone, string $userInput): ?string
    {
        $input = $this->sanitize($userInput);

        if (! $this->checkRateLimit($phone)) {
            return '⏳ Estás enviando muchos mensajes. Por favor espera un momento.';
        }

        if ($this->isJailbreak($input)) {
            Log::warning('[WhatsAppBot] Jailbreak attempt blocked', ['phone' => $phone]);

            return 'Lo siento, no puedo procesar esa solicitud.';
        }

        $area      = $this->detectArea($input);
        $cacheKey  = $area ? "whatsapp_bot_ctx_{$area}" : 'whatsapp_bot_ctx';
        $context   = Cache::remember($cacheKey, 300, fn () => $this->contextBuilder->buildContext($area));
        $prompt    = $this->contextBuilder->buildSystemPrompt($context);

        $history  = $this->manager->getRecentMessages($phone, 6);
        $messages = $history->map(fn ($m) => $m->direccion === 'entrante'
            ? new UserMessage($m->contenido)
            : new AssistantMessage($m->contenido)
        )->all();
        $messages[] = new UserMessage($input);

        $start  = microtime(true);
        $output = null;

        try {
            $response = Prism::text()
                ->using(Provider::Ollama, $this->model)
                ->withSystemPrompt($prompt)
                ->withMessages($messages)
                ->withMaxTokens($this->maxTokens)
                ->usingTemperature($this->temperature)
                ->asText();

            $output = trim($response->text);

            $this->logInteraction(
                phone: $phone,
                input: $input,
                prompt: $prompt,
                output: $output,
                tokensIn: $response->usage->promptTokens ?? null,
                tokensOut: $response->usage->completionTokens ?? null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                error: false,
            );

            return $output ?: null;

        } catch (\Throwable $e) {
            Log::error('[WhatsAppBot] AI error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            $this->logInteraction(
                phone: $phone,
                input: $input,
                prompt: $prompt,
                output: null,
                tokensIn: null,
                tokensOut: null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                error: true,
            );

            return null;
        }
    }

    // -------------------------------------------------------------------------

    private function sanitize(string $input): string
    {
        return mb_substr(trim(strip_tags($input)), 0, $this->maxInputChars);
    }

    private function isJailbreak(string $input): bool
    {
        $normalized = preg_replace('/\s+/', ' ', trim(TextHelper::normalize($input)));

        foreach ($this->forbidden as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function checkRateLimit(string $phone): bool
    {
        $key = "whatsapp_bot_throttle:{$phone}";
        Cache::add($key, 0, 60);

        return (int) Cache::increment($key) <= $this->rateLimit;
    }

    private function detectArea(string $input): ?string
    {
        $normalized = TextHelper::normalize($input);

        foreach ($this->contextBuilder->thematicAreas() as $area => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $area;
                }
            }
        }

        return null;
    }

    private function logInteraction(
        string $phone,
        string $input,
        string $prompt,
        ?string $output,
        ?int $tokensIn,
        ?int $tokensOut,
        int $latencyMs,
        bool $error,
    ): void {
        try {
            DB::table('whatsapp_ai_logs')->insert([
                'phone'       => $phone,
                'input'       => $input,
                'prompt'      => mb_substr($prompt, 0, 5000),
                'output'      => $output ? mb_substr($output, 0, 2000) : null,
                'modelo'      => $this->model,
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
                'latencia_ms' => $latencyMs,
                'error'       => $error,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppBot] Could not log AI interaction', ['phone' => $phone]);
        }
    }
}
