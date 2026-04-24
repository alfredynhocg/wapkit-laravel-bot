<?php

namespace Wapkit\LaravelBot\Contracts;

interface BotHandlerInterface
{
    /**
     * Called when this handler is triggered by an intent keyword.
     * The $context array is the current conversation context (JSON stored in DB).
     */
    public function handle(string $from, array $context): void;
}
