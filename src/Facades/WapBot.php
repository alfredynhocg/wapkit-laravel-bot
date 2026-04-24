<?php

namespace Wapkit\LaravelBot\Facades;

use Illuminate\Support\Facades\Facade;
use Wapkit\LaravelBot\Services\WhatsAppService;

/**
 * @method static array sendText(string $to, string $message, bool $previewUrl = false)
 * @method static array sendImage(string $to, string $source, string $caption = '')
 * @method static array sendDocument(string $to, string $source, string $caption = '', string $filename = '')
 * @method static array sendAudio(string $to, string $source)
 * @method static array sendVideo(string $to, string $source, string $caption = '')
 * @method static array sendLocation(string $to, float $lat, float $lng, string $name = '', string $address = '')
 * @method static array sendButtons(string $to, string $body, array $buttons, string $header = '', string $footer = '')
 * @method static array sendList(string $to, string $header, string $body, string $footer, string $buttonText, array $sections)
 * @method static array sendTemplate(string $to, string $templateName, string $language, array $parameters = [])
 * @method static array sendCtaUrl(string $to, string $body, string $buttonText, string $url, string $footer = '')
 * @method static void sendTyping(string $to, string $messageId)
 *
 * @see \Wapkit\LaravelBot\Services\WhatsAppService
 */
class WapBot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WhatsAppService::class;
    }
}
