<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Terminals\ImportTerminalsRequest;
use App\Services\Terminals\SheetReader;
use App\Services\Terminals\TerminalImportException;
use App\Services\Terminals\TerminalImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

final class TerminalImportController extends Controller
{
    public function __construct(
        private readonly SheetReader $reader,
        private readonly TerminalImporter $importer,
    ) {}

    #[OA\Post(
        path: '/api/v1/terminals/import',
        operationId: 'importTerminals',
        summary: 'Bulk create terminal logins',
        description: <<<'TEXT'
        Upload a spreadsheet of terminals and create one login per terminal ID.

        The sheet needs a header row with a "Terminal ID" column; "Serial Number" is
        used for the account name when present, and any other columns are ignored.
        Header matching ignores case, spaces, underscores and hyphens.

        Each new account gets an email derived from its terminal ID
        (204401PG becomes 204401pg@<email_domain>) and a randomly generated
        12 character password.

        The generated passwords are returned in this response and nowhere else:
        they are hashed on the way into the database and cannot be read back
        afterwards. Capture them before closing the page.

        Terminals that already have a login are skipped and listed, so re-uploading
        the same file is safe and will not disturb working accounts.

        Authentication: Bearer token required.
        TEXT,
        tags: ['Terminals'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', description: 'The .xlsx, .xls or .csv file. Maximum 5MB.', type: 'string', format: 'binary'),
                        new OA\Property(property: 'email_domain', description: 'Domain for the generated addresses. Defaults to the configured terminals.email_domain.', type: 'string', example: 'ecgpos.local'),
                    ],
                    type: 'object',
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Import finished. Check the summary: individual rows can fail without failing the upload.', content: new OA\JsonContent(ref: '#/components/schemas/TerminalImportResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'The file is missing, the wrong type, too large, or has no Terminal ID column.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 500, description: 'Server error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(ImportTerminalsRequest $request): JsonResponse
    {
        $domain = $request->string('email_domain')->isNotEmpty()
            ? $request->string('email_domain')->toString()
            : (string) config('services.terminals.email_domain');

        try {
            $rows = $this->reader->rows($request->file('file'));
            $result = $this->importer->import($rows, $domain);
        } catch (TerminalImportException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['file' => [$exception->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $result->toArray()]);
    }
}
