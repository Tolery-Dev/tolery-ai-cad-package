<?php

namespace Tolery\AiCad\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $file_purchase_id
 * @property string $stripe_invoice_id
 * @property string|null $stripe_subscription_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $number
 * @property string|null $status
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property int $amount_paid
 * @property string $currency
 * @property string|null $hosted_invoice_url
 * @property string|null $invoice_pdf
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Invoice extends Model
{
    protected $fillable = [
        'team_id',
        'file_purchase_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'stripe_payment_intent_id',
        'number',
        'status',
        'subtotal',
        'tax',
        'total',
        'amount_paid',
        'currency',
        'hosted_invoice_url',
        'invoice_pdf',
        'period_start',
        'period_end',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ChatTeam::class, 'team_id');
    }

    public function filePurchase(): BelongsTo
    {
        return $this->belongsTo(FilePurchase::class);
    }

    /**
     * Origin of the invoice: a recurring subscription or a one-shot file purchase.
     */
    public function getTypeAttribute(): string
    {
        return $this->stripe_subscription_id ? 'subscription' : 'one_shot';
    }

    public function getFormattedTotalAttribute(): string
    {
        return Number::currency($this->total / 100, in: $this->currency);
    }
}
