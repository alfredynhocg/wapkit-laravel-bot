<?php

namespace Wapkit\LaravelBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = ['phone', 'nombre', 'estado', 'contexto', 'cliente_id'];

    protected $casts = ['contexto' => 'array'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversacion_id');
    }
}
