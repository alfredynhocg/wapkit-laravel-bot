<?php

namespace Wapkit\LaravelBot\Contracts;

interface IntentResolverInterface
{
    /**
     * Resolve zero or more intent names from raw user text.
     * Returns an array of matched intent names (e.g. ['tramites', 'horario']).
     * An empty array means no intent was detected — AI fallback will be used.
     */
    public function resolve(string $text): array;
}
