<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'customer_id', 'number', 'description', 'amount_cents', 'currency', 'status', 'due_on', 'paid_at'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount_cents' => 'integer',
            'due_on' => 'immutable_date',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Open, InvoiceStatus::Processing], true)
            && $this->due_on->isBefore(today());
    }

    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Processing]);
    }
}
