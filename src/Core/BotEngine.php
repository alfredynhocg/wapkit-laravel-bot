<?php

namespace Wapkit\LaravelBot\Core;

use Wapkit\LaravelBot\Contracts\IntentResolverInterface;
use Wapkit\LaravelBot\Services\AgentService;
use Wapkit\LaravelBot\Services\WhatsAppService;

/**
 * Main orchestrator. Receives raw WhatsApp messages, routes them to registered
 * handlers, and falls back to the AI agent when no intent matches.
 *
 * This class has zero knowledge of the business domain. All domain logic lives
 * in the handlers registered via HandlerRegistry by the consuming application.
 */
class BotEngine
{
    public function __construct(
        private readonly WhatsAppService       $wa,
        private readonly ConversationManager   $manager,
        private readonly HandlerRegistry       $registry,
        private readonly IntentResolverInterface $intentResolver,
        private readonly AgentService          $agent,
    ) {}

    public function handleMessage(string $from, string $type, array $message, ?string $name = null): void
    {
        $conv    = $this->manager->getOrCreate($from, $name);
        $state   = $conv->estado;
        $context = $conv->contexto ?? [];

        $content = $message['text']['body']
            ?? $message['interactive']['button_reply']['title']
            ?? $message['interactive']['list_reply']['title']
            ?? null;

        $this->manager->logMessage($conv, 'entrante', $type, $content, $message['id'] ?? null);

        if ($type === 'text') {
            $this->routeText($from, trim($message['text']['body'] ?? ''), $state, $context);
            return;
        }

        if ($type === 'interactive') {
            $id = $message['interactive']['button_reply']['id']
                ?? $message['interactive']['list_reply']['id']
                ?? '';
            $this->routeButton($from, $id, $context);
        }
    }

    /**
     * Send a welcome message and trigger the default intent handler.
     * Call this from your own welcome/greeting flow.
     */
    public function sendWelcome(string $from): void
    {
        $conv    = $this->manager->getOrCreate($from);
        $name    = $conv->nombre ?? '';
        $message = config('whatsapp-bot.bot.welcome_message', '👋 ¡Hola! ¿En qué puedo ayudarte?');

        if ($name) {
            $message = str_replace('{nombre}', $name, $message);
        } else {
            $message = str_replace([', {nombre}', '{nombre}'], '', $message);
        }

        $this->wa->sendText($from, $message);
        $this->triggerDefault($from, $conv->contexto ?? []);
    }

    // -------------------------------------------------------------------------
    // Internal routing
    // -------------------------------------------------------------------------

    private function routeText(string $from, string $text, string $state, array $context): void
    {
        // When in support/human-handoff state, acknowledge and stop processing
        if ($state === config('whatsapp-bot.bot.support_state', 'soporte')) {
            $this->wa->sendText($from, config('whatsapp-bot.bot.support_message'));
            return;
        }

        // State-bound handler: fires when the conversation is waiting for specific input
        // (e.g. waiting for a tracking number, a search term, a form field, etc.)
        $stateHandler = $this->registry->resolveState($state);
        if ($stateHandler) {
            [$class, $method] = $stateHandler;
            $this->registry->call($class, $method, $from, $context, $text);
            return;
        }

        // Keyword intent detection
        $intents = $this->intentResolver->resolve($text);

        if (count($intents) === 1) {
            $handler = $this->registry->resolveIntent($intents[0]);
            if ($handler) {
                [$class, $method] = $handler;
                $this->registry->call($class, $method, $from, $context);
                return;
            }
        }

        // Multiple intents or no intent match → AI agent
        if (config('whatsapp-bot.ai.enabled', true)) {
            $this->wa->sendText($from, config('whatsapp-bot.bot.thinking_message'));
            $response = $this->agent->respond($from, $text);
            if ($response !== null) {
                $this->wa->sendText($from, $response);
                return;
            }
        }

        // Final fallback: trigger the default intent (usually the main menu)
        $this->triggerDefault($from, $context);
    }

    private function routeButton(string $from, string $id, array $context): void
    {
        $resolved = $this->registry->resolveButton($id);
        if ($resolved) {
            [$class, $method, $payload] = $resolved;
            $this->registry->call($class, $method, $from, $context, $payload);
            return;
        }

        $this->triggerDefault($from, $context);
    }

    private function triggerDefault(string $from, array $context): void
    {
        $defaultIntent = config('whatsapp-bot.bot.default_intent', 'saludo');
        $handler       = $this->registry->resolveIntent($defaultIntent);

        if ($handler) {
            [$class, $method] = $handler;
            $this->registry->call($class, $method, $from, $context);
        } else {
            $this->wa->sendText($from, config('whatsapp-bot.bot.fallback_message'));
        }
    }
}
