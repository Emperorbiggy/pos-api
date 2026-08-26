<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Services\OIRS\DTOs\PaymentNotificationData;
use App\Services\OIRS\DTOs\PaymentValidationData;

/**
 * Keeps a merchant's payment ledger in step with OIRS.
 */
final class PaymentRecorder
{
    /**
     * Record a validated bill against the merchant, pending payment.
     *
     * Re-validating the same IPN refreshes the existing row instead of adding
     * another, so a cashier retrying a lookup cannot fan out duplicates.
     */
    public function recordValidation(User $merchant, PaymentValidationData $validation, ?string $location = null): Payment
    {
        $customer = $validation->customer;

        $attributes = [
            'terminal_id' => $validation->terminalId,
            'status' => $validation->status ?? Payment::STATUS_PENDING,
            'amount' => $validation->amount,
            'total_amount' => $validation->totalAmount,
            'amount_paid' => $validation->amountPaid,
            'description' => $validation->description,
            'customer_id' => $customer?->id,
            'customer_ipn' => $customer?->ipn,
            'customer_name' => $customer?->name,
            'customer_email' => $customer?->email,
            'customer_phone' => $customer?->phone,
            'customer_address' => $customer?->address,
        ];

        // Only overwrite a known location when the terminal reports one, so a
        // later call from a device with no fix cannot blank it out.
        if ($location !== null && $location !== '') {
            $attributes['location'] = $location;
        }

        return Payment::query()->updateOrCreate(
            [
                'user_id' => $merchant->getKey(),
                'ipn' => $validation->ipn,
            ],
            $attributes,
        );
    }

    /**
     * Apply a terminal's payment notification to the merchant's record.
     *
     * Returns null when the merchant has no record for that IPN, which happens
     * if a terminal notifies without validating first.
     */
    public function recordNotification(User $merchant, PaymentNotificationData $notification, ?string $location = null): ?Payment
    {
        $payment = Payment::query()
            ->where('user_id', $merchant->getKey())
            ->where('ipn', $notification->ipn)
            ->first();

        if ($payment === null) {
            return null;
        }

        $attributes = [
            'amount_paid' => $notification->amountPaid,
            'reference' => $notification->reference,
            'paid_at' => $notification->paidAt,
            'terminal_id' => $notification->terminalId,
        ];

        // Only overwrite the status when OIRS actually reported one, so a
        // silent response cannot erase a known state.
        if ($notification->status !== null) {
            $attributes['status'] = $notification->status;
        }

        if ($location !== null && $location !== '') {
            $attributes['location'] = $location;
        }

        $payment->update($attributes);

        return $payment->refresh();
    }
}
