<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TerminalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $terminal */
        $terminal = $this->resource;

        return [
            'id' => $terminal->id,
            'name' => $terminal->name,
            'email' => $terminal->email,
            'terminal_id' => $terminal->terminal_id,
            'is_admin' => (bool) $terminal->is_admin,
            // Never expose the hashes themselves, only whether a PIN is set.
            'has_pin' => $terminal->pin !== null,
            'created_at' => $terminal->created_at?->toIso8601String(),

            // Present only on the listing, which aggregates these per terminal.
            'transactions_count' => $this->when(
                $terminal->payments_count !== null,
                fn (): int => (int) $terminal->payments_count,
            ),
            'total_collected' => $this->when(
                $terminal->payments_sum_amount_paid !== null,
                fn (): float => (float) $terminal->payments_sum_amount_paid,
            ),
        ];
    }
}
