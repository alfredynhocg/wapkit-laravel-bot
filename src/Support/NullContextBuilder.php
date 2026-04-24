<?php

namespace Wapkit\LaravelBot\Support;

use Wapkit\LaravelBot\Contracts\ContextBuilderInterface;

/**
 * Minimal context builder used when the consuming application hasn't registered one.
 * Replace this binding with your domain-specific implementation in your ServiceProvider:
 *
 *   $this->app->bind(ContextBuilderInterface::class, MyContextBuilder::class);
 */
class NullContextBuilder implements ContextBuilderInterface
{
    public function buildContext(?string $area = null): string
    {
        return '';
    }

    public function buildSystemPrompt(string $context): string
    {
        return "Eres un asistente virtual. Responde de forma útil, breve y en español.\n\n{$context}";
    }

    public function thematicAreas(): array
    {
        return [];
    }
}
