<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTerminalsRequest;
use App\Http\Requests\Admin\UpdateTerminalRequest;
use App\Http\Resources\TerminalResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

final class TerminalController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    #[OA\Get(
        path: '/api/v1/admin/terminals',
        operationId: 'adminListTerminals',
        summary: 'List all terminals',
        description: 'Every registered terminal with its transaction count and total collected. Admin only. Optional search matches name, email or terminal id.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 100)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1, default: 25)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Terminals listed.'),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not an administrator.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(IndexTerminalsRequest $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->toString();

        $terminals = User::query()
            ->withCount('payments')
            ->withSum('payments', 'amount_paid')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('terminal_id', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page') ?: self::DEFAULT_PER_PAGE)
            ->withQueryString();

        return TerminalResource::collection($terminals);
    }

    #[OA\Get(
        path: '/api/v1/admin/terminals/{user}',
        operationId: 'adminShowTerminal',
        summary: 'Show one terminal',
        description: 'A single terminal record. Admin only.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Terminal found.'),
            new OA\Response(response: 403, description: 'Not an administrator.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(User $user): TerminalResource
    {
        return new TerminalResource($user);
    }

    #[OA\Patch(
        path: '/api/v1/admin/terminals/{user}',
        operationId: 'adminUpdateTerminal',
        summary: 'Reset terminal details',
        description: 'Update a terminal\'s name, email, terminal id, password or PIN. Admin only. Every field is optional; send only what should change. A password reset here does not require the old password, since the point is recovering a terminal whose holder cannot supply it. Password and PIN are stored hashed and never returned.',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Ilesa Main Terminal'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ilesa@example.com'),
            new OA\Property(property: 'terminal_id', type: 'string', maxLength: 50, example: '204401PG'),
            new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'newsecret123'),
            new OA\Property(property: 'password_confirmation', type: 'string', example: 'newsecret123'),
            new OA\Property(property: 'pin', type: 'string', example: '1234'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Terminal updated.'),
            new OA\Response(response: 403, description: 'Not an administrator.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(UpdateTerminalRequest $request, User $user): TerminalResource
    {
        // The model's hashed casts turn password and pin into hashes on save.
        $user->fill($request->safe()->only(['name', 'email', 'terminal_id', 'password', 'pin']));
        $user->save();

        return new TerminalResource($user->refresh());
    }
}
