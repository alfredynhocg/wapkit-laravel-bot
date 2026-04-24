<?php

namespace Wapkit\LaravelBot\Http\Controllers;

use Wapkit\LaravelBot\Core\BotEngine;
use Wapkit\LaravelBot\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly BotEngine       $bot,
        private readonly WhatsAppService $wa,
    ) {}

    public function verify(Request $request): Response|JsonResponse
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('whatsapp-bot.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response()->json(['error' => 'Verification failed'], 403);
    }

    public function receive(Request $request): JsonResponse
    {
        $appSecret = config('whatsapp-bot.whatsapp.app_secret');

        if (empty($appSecret)) {
            if (app()->environment('production')) {
                Log::error('[WhatsAppBot] WHATSAPP_APP_SECRET not configured in production — webhook rejected.');

                return response()->json(['error' => 'Webhook not configured'], 503);
            }
            Log::warning('[WhatsAppBot] app_secret not configured — webhook signature check skipped (non-production only).');
        } else {
            $signature = $request->header('X-Hub-Signature-256', '');
            $expected  = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

            if (! hash_equals($expected, $signature)) {
                Log::warning('[WhatsAppBot] Invalid webhook signature rejected', ['ip' => $request->ip()]);

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                if (isset($value['statuses'])) {
                    continue;
                }

                $name = $value['contacts'][0]['profile']['name'] ?? null;

                foreach ($value['messages'] ?? [] as $message) {
                    $messageId = $message['id'] ?? null;

                    // Idempotency: skip already-processed messages
                    try {
                        if ($messageId && ! Cache::add("wamsg:{$messageId}", true, 300)) {
                            continue;
                        }
                    } catch (\Throwable) {
                        // If cache is unavailable, process anyway
                    }

                    $from = $message['from'];
                    $type = $message['type'];

                    Log::info('[WhatsAppBot] Incoming message', [
                        'id'     => $messageId,
                        'from'   => $from,
                        'name'   => $name,
                        'type'   => $type,
                        'body'   => $message['text']['body'] ?? '[no text]',
                    ]);

                    $this->wa->sendTyping($from, $messageId);

                    try {
                        $this->bot->handleMessage($from, $type, $message, $name);
                    } catch (\Throwable $e) {
                        Log::error('[WhatsAppBot] Error in handleMessage', [
                            'from'  => $from,
                            'error' => $e->getMessage(),
                            'file'  => $e->getFile(),
                            'line'  => $e->getLine(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
