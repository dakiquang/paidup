<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['idempotency_key', 'provider_intent_id', 'client_secret', 'amount_cents', 'currency', 'status', 'last_error'];

    protected $attributes = ['status' => 'pending'];

    protected $hidden = ['client_secret', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'client_secret' => 'encrypted',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
