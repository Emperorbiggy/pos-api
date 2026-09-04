<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTransactionsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

final class TransactionController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    #[OA\Get(
        path: '/api/v1/admin/transactions',
        operationId: 'adminListTransactions',
        summary: 'List all transactions',
        description: 'Every transaction across every terminal, newest first. Admin only. Filter by terminal_id, status, ipn, and a from/to date range on the record date; search matches IPN or payer name.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'terminal_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ipn', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1, default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transactions listed.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentCollectionResponse')),
            new OA\Response(response: 403, description: 'Not an administrator.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(IndexTransactionsRequest $request): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            $this->filtered($request)
                ->latest('id')
                ->paginate($request->integer('per_page') ?: self::DEFAULT_PER_PAGE)
                ->withQueryString()
        );
    }

    #[OA\Get(
        path: '/api/v1/admin/transactions/summary',
        operationId: 'adminTransactionSummary',
        summary: 'Transaction totals',
        description: 'Headline figures for the dashboard, honouring the same filters as the transaction list. Admin only.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Summary returned.'),
            new OA\Response(response: 403, description: 'Not an administrator.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function summary(IndexTransactionsRequest $request): JsonResponse
    {
        $base = $this->filtered($request);

        return response()->json([
            'data' => [
                'transactions' => (clone $base)->count(),
                'total_collected' => (float) (clone $base)->sum('amount_paid'),
                'total_billed' => (float) (clone $base)->sum('total_amount'),
                'paid' => (clone $base)->where('status', 'paid')->count(),
                'pending' => (clone $base)->where('status', Payment::STATUS_PENDING)->count(),
                'terminals' => (clone $base)->distinct()->count('terminal_id'),
            ],
        ]);
    }

    /**
     * The shared query behind both the list and its totals, so a filtered
     * dashboard never shows figures that disagree with the rows beneath them.
     *
     * @return Builder<Payment>
     */
    private function filtered(IndexTransactionsRequest $request): Builder
    {
        $search = $request->string('search')->toString();

        return Payment::query()
            ->when($request->string('terminal_id')->isNotEmpty(), fn (Builder $q) => $q->where('terminal_id', $request->string('terminal_id')->toString()))
            ->when($request->string('status')->isNotEmpty(), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->string('ipn')->isNotEmpty(), fn (Builder $q) => $q->where('ipn', $request->string('ipn')->toString()))
            ->when($request->date('from') !== null, fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->date('to') !== null, fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('ipn', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
            ));
    }
}
