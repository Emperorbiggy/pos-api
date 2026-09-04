<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'terminal_id' => $this->resource->terminal_id,
            // Lets a client decide between "create a PIN" and "enter your PIN"
            // without ever exposing the PIN itself.
            'has_pin' => $this->resource->pin !== null,
            // Lets the admin panel show or withhold its own navigation. This is
            // a convenience for the UI, never the access control itself: the
            // admin middleware is what actually guards those routes.
            'is_admin' => (bool) $this->resource->is_admin,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
