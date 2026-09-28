<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_token',
    'user_id',
    'role',
    'content',
    'tool_calls',
    'prompt_tokens',
    'completion_tokens',
])]
class AiChatMessage extends Model
{
    protected $table = 'ai_chat_messages';

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Các lượt của một hội thoại, cũ trước mới sau. */
    public function scopeForConversation(Builder $query, string $token): Builder
    {
        return $query->where('conversation_token', $token)->orderBy('id');
    }
}
