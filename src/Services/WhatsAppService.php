<?php

namespace Wapkit\LaravelBot\Services;

use Wapkit\LaravelBot\Core\ConversationManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;

    private string $token;

    private string $graphBaseUrl;

    public function __construct(private readonly ConversationManager $manager)
    {
        $phoneNumberId = config('whatsapp-bot.whatsapp.phone_number_id');
        $this->token   = config('whatsapp-bot.whatsapp.access_token');
        $version       = config('whatsapp-bot.whatsapp.api_version', 'v20.0');

        if (empty($phoneNumberId) || empty($this->token)) {
            throw new \RuntimeException(
                'WhatsApp credentials are not configured. Check WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN.'
            );
        }

        $this->graphBaseUrl = "https://graph.facebook.com/{$version}";
        $this->baseUrl      = "{$this->graphBaseUrl}/{$phoneNumberId}";
    }

    public function sendText(string $to, string $message, bool $previewUrl = false): array
    {
        $result = $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message, 'preview_url' => $previewUrl],
        ]);
        $this->logOutgoing($to, 'text', $message);

        return $result;
    }

    public function sendImage(string $to, string $source, string $caption = ''): array
    {
        $media = $this->resolveMedia($source);
        if ($caption) {
            $media['caption'] = $caption;
        }

        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'image',
            'image'             => $media,
        ]);
    }

    public function sendDocument(string $to, string $source, string $caption = '', string $filename = ''): array
    {
        $media = $this->resolveMedia($source);
        if ($caption) {
            $media['caption'] = $caption;
        }
        if ($filename) {
            $media['filename'] = $filename;
        }

        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'document',
            'document'          => $media,
        ]);
    }

    public function sendAudio(string $to, string $source): array
    {
        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'audio',
            'audio'             => $this->resolveMedia($source),
        ]);
    }

    public function sendVideo(string $to, string $source, string $caption = ''): array
    {
        $media = $this->resolveMedia($source);
        if ($caption) {
            $media['caption'] = $caption;
        }

        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'video',
            'video'             => $media,
        ]);
    }

    public function sendLocation(string $to, float $lat, float $lng, string $name = '', string $address = ''): array
    {
        $location = ['latitude' => $lat, 'longitude' => $lng];
        if ($name) {
            $location['name'] = $name;
        }
        if ($address) {
            $location['address'] = $address;
        }

        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'location',
            'location'          => $location,
        ]);
    }

    public function sendLocationRequest(string $to, string $body = '¿Dónde te encuentras?'): array
    {
        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'location_request_message',
                'body'   => ['text' => $body],
                'action' => ['name' => 'send_location'],
            ],
        ]);
    }

    public function sendContacts(string $to, array $contacts): array
    {
        $built = array_map(fn ($c) => [
            'name'   => [
                'formatted_name' => trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? '')),
                'first_name'     => $c['first_name'] ?? '',
                'last_name'      => $c['last_name'] ?? '',
            ],
            'phones' => [['phone' => $c['phone'], 'type' => 'CELL']],
        ], $contacts);

        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'contacts',
            'contacts'          => $built,
        ]);
    }

    public function sendButtons(
        string $to,
        string $body,
        array $buttons,
        string $header = '',
        string $footer = '',
    ): array {
        $interactive = [
            'type'   => 'button',
            'body'   => ['text' => $body],
            'action' => [
                'buttons' => array_map(fn ($b) => [
                    'type'  => 'reply',
                    'reply' => ['id' => $b['id'], 'title' => $b['title']],
                ], $buttons),
            ],
        ];

        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }
        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        $result = $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ]);
        $this->logOutgoing($to, 'interactive_buttons', $body);

        return $result;
    }

    public function sendButtonsWithImage(
        string $to,
        string $imageUrl,
        string $body,
        array $buttons,
        string $footer = '',
    ): array {
        $interactive = [
            'type'   => 'button',
            'header' => ['type' => 'image', 'image' => ['link' => $imageUrl]],
            'body'   => ['text' => $body],
            'action' => [
                'buttons' => array_map(fn ($b) => [
                    'type'  => 'reply',
                    'reply' => ['id' => $b['id'], 'title' => $b['title']],
                ], $buttons),
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        $result = $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ]);
        $this->logOutgoing($to, 'interactive_buttons_image', $body);

        return $result;
    }

    public function sendCtaUrl(
        string $to,
        string $body,
        string $buttonText,
        string $url,
        string $footer = '',
    ): array {
        $interactive = [
            'type'   => 'cta_url',
            'body'   => ['text' => $body],
            'action' => [
                'name'       => 'cta_url',
                'parameters' => ['display_text' => $buttonText, 'url' => $url],
            ],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        $result = $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ]);
        $this->logOutgoing($to, 'cta_url', $body);

        return $result;
    }

    public function sendList(
        string $to,
        string $header,
        string $body,
        string $footer,
        string $buttonText,
        array $sections,
    ): array {
        $builtSections = array_map(fn ($s) => [
            'title' => $s['title'],
            'rows'  => array_map(fn ($r) => array_filter([
                'id'          => $r['id'],
                'title'       => $r['title'],
                'description' => $r['description'] ?? '',
            ]), $s['rows']),
        ], $sections);

        $interactive = [
            'type'   => 'list',
            'header' => ['type' => 'text', 'text' => $header],
            'body'   => ['text' => $body],
            'action' => ['button' => $buttonText, 'sections' => $builtSections],
        ];

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        $result = $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ]);
        $this->logOutgoing($to, 'interactive_list', $body);

        return $result;
    }

    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = []): array
    {
        $template = [
            'name'     => $templateName,
            'language' => ['code' => $language],
        ];

        if (! empty($parameters)) {
            $template['components'] = [['type' => 'body', 'parameters' => $parameters]];
        }

        $result = $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $template,
        ]);
        $this->logOutgoing($to, 'template', $templateName);

        return $result;
    }

    public function sendTyping(string $to, string $messageId): void
    {
        try {
            $this->post('messages', [
                'messaging_product' => 'whatsapp',
                'status'            => 'read',
                'message_id'        => $messageId,
                'typing_indicator'  => ['type' => 'text'],
            ]);
        } catch (\Throwable) {
            // Non-critical
        }
    }

    public function markAsRead(string $messageId): array
    {
        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ]);
    }

    public function uploadMedia(string $filePath): array
    {
        $mime = mime_content_type($filePath);

        $response = Http::withToken($this->token)
            ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mime])
            ->post("{$this->baseUrl}/media", ['messaging_product' => 'whatsapp']);

        $body = $response->json() ?: [];

        if (! $response->successful()) {
            $error   = $body['error'] ?? $response->body();
            $message = is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : (string) $error;
            throw new \RuntimeException("WhatsApp uploadMedia error ({$response->status()}): {$message}");
        }

        return $body;
    }

    public function downloadMedia(string $mediaId): string
    {
        $meta = Http::withToken($this->token)->get("{$this->graphBaseUrl}/{$mediaId}")->json();

        return Http::withToken($this->token)->get($meta['url'])->body();
    }

    public function getBusinessProfile(): array
    {
        return Http::withToken($this->token)
            ->get("{$this->baseUrl}/whatsapp_business_profile", [
                'fields' => 'about,address,description,email,profile_picture_url,websites,vertical',
            ])
            ->json();
    }

    public function updateBusinessProfile(array $data): array
    {
        return $this->post('whatsapp_business_profile', array_merge(
            ['messaging_product' => 'whatsapp'],
            $data
        ));
    }

    // -------------------------------------------------------------------------

    private function post(string $endpoint, array $payload): array
    {
        $response = Http::withToken($this->token)->post("{$this->baseUrl}/{$endpoint}", $payload);
        $body     = $response->json() ?: [];

        if (! $response->successful()) {
            $error   = $body['error'] ?? $response->body();
            $message = is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : (string) $error;

            Log::error('[WhatsAppBot] API error', [
                'status'   => $response->status(),
                'endpoint' => $endpoint,
                'error'    => $message,
            ]);

            throw new \RuntimeException("WhatsApp API error ({$response->status()}): {$message}");
        }

        return $body;
    }

    private function resolveMedia(string $source): array
    {
        return str_starts_with($source, 'http') ? ['link' => $source] : ['id' => $source];
    }

    private function logOutgoing(string $phone, string $type, ?string $content): void
    {
        try {
            $this->manager->logOutgoing($phone, $type, $content);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppBot] Could not log outgoing message', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
