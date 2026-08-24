<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\IndexPaymentsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

final class PaymentController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    #[OA\Get(
        path: '/api/v1/payments',
        operationId: 'listPayments',
        summary: 'List payments',
        description: 'Return the authenticated merchant\'s payments, newest first. Only the caller\'s own payments are ever returned. Authentication: Bearer token required. Optional filters: status, terminal_id, ipn. Page size is controlled by per_page (1-100, default 25).',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', description: 'Only payments with this status, e.g. pending or paid.', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 50), example: 'pending'),
            new OA\Parameter(name: 'terminal_id', description: 'Only payments collected on this terminal.', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 50), example: '204401PG'),
            new OA\Parameter(name: 'ipn', description: 'Only the payment for this IPN.', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 50), example: '331622459317'),
            new OA\Parameter(name: 'per_page', description: 'Results per page.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1, default: 25)),
            new OA\Parameter(name: 'page', description: 'Page number.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments listed successfully.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentCollectionResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(IndexPaymentsRequest $request): AnonymousResourceCollection
    {
        /** @var User $merchant */
        $merchant = $request->user('api');

        $payments = $merchant->payments()
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('terminal_id')->isNotEmpty(), fn ($query) => $query->where('terminal_id', $request->string('terminal_id')->toString()))
            ->when($request->string('ipn')->isNotEmpty(), fn ($query) => $query->where('ipn', $request->string('ipn')->toString()))
            ->latest('id')
            ->paginate($request->integer('per_page') ?: self::DEFAULT_PER_PAGE)
            ->withQueryString();

        return PaymentResource::collection($payments);
    }
}
