<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OIRS\GenerateInvoiceRequest;
use App\Http\Requests\OIRS\PaymentNotificationRequest;
use App\Http\Requests\OIRS\ValidateIpnRequest;
use App\Http\Resources\InvoiceDetailsResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentNotificationResource;
use App\Http\Resources\PaymentValidationResource;
use App\Models\User;
use App\Services\OIRS\Contracts\OIRSServiceInterface;
use App\Services\Payments\PaymentRecorder;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

final class OIRSController extends Controller
{
    public function __construct(
        private readonly OIRSServiceInterface $oirsService,
        private readonly PaymentRecorder $payments,
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
            new OA\Response(response: 400, description: 'OIRS rejected the request on business grounds, e.g. the IPN has already been paid for. The message is relayed verbatim.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'OIRS is unreachable, returned an error of its own, or rejected the credentials this API uses.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function validateIpn(ValidateIpnRequest $request): PaymentValidationResource
    {
        $validated = $request->validated();

        $validation = $this->oirsService->validateIpn($validated['ipn'], $validated['terminal_id']);

        /** @var User $merchant */
        $merchant = $request->user('api');
        $this->payments->recordValidation($merchant, $validation, $validated['location'] ?? null);

        return new PaymentValidationResource($validation);
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
            new OA\Response(response: 400, description: 'OIRS rejected the request on business grounds, e.g. the IPN has already been paid for. The message is relayed verbatim.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'OIRS is unreachable, returned an error of its own, or rejected the credentials this API uses.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function paymentNotification(PaymentNotificationRequest $request): PaymentNotificationResource
    {
        $validated = $request->validated();

        $notification = $this->oirsService->paymentNotification(
            $validated['ipn'],
            (float) $validated['amount_paid'],
            $validated['terminal_id'],
            Carbon::parse($validated['paid_at'])
        );

        /** @var User $merchant */
        $merchant = $request->user('api');
        $this->payments->recordNotification($merchant, $notification, $validated['location'] ?? null);

        return new PaymentNotificationResource($notification);
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
            new OA\Response(response: 400, description: 'OIRS rejected the request on business grounds, e.g. the IPN has already been paid for. The message is relayed verbatim.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'OIRS is unreachable, returned an error of its own, or rejected the credentials this API uses.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    #[OA\Get(
        path: '/api/v1/invoices/{ipn}',
        operationId: 'showInvoice',
        summary: 'Fetch invoice',
        description: 'Look up an existing OIRS invoice by its IPN. Served from the OIRS base URL. Authentication: Bearer token required.',
        tags: ['OIRS'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'ipn', description: 'The invoice payment number to look up.', in: 'path', required: true, schema: new OA\Schema(type: 'string', maxLength: 50, pattern: '^[A-Za-z0-9\-]{1,50}$'), example: '426878163229'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice found.', content: new OA\JsonContent(ref: '#/components/schemas/InvoiceDetailsResponse')),
            new OA\Response(response: 400, description: 'OIRS rejected the request on business grounds. The message is relayed verbatim.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'No invoice exists for that IPN.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 502, description: 'OIRS is unreachable, returned an error of its own, or rejected the credentials this API uses.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function showInvoice(string $ipn): InvoiceDetailsResource
    {
        return new InvoiceDetailsResource($this->oirsService->fetchInvoice($ipn));
    }

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
