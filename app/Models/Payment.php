<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    /**
     * Status a payment carries until the terminal reports otherwise.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ipn',
        'terminal_id',
        'status',
        'amount',
        'total_amount',
        'amount_paid',
        'description',
        'customer_id',
        'customer_ipn',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'reference',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'total_amount' => 'float',
            'amount_paid' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The merchant this payment belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
