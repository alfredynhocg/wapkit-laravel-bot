<?php

namespace Wapkit\LaravelBot\Support;

class TextHelper
{
    /**
     * Lowercase, strip accents, and collapse whitespace.
     * Used for keyword matching and jailbreak detection.
     */
    public static function normalize(string $text): string
    {
        $text   = strtolower(trim(strip_tags($text)));
        $result = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/\s+/', ' ', $result !== false ? $result : $text);
    }
}
