<?php

namespace Wapkit\LaravelBot\Core;

use Wapkit\LaravelBot\Models\Conversation;
use Wapkit\LaravelBot\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ConversationManager
{
    public function getOrCreate(string $phone, ?string $name = null): Conversation
    {
        $conv = Conversation::firstOrCreate(
            ['phone' => $phone],
            [
                'nombre'   => $name,
                'estado'   => config('whatsapp-bot.bot.initial_state', 'menu'),
                'contexto' => [],
            ]
        );

        if (! $conv->wasRecentlyCreated && $name && $conv->nombre !== $name) {
            $conv->update(['nombre' => $name]);
            $conv->nombre = $name;
        }

        return $conv;
    }

    public function setState(string $phone, string $state, array $context = []): void
    {
        Conversation::where('phone', $phone)->update([
            'estado'   => $state,
            'contexto' => $context,
        ]);
    }

    public function setContext(string $phone, array $context): void
    {
        Conversation::where('phone', $phone)->update(['contexto' => $context]);
    }

    public function reset(string $phone): void
    {
        $this->setState($phone, config('whatsapp-bot.bot.initial_state', 'menu'), []);
    }

    public function linkClient(string $phone, int $clientId): void
    {
        Conversation::where('phone', $phone)->update(['cliente_id' => $clientId]);
    }

    public function logMessage(
        Conversation $conv,
        string $direction,
        string $type,
        ?string $content,
        ?string $waMessageId = null,
    ): void {
        try {
            Message::create([
                'conversacion_id'     => $conv->id,
                'phone'               => $conv->phone,
                'direccion'           => $direction,
                'tipo'                => $type,
                'contenido'           => $content,
                'whatsapp_message_id' => $waMessageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppBot] Could not log message', [
                'phone' => $conv->phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function logOutgoing(string $phone, string $type, ?string $content): void
    {
        try {
            $conv = Conversation::where('phone', $phone)->first();
            Message::create([
                'conversacion_id' => $conv?->id,
                'phone'           => $phone,
                'direccion'       => 'saliente',
                'tipo'            => $type,
                'contenido'       => $content,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppBot] Could not log outgoing message', ['phone' => $phone]);
        }
    }

    public function getRecentMessages(string $phone, int $limit = 6): Collection
    {
        $conv = Conversation::where('phone', $phone)->first();
        if (! $conv) {
            return collect();
        }

        return Message::where('conversacion_id', $conv->id)
            ->whereNotNull('contenido')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['direccion', 'contenido'])
            ->reverse()
            ->values();
    }
}
