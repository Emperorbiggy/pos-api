<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OIRS\GenerateInvoiceRequest;
use App\Http\Requests\OIRS\PaymentNotificationRequest;
use App\Http\Requests\OIRS\ValidateIpnRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentNotificationResource;
use App\Http\Resources\PaymentValidationResource;
use App\Services\OIRS\Contracts\OIRSServiceInterface;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

final class OIRSController extends Controller
{
    public function __construct(
        private readonly OIRSServiceInterface $oirsService,
    ) {}

    #[OA\Post(
        path: '/api/v1/validate-ipn',
        operationId: 'validateIpn',
        summary: 'Validate IPN',
        description: 'Validate an OIRS IPN for a POS terminal. Authentication: Bearer token required. Validation rules: ipn is required string max 50; terminal_id is required string max 50.',
        tags: ['OIRS'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ValidateIpnRequest')),
        responses: [
            new OA\Response(response: 200, description: 'IPN validated successfully.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentValidationResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function validateIpn(ValidateIpnRequest $request): PaymentValidationResource
    {
        $validated = $request->validated();

        return new PaymentValidationResource(
            $this->oirsService->validateIpn($validated['ipn'], $validated['terminal_id'])
        );
    }

    #[OA\Post(
        path: '/api/v1/payment-notification',
        operationId: 'paymentNotification',
        summary: 'Send payment notification',
        description: 'Notify OIRS about a successful terminal payment. Authentication: Bearer token required. Validation rules: ipn required string max 50; amount_paid required numeric greater than zero; terminal_id required string max 50; paid_at required date.',
        tags: ['OIRS'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PaymentNotificationRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Payment notification sent successfully.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentNotificationResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function paymentNotification(PaymentNotificationRequest $request): PaymentNotificationResource
    {
        $validated = $request->validated();

        return new PaymentNotificationResource(
            $this->oirsService->paymentNotification(
                $validated['ipn'],
                (float) $validated['amount_paid'],
                $validated['terminal_id'],
                Carbon::parse($validated['paid_at'])
            )
        );
    }

    #[OA\Post(
        path: '/api/v1/invoices',
        operationId: 'generateInvoice',
        summary: 'Generate invoice',
        description: 'Generate an OIRS invoice and authorization URL. Authentication: Bearer token required. Validation rules: revenue_code required string max 50; agency_code required string max 50; amount required numeric greater than zero; pid required string max 50; payment_type required and must be individual or corporate.',
        tags: ['OIRS'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GenerateInvoiceRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Invoice generated successfully.', content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function generateInvoice(GenerateInvoiceRequest $request): InvoiceResource
    {
        $validated = $request->validated();

        return new InvoiceResource(
            $this->oirsService->generateInvoice(
                $validated['revenue_code'],
                $validated['agency_code'],
                (float) $validated['amount'],
                $validated['pid'],
                $validated['payment_type']
            )
        );
    }
}
