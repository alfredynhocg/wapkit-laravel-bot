# wapkit/laravel-bot

Todo lo que necesitás para enviar mensajes de WhatsApp, recibir webhooks y construir chatbots completos en Laravel — usando la **WhatsApp Business Cloud API** oficial.

Este paquete incluye un motor listo para producción con gestión de estado de conversación, respaldo con IA, enrutamiento por intenciones y una Facade simple para que puedas enviar tu primer mensaje en menos de 5 minutos.

---

## Requisitos previos

Antes de empezar, necesitás:

1. **PHP 8.2+** y **Laravel 10, 11, 12 o 13**
2. Una **[Cuenta de Meta Developers](https://developers.facebook.com/)** con una app de WhatsApp Business
3. Tu **Phone Number ID**, **Access Token**, **App Secret** y un **Verify Token** (esta cadena la elegís vos)

---

## Instalación

```bash
composer require wapkit/laravel-bot
```

```bash
php artisan wapkit:install
```

Listo. El comando de instalación publica el archivo de configuración y ejecuta las tres migraciones del paquete automáticamente.

---

## Parte 1: Enviar un mensaje de WhatsApp desde Laravel

Una vez instalado, podés enviar mensajes desde cualquier parte de tu aplicación usando la Facade `WapBot`.

**Agregá tus credenciales en `.env`:**

```env
WHATSAPP_PHONE_NUMBER_ID=tu_phone_number_id
WHATSAPP_ACCESS_TOKEN=tu_access_token
WHATSAPP_APP_SECRET=tu_app_secret
WHATSAPP_VERIFY_TOKEN=tu_verify_token
```

**Enviar un mensaje de texto:**

```php
use WapBot;

WapBot::sendText('+1234567890', '¡Hola desde Laravel! 👋');
```

**Enviar una imagen:**

```php
WapBot::sendImage('+1234567890', 'https://ejemplo.com/imagen.jpg', '¡Mirá esto!');
```

**Enviar un documento:**

```php
WapBot::sendDocument('+1234567890', 'https://ejemplo.com/factura.pdf', 'Tu factura', 'factura.pdf');
```

**Enviar botones interactivos:**

```php
WapBot::sendButtons(
    to: '+1234567890',
    body: '¿En qué puedo ayudarte?',
    buttons: [
        ['id' => 'btn_pedidos',  'title' => '📦 Mis pedidos'],
        ['id' => 'btn_soporte',  'title' => '💬 Soporte'],
        ['id' => 'btn_catalogo', 'title' => '🛍️ Catálogo'],
    ],
);
```

**Enviar una plantilla de WhatsApp** (obligatoria para iniciar conversaciones):

```php
WapBot::sendTemplate('+1234567890', 'pedido_confirmado', 'es', [
    ['type' => 'text', 'text' => 'PED-1234'],
    ['type' => 'text', 'text' => 'Bs 59.99'],
]);
```

Podés usar la Facade en controladores, jobs, listeners, comandos Artisan — en cualquier parte de tu app Laravel.

---

## Parte 2: Recibir mensajes entrantes (Webhook)

El paquete **registra las rutas del webhook automáticamente** — no hace falta configuración adicional.

| Método | URL |
| --- | --- |
| `GET` | `/webhook/whatsapp` — Verificación de Meta |
| `POST` | `/webhook/whatsapp` — Mensajes entrantes |

**Exponé tu servidor local con ngrok:**

```bash
ngrok http 8000
```

**Configurá el webhook en Meta Developers:**

Copiá tu URL de ngrok, agregale `/webhook/whatsapp` y pegala en el campo **Webhook URL** de tu app de WhatsApp. Usá la misma cadena que pusiste en `WHATSAPP_VERIFY_TOKEN` como **Verify Token**.

Una vez verificado, cada mensaje enviado a tu número de WhatsApp llegará al controlador del paquete, que lo registra y lo enruta a tus handlers.

---

## Parte 3: Construir un bot de WhatsApp completo

Acá es donde wapkit se diferencia. En vez de escribir cadenas de `if/else` en tu controlador, registrás **handlers** — clases dedicadas para cada intención o botón — y el motor enruta automáticamente.

### Paso 1 — Generá tu estructura base

```bash
php artisan wapkit:make-context TiendaContextBuilder
php artisan wapkit:make-handler MenuHandler
php artisan wapkit:make-handler PedidoHandler
```

### Paso 2 — Completá tu ContextBuilder

El `ContextBuilder` es el único lugar que conoce los datos de tu negocio. Le proporciona contexto al agente de IA para que las respuestas estén basadas en tu catálogo, políticas y horarios reales.

```php
// app/WhatsApp/TiendaContextBuilder.php

class TiendaContextBuilder implements ContextBuilderInterface
{
    public function buildContext(?string $area = null): string
    {
        $productos = Producto::where('activo', true)
            ->get()
            ->map(fn ($p) => "- {$p->nombre}: Bs {$p->precio} | Tallas: {$p->tallas}")
            ->implode("\n");

        return <<<CTX
        TIENDA: ModaFácil
        DIRECCIÓN: Av. Comercio 456, Local 12
        HORARIOS: Lunes a Sábado 9:00-20:00
        TELÉFONO: +591 70000001

        PRODUCTOS:
        {$productos}

        ENVÍOS: Bs 20 en ciudad, Bs 40 al interior
        PAGOS: Efectivo, QR, tarjeta
        CTX;
    }

    public function buildSystemPrompt(string $context): string
    {
        return <<<SYSTEM
        Sos el asistente virtual de ModaFácil. Atendés clientes por WhatsApp.
        Respondé SOLO con información del contexto. Tono amigable. Máximo 4 oraciones.

        INFORMACIÓN DE LA TIENDA:
        {$context}
        SYSTEM;
    }

    public function thematicAreas(): array
    {
        return [
            'calzado' => ['zapato', 'zapatilla', 'bota', 'talle'],
            'envios'  => ['envio', 'delivery', 'llegar', 'despacho'],
        ];
    }
}
```

### Paso 3 — Escribí tus handlers

Cada handler es una clase PHP simple que envía una respuesta de WhatsApp. Inyectá `WhatsAppService` y `ConversationManager` según lo necesites.

```php
// app/WhatsApp/Handlers/MenuHandler.php

class MenuHandler implements BotHandlerInterface
{
    public function __construct(private readonly WhatsAppService $wa) {}

    public function handle(string $from, array $context): void
    {
        $this->wa->sendList(
            to: $from,
            header: '🛍️ ModaFácil',
            body: '¿En qué puedo ayudarte?',
            footer: 'Tu tienda de confianza',
            buttonText: 'Ver opciones',
            sections: [
                [
                    'title' => 'Compras',
                    'rows'  => [
                        ['id' => 'btn_catalogo', 'title' => 'Ver catálogo'],
                        ['id' => 'btn_pedido',   'title' => 'Hacer un pedido'],
                    ],
                ],
            ],
        );
    }
}
```

```php
// app/WhatsApp/Handlers/PedidoHandler.php

class PedidoHandler implements BotHandlerInterface
{
    public function __construct(
        private readonly WhatsAppService     $wa,
        private readonly ConversationManager $manager,
    ) {}

    public function handle(string $from, array $context): void
    {
        // Establecer estado para capturar el próximo mensaje de texto libre
        $this->manager->setState($from, 'esperando_pedido');
        $this->wa->sendText($from, '📝 Escribí los detalles de tu pedido (producto, talla, color):');
    }

    public function procesarPedido(string $from, string $detalle, array $context): void
    {
        $this->manager->reset($from);
        $this->wa->sendText($from, "✅ Pedido recibido: {$detalle}\n\nUn asesor te confirmará el precio en breve.");
    }
}
```

### Paso 4 — Registrá todo en un ServiceProvider

```php
// app/Providers/WhatsAppServiceProvider.php

use Wapkit\LaravelBot\Contracts\ContextBuilderInterface;
use Wapkit\LaravelBot\Core\HandlerRegistry;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContextBuilderInterface::class, TiendaContextBuilder::class);
    }

    public function boot(): void
    {
        $r = app(HandlerRegistry::class);

        // Handlers de intención por texto (activados por palabras clave)
        $r->intent('saludo',  MenuHandler::class);
        $r->intent('pedido',  PedidoHandler::class);

        // Handlers de botones (activados por ID exacto)
        $r->button('btn_menu',     MenuHandler::class);
        $r->button('btn_catalogo', CatalogoHandler::class);
        $r->button('btn_pedido',   PedidoHandler::class);

        // Handlers por prefijo — "producto_42" → ProductoHandler::showDetalle($from, '42', $ctx)
        $r->prefix('producto_', ProductoHandler::class, 'showDetalle');

        // Handlers de estado — se activa cuando el estado de conversación coincide y el usuario envía texto
        $r->state('esperando_pedido', PedidoHandler::class, 'procesarPedido');
    }
}
```

Registrá el provider en `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\WhatsAppServiceProvider::class,
];
```

### Paso 5 — Agregá las palabras clave de tu dominio

Publicá la configuración (si aún no lo hiciste) y extendé el array `keywords`:

```php
// config/whatsapp-bot.php

'keywords' => [
    // Palabras clave base (incluidas por defecto)
    'saludo'  => ['hola', 'buenas', 'inicio', 'menu', 'start'],
    'soporte' => ['ayuda', 'soporte', 'hablar', 'humano'],

    // Palabras clave de tu dominio
    'pedido'   => ['pedido', 'comprar', 'quiero', 'necesito'],
    'precios'  => ['precio', 'cuanto cuesta', 'talla', 'valor'],
    'envios'   => ['envio', 'delivery', 'cuando llega', 'despacho'],
],
```

Ahora ejecutá `php artisan serve`, apuntá tu webhook a la URL local con ngrok y tu bot está en vivo.

---

## Cómo funciona el enrutamiento

Cada mensaje entrante pasa por este flujo:

```
Usuario de WhatsApp
     ↓
POST /webhook/whatsapp
     ↓  Verificación HMAC + deduplicación de mensajes
BotEngine::handleMessage()
     ↓
ConversationManager — carga estado + contexto actuales
     ↓
Mensaje de TEXTO
  ├─ ¿Hay un handler registrado para el estado?  → StateHandler($from, $text, $ctx)
  ├─ ¿Exactamente 1 coincidencia por keyword?    → IntentHandler($from, $ctx)
  └─ 0 o múltiples coincidencias                → Agente IA (si está habilitado)
                                                    └─ sin respuesta → intención por defecto (menú)

Mensaje INTERACTIVO (botón / lista)
  ├─ ¿ID de botón exacto registrado?            → ButtonHandler($from, $ctx)
  ├─ ¿Comienza con un prefijo registrado?       → PrefixHandler($from, $sufijo, $ctx)
  └─ Sin coincidencia                           → intención por defecto (menú)
```

---

## Agente de IA

Cuando no se detecta ninguna intención, el motor llama al agente de IA automáticamente. Vos controlás **qué sabe** (via `ContextBuilder`) y **cómo se comporta** (via el system prompt).

**Activar/desactivar:**

```env
AI_ENABLED=true
```

**Cambiar de proveedor** — el paquete usa [Prism](https://github.com/prism-php/prism) como capa de abstracción, así que cambiar es solo modificar el `.env`:

```env
# Ollama (local, por defecto)
AI_PROVIDER=ollama
AI_MODEL=qwen2.5:7b
OLLAMA_HOST=http://localhost:11434

# OpenAI
AI_PROVIDER=openai
AI_MODEL=gpt-4o-mini
OPENAI_API_KEY=sk-...

# Anthropic
AI_PROVIDER=anthropic
AI_MODEL=claude-haiku-4-5-20251001
ANTHROPIC_API_KEY=sk-ant-...
```

**Ajustar el comportamiento de la IA:**

```env
AI_MAX_TOKENS=512        # longitud máxima de respuesta
AI_TEMPERATURE=0.3       # 0.0 = determinístico, 1.0 = creativo
AI_MAX_INPUT_CHARS=500   # truncar mensajes largos antes de enviarlos
AI_RATE_LIMIT=20         # máximo de llamadas IA por minuto por número de teléfono
```

---

## Temas avanzados

### Mensajes masivos con Laravel Queues

Para enviar mensajes a muchos usuarios sin bloquear el request, despachá un job:

```php
// app/Jobs/EnviarNotificacionWhatsApp.php

class EnviarNotificacionWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $telefono,
        private string $mensaje,
    ) {}

    public function handle(): void
    {
        WapBot::sendText($this->telefono, $this->mensaje);
    }
}
```

```php
$usuarios = User::whereNotNull('phone')->get();

foreach ($usuarios as $usuario) {
    EnviarNotificacionWhatsApp::dispatch($usuario->phone, '¡Nueva oferta disponible! 🎉');
}
```

> **Importante:** Para iniciar conversaciones (no respuestas), WhatsApp requiere **Plantillas de Mensaje** pre-aprobadas. Usá `WapBot::sendTemplate()` para campañas salientes.

### Laravel Events

Para bots complejos, usá eventos para desacoplar el webhook de tu lógica de negocio:

```php
// app/Events/MensajeWhatsAppRecibido.php

class MensajeWhatsAppRecibido
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $telefono,
        public string $mensaje,
        public string $estado,
    ) {}
}
```

```php
// Despachar desde tu handler
event(new MensajeWhatsAppRecibido($from, $text, $estadoActual));
```

### Laravel Notifications

Enviá mensajes de WhatsApp a través del sistema de notificaciones de Laravel:

```php
// app/Notifications/PedidoConfirmado.php

class PedidoConfirmado extends Notification
{
    public function via($notifiable): array
    {
        return ['database', 'whatsapp_custom'];
    }

    public function toWhatsApp($notifiable): void
    {
        WapBot::sendTemplate(
            $notifiable->phone,
            'pedido_confirmado',
            'es',
            [['type' => 'text', 'text' => $this->pedido->numero]]
        );
    }
}

// Uso
$usuario->notify(new PedidoConfirmado($pedido));
```

### Estado y contexto de conversación

Usá `ConversationManager` dentro de cualquier handler para controlar el flujo de conversación:

```php
use Wapkit\LaravelBot\Core\ConversationManager;

// Establecer un estado para capturar el próximo mensaje del usuario
$this->manager->setState($from, 'esperando_ci');

// Establecer estado + guardar datos para pasos posteriores
$this->manager->setState($from, 'esperando_confirmacion', ['pedido_id' => 42]);

// Leer el contexto guardado en el siguiente handler
public function confirmar(string $from, string $respuesta, array $context): void
{
    $pedidoId = $context['pedido_id']; // 42
}

// Volver al estado inicial (menú)
$this->manager->reset($from);

// Vincular una conversación de WhatsApp con un usuario del sistema
$this->manager->linkClient($from, $usuario->id);
```

### Registro en base de datos

Todos los mensajes e interacciones con IA se registran automáticamente:

| Tabla | Contenido |
| --- | --- |
| `whatsapp_conversations` | Estado actual, contexto JSON, ID de cliente vinculado |
| `whatsapp_messages` | Log completo de mensajes entrantes y salientes con timestamps |
| `whatsapp_ai_logs` | Input, prompt, output, tokens, latencia y errores de IA |

Consultálos como cualquier modelo Eloquent:

```php
use Wapkit\LaravelBot\Models\Conversation;
use Wapkit\LaravelBot\Models\Message;

// Todas las conversaciones en estado soporte
Conversation::where('estado', 'soporte')->get();

// Últimos 20 mensajes de un número
Message::where('phone', '591700000001')->latest()->take(20)->get();
```

---

## Métodos disponibles en la Facade `WapBot`

| Método | Descripción |
| --- | --- |
| `sendText($to, $mensaje)` | Mensaje de texto plano |
| `sendImage($to, $fuente, $caption)` | Imagen desde URL o media ID |
| `sendDocument($to, $fuente, $caption, $nombre)` | PDF, Word, Excel, etc. |
| `sendAudio($to, $fuente)` | Archivo de audio |
| `sendVideo($to, $fuente, $caption)` | Archivo de video |
| `sendLocation($to, $lat, $lng, $nombre, $direccion)` | Pin en mapa |
| `sendLocationRequest($to, $body)` | Solicitar ubicación al usuario |
| `sendButtons($to, $body, $buttons, $header, $footer)` | Botones de respuesta rápida (máx. 3) |
| `sendList($to, $header, $body, $footer, $buttonText, $sections)` | Menú de lista desplazable |
| `sendCtaUrl($to, $body, $buttonText, $url)` | Botón de llamada a la acción con enlace |
| `sendTemplate($to, $nombre, $idioma, $parametros)` | Plantilla de mensaje pre-aprobada |
| `sendTyping($to, $messageId)` | Mostrar indicador de escritura |
| `uploadMedia($filePath)` | Subir un archivo local y obtener el media ID |

---

## Comandos Artisan

| Comando | Descripción |
| --- | --- |
| `php artisan wapkit:install` | Publicar configuración + ejecutar migraciones |
| `php artisan wapkit:make-handler {Nombre}` | Generar un stub de handler |
| `php artisan wapkit:make-context {Nombre}` | Generar un stub de ContextBuilder |

---

## Referencia de variables de entorno

```env
# WhatsApp Cloud API
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_API_VERSION=v20.0

# Mensajes del bot
BOT_WELCOME_MESSAGE="👋 ¡Hola, {nombre}! ¿En qué puedo ayudarte hoy?"
BOT_THINKING_MESSAGE="⏳ Un momento..."
BOT_SUPPORT_MESSAGE="📩 Mensaje recibido. Un asesor te atenderá pronto."
BOT_FALLBACK_MESSAGE="No entendí tu consulta. Escribí *menu* para ver las opciones."

# Agente de IA
AI_ENABLED=true
AI_PROVIDER=ollama
AI_MODEL=qwen2.5:7b
OLLAMA_HOST=http://localhost:11434
AI_MAX_TOKENS=512
AI_TEMPERATURE=0.3
AI_TIMEOUT_SECONDS=30
AI_MAX_INPUT_CHARS=500
AI_RATE_LIMIT=20
```

---

## Preguntas frecuentes

**¿Cómo envío un mensaje de WhatsApp desde un controlador?**

```php
use WapBot;

class PedidoController extends Controller
{
    public function confirmar(Pedido $pedido): JsonResponse
    {
        WapBot::sendText($pedido->usuario->phone, "✅ Pedido #{$pedido->numero} confirmado.");
        return response()->json(['status' => 'ok']);
    }
}
```

**¿Cómo inicio una conversación con un usuario (sin que él haya escrito primero)?**

Usá una plantilla pre-aprobada — WhatsApp lo requiere para iniciar conversaciones:

```php
WapBot::sendTemplate($phone, 'mensaje_bienvenida', 'es');
```

**¿Cómo desactivo la IA y uso solo handlers?**

```env
AI_ENABLED=false
```

Cuando está desactivada, los mensajes sin coincidencia caen al handler de intención por defecto (normalmente tu menú).

**¿Puedo usar un proveedor de IA diferente?**

Sí. Configurá `AI_PROVIDER` como `openai`, `anthropic` o cualquier [proveedor soportado por Prism](https://github.com/prism-php/prism). No se necesitan cambios en el código.

**¿Cómo paso datos entre pasos de un flujo multi-paso?**

Usá `setState($from, $estado, $arrayContexto)` para guardar datos y leelos desde `$context` en el siguiente handler:

```php
// Paso 1
$this->manager->setState($from, 'paso_2', ['nombre' => $nombre]);

// Handler del Paso 2
public function paso2(string $from, string $input, array $context): void
{
    $nombre = $context['nombre'];
}
```

**¿Cómo manejo tipos de mensajes no soportados (imágenes, audio, reacciones)?**

El controlador del webhook solo enruta tipos `text` e `interactive`. Los demás tipos se registran pero se ignoran silenciosamente. Extendé `BotEngine::handleMessage()` en tu propio controlador si necesitás manejo personalizado.

**¿Puedo usar esto con múltiples números de WhatsApp?**

Cada instancia de la aplicación Laravel maneja un número de teléfono (configurado via `WHATSAPP_PHONE_NUMBER_ID`). Para múltiples números, desplegá instancias separadas o sobreescribí la configuración de `WhatsAppService` en tiempo de ejecución.

---

## Licencia

MIT
