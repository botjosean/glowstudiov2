<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A WhatsApp conversation nobody from the salon has dealt with yet.
 *
 * Two jobs in one row, on purpose (see the migration): the card the panel
 * shows, and the count of how many times the receptionist has already spoken
 * in this conversation.
 */
#[Fillable([
    'provider_id', 'phone', 'name', 'message', 'status',
    'bot_messages_sent', 'first_contact_at', 'last_contact_at', 'answered_at', 'alerted_at',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const STATUS_NEW = 'nuevo';

    public const STATUS_HANDLED = 'atendido';

    public const STATUS_DISMISSED = 'descartado';

    /**
     * How many messages the receptionist may send in one conversation.
     *
     * Patricia's own words: "que sean como máximo dos mensajes". One to say
     * the message arrived, one to ask for what she needs. After that the
     * conversation belongs to a person.
     */
    public const MAX_BOT_MESSAGES = 2;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_messages_sent' => 'int',
            'first_contact_at' => 'immutable_datetime',
            'last_contact_at' => 'immutable_datetime',
            'answered_at' => 'immutable_datetime',
            'alerted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Still waiting for a person.
     */
    #[Scope]
    protected function waiting(Builder $query): void
    {
        $query->where('status', self::STATUS_NEW);
    }

    /**
     * Whether the receptionist has used up its two messages.
     */
    public function botIsDone(): bool
    {
        return $this->bot_messages_sent >= self::MAX_BOT_MESSAGES;
    }
}
