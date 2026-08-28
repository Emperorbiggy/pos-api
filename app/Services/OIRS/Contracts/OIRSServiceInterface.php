<?php

declare(strict_types=1);

namespace App\Services\OIRS\Contracts;

use App\Services\OIRS\DTOs\InvoiceData;
use App\Services\OIRS\DTOs\InvoiceDetailsData;
use App\Services\OIRS\DTOs\PaymentNotificationData;
use App\Services\OIRS\DTOs\PaymentValidationData;
use App\Services\OIRS\Exceptions\OIRSException;
use Carbon\Carbon;

interface OIRSServiceInterface
{
    /**
     * Validate an IPN against the OIRS Terminal API.
     *
     * @throws OIRSException
     */
    public function validateIpn(string $ipn, string $terminalId): PaymentValidationData;

    /**
     * Notify OIRS about a successful payment collected on a terminal.
     *
     * @throws OIRSException
     */
    public function paymentNotification(
        string $ipn,
        float $amountPaid,
        string $terminalId,
        Carbon $paidAt
    ): PaymentNotificationData;

    /**
     * Generate an OIRS invoice and return the authorization URL.
     *
     * @throws OIRSException
     */
    public function generateInvoice(
        string $revenueCode,
        string $agencyCode,
        float $amount,
        string $pid,
        string $paymentType
    ): InvoiceData;

    /**
     * Fetch an existing OIRS invoice by its IPN.
     *
     * @throws OIRSException
     */
    public function fetchInvoice(string $ipn): InvoiceDetailsData;
}
