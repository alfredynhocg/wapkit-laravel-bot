<?php

namespace Wapkit\LaravelBot\Contracts;

interface ContextBuilderInterface
{
    /**
     * Build the context string injected into the AI system prompt.
     * Optionally filtered/focused by thematic area (e.g. 'salud', 'ventas').
     */
    public function buildContext(?string $area = null): string;

    /**
     * Build the complete system prompt, receiving the context string produced
     * by buildContext(). This is where you define the AI's persona, rules,
     * and tone for your specific domain.
     */
    public function buildSystemPrompt(string $context): string;

    /**
     * Return a map of thematic areas to trigger keywords used for context
     * pre-filtering before the AI call.
     *
     * Example:
     *   [
     *     'salud'  => ['medico', 'clinica', 'consulta', 'paciente'],
     *     'ventas' => ['precio', 'comprar', 'producto', 'stock'],
     *   ]
     */
    public function thematicAreas(): array;
}
