<?php

namespace Wapkit\LaravelBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversacion_id',
        'phone',
        'direccion',
        'tipo',
        'contenido',
        'whatsapp_message_id',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversacion_id');
    }
}
