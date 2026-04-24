<?php

namespace Wapkit\LaravelBot\Support;

use Wapkit\LaravelBot\Contracts\IntentResolverInterface;

/**
 * Default intent resolver: keyword matching against config('whatsapp-bot.keywords').
 *
 * Consuming applications extend the keywords list by merging their own intents
 * into config/whatsapp-bot.php. Replace this binding in the container to use
 * a completely different strategy (NLP, embeddings, etc.).
 */
class KeywordIntentResolver implements IntentResolverInterface
{
    public function resolve(string $text): array
    {
        $normalized = TextHelper::normalize($text);
        $keywords   = config('whatsapp-bot.keywords', []);
        $detected   = [];

        foreach ($keywords as $intent => $words) {
            foreach ($words as $word) {
                if (str_contains($normalized, $word)) {
                    $detected[] = $intent;
                    break;
                }
            }
        }

        return array_unique($detected);
    }
}
