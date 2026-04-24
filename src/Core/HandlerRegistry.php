<?php

namespace Wapkit\LaravelBot\Core;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Central registry that maps intents, buttons, button prefixes, and states
 * to handler classes + methods. Each consuming project registers its own
 * handlers in its service provider — no code in the package changes.
 */
class HandlerRegistry
{
    private array $intents  = [];
    private array $buttons  = [];
    private array $prefixes = [];
    private array $states   = [];

    public function __construct(private readonly Container $app) {}

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register a handler for a keyword-detected intent.
     * The method receives ($from, $context).
     */
    public function intent(string $intent, string $class, string $method = 'handle'): static
    {
        $this->intents[$intent] = [$class, $method];
        return $this;
    }

    /**
     * Register a handler for an exact button ID.
     * The method receives ($from, $context).
     */
    public function button(string $id, string $class, string $method = 'handle'): static
    {
        $this->buttons[$id] = [$class, $method];
        return $this;
    }

    /**
     * Register a handler for buttons that start with $prefix.
     * The extracted suffix (e.g. the record ID) is passed as $payload.
     * The method receives ($from, $payload, $context).
     */
    public function prefix(string $prefix, string $class, string $method = 'handle'): static
    {
        $this->prefixes[$prefix] = [$class, $method];
        return $this;
    }

    /**
     * Register a handler for a specific conversation state.
     * When the user sends free text and the conversation is in this state,
     * this handler is invoked instead of the intent resolver.
     * The method receives ($from, $text, $context) — text is the raw input.
     */
    public function state(string $state, string $class, string $method = 'handle'): static
    {
        $this->states[$state] = [$class, $method];
        return $this;
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    public function resolveIntent(string $intent): ?array
    {
        return $this->intents[$intent] ?? null;
    }

    /**
     * Resolve a button ID to [$class, $method, $payload|null].
     * Checks exact buttons first, then prefix handlers.
     */
    public function resolveButton(string $id): ?array
    {
        if (isset($this->buttons[$id])) {
            return [...$this->buttons[$id], null];
        }

        foreach ($this->prefixes as $prefix => [$class, $method]) {
            if (str_starts_with($id, $prefix)) {
                return [$class, $method, substr($id, strlen($prefix))];
            }
        }

        return null;
    }

    public function resolveState(string $state): ?array
    {
        return $this->states[$state] ?? null;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Instantiate and call a resolved handler.
     *
     * @param  array       $resolved   [$class, $method]
     * @param  string|null $payload    Extracted suffix for prefix handlers, or raw
     *                                 text for state handlers.
     */
    public function call(string $class, string $method, string $from, array $context, ?string $payload = null): void
    {
        $handler = $this->app->make($class);

        if (! method_exists($handler, $method)) {
            throw new InvalidArgumentException("Method [{$method}] not found on [{$class}].");
        }

        if ($payload !== null) {
            $handler->{$method}($from, $payload, $context);
        } else {
            $handler->{$method}($from, $context);
        }
    }
}
