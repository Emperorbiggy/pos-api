<?php

declare(strict_types=1);

namespace App\Services\OIRS;

use App\Services\OIRS\Contracts\OIRSServiceInterface;
use App\Services\OIRS\DTOs\CustomerData;
use App\Services\OIRS\DTOs\InvoiceData;
use App\Services\OIRS\DTOs\PaymentNotificationData;
use App\Services\OIRS\DTOs\PaymentValidationData;
use App\Services\OIRS\Exceptions\OIRSException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class OIRSService implements OIRSServiceInterface
{
    private const VALIDATE_IPN_ENDPOINT = 'validate-ipn';

    private const PAYMENT_NOTIFICATION_ENDPOINT = 'payment-notification';

    private const GENERATE_INVOICE_ENDPOINT = 'invoices';

    /**
     * Create a new OIRS Terminal API service instance.
     */
    public function __construct(
        protected Factory $http,
    ) {}

    /**
     * Validate an IPN against the OIRS Terminal API.
     *
     * @throws OIRSException
     */
    public function validateIpn(string $ipn, string $terminalId): PaymentValidationData
    {
        $ipn = $this->validateRequiredString($ipn, 'ipn');
        $terminalId = $this->validateRequiredString($terminalId, 'terminalId');

        $query = [
            'ipn' => $ipn,
            'terminal_id' => $terminalId,
        ];

        $data = $this->get(self::VALIDATE_IPN_ENDPOINT, $query, $this->terminalBaseUrl());

        Log::info('OIRS IPN validation response', [
            'ipn' => $ipn,
            'terminal_id' => $terminalId,
            'response' => $data,
        ]);

        return $this->paymentValidationData($data, $ipn, $terminalId);
    }

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
    ): PaymentNotificationData {
        $ipn = $this->validateRequiredString($ipn, 'ipn');
        $terminalId = $this->validateRequiredString($terminalId, 'terminalId');
        $this->validatePositiveAmount($amountPaid);

        $immutablePaidAt = $paidAt->toImmutable();

        $payload = [
            'ipn' => $ipn,
            'amount_paid' => $amountPaid,
            'terminal_id' => $terminalId,
            'paid_at' => $immutablePaidAt->format('Y-m-d H:i:s'),
        ];

        $data = $this->post(self::PAYMENT_NOTIFICATION_ENDPOINT, $payload, $this->terminalBaseUrl());

        Log::info('OIRS Payment Notification response', [
            'ipn' => $ipn,
            'terminal_id' => $terminalId,
            'amount_paid' => $amountPaid,
            'paid_at' => $immutablePaidAt->toDateTimeString(),
            'response' => $data,
        ]);

        return $this->paymentNotificationData($data, $ipn, $amountPaid, $terminalId, $immutablePaidAt);
    }

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
    ): InvoiceData {
        $revenueCode = $this->validateRequiredString($revenueCode, 'revenueCode');
        $agencyCode = $this->validateRequiredString($agencyCode, 'agencyCode');
        $pid = $this->validateRequiredString($pid, 'pid');
        $paymentType = $this->validateRequiredString($paymentType, 'paymentType');
        $this->validatePositiveAmount($amount);

        $payload = [
            'revenue_code' => $revenueCode,
            'agency_code' => $agencyCode,
            'amount' => $amount,
            'pid' => $pid,
            'payment_type' => $paymentType,
        ];

        $data = $this->post(self::GENERATE_INVOICE_ENDPOINT, $payload);

        return $this->invoiceData($data);
    }

    /**
     * Make a GET request to the OIRS API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws OIRSException
     */
    private function get(string $endpoint, array $query = [], ?string $baseUrl = null): array
    {
        try {
            $response = $this->request($baseUrl)->get($endpoint, $query);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $message = $this->responseMessage($response) ?? 'OIRS returned an unsuccessful HTTP response.';

            $this->logFailure('OIRS HTTP request failed.', [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'query' => $query,
                'response' => $this->safeResponseBody($response),
            ]);

            throw new OIRSException($message, $response->status(), context: [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
            ]);
        } catch (ConnectionException $exception) {
            $this->logFailure('OIRS network request failed.', [
                'endpoint' => $endpoint,
                'query' => $query,
                'exception' => $exception->getMessage(),
            ]);

            throw new OIRSException(
                'Unable to connect to the OIRS service.',
                previous: $exception,
                context: ['endpoint' => $endpoint]
            );
        }

        if ($response->failed()) {
            $message = $this->responseMessage($response) ?? 'OIRS returned an unsuccessful HTTP response.';

            $this->logFailure('OIRS HTTP request failed.', [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'query' => $query,
                'response' => $this->safeResponseBody($response),
            ]);

            throw new OIRSException($message, $response->status(), context: [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
            ]);
        }

        $body = $response->json();

        if (! is_array($body)) {
            $this->logFailure('OIRS returned an invalid JSON response.', [
                'endpoint' => $endpoint,
                'query' => $query,
                'response' => $response->body(),
            ]);

            throw new OIRSException(
                'OIRS returned an invalid response.',
                context: ['endpoint' => $endpoint]
            );
        }

        if (($body['status'] ?? false) !== true) {
            $message = $this->arrayString($body, 'message') ?? 'OIRS request failed.';

            $this->logFailure('OIRS API request failed.', [
                'endpoint' => $endpoint,
                'query' => $query,
                'response' => $body,
            ]);

            throw new OIRSException(
                $message,
                context: ['endpoint' => $endpoint]
            );
        }

        $data = $body['data'] ?? [];

        if (! is_array($data)) {
            $this->logFailure('OIRS returned invalid data payload.', [
                'endpoint' => $endpoint,
                'query' => $query,
                'response' => $body,
            ]);

            throw new OIRSException(
                'OIRS returned an invalid data payload.',
                context: ['endpoint' => $endpoint]
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws OIRSException
     */
    private function post(string $endpoint, array $payload, ?string $baseUrl = null): array
    {
        try {
            $response = $this->request($baseUrl)->post($endpoint, $payload);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $message = $this->responseMessage($response) ?? 'OIRS returned an unsuccessful HTTP response.';

            $this->logFailure('OIRS HTTP request failed.', [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'payload' => $payload,
                'response' => $this->safeResponseBody($response),
            ]);

            throw new OIRSException($message, $response->status(), context: [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
            ]);
        } catch (ConnectionException $exception) {
            $this->logFailure('OIRS network request failed.', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'exception' => $exception->getMessage(),
            ]);

            throw new OIRSException(
                'Unable to connect to the OIRS service.',
                previous: $exception,
                context: ['endpoint' => $endpoint]
            );
        }

        if ($response->failed()) {
            $message = $this->responseMessage($response) ?? 'OIRS returned an unsuccessful HTTP response.';

            $this->logFailure('OIRS HTTP request failed.', [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'payload' => $payload,
                'response' => $this->safeResponseBody($response),
            ]);

            throw new OIRSException($message, $response->status(), context: [
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
            ]);
        }

        $body = $response->json();

        if (! is_array($body)) {
            $this->logFailure('OIRS returned an invalid JSON response.', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'response' => $response->body(),
            ]);

            throw new OIRSException(
                'OIRS returned an invalid response.',
                context: ['endpoint' => $endpoint]
            );
        }

        if (($body['status'] ?? false) !== true) {
            $message = $this->arrayString($body, 'message') ?? 'OIRS request failed.';

            $this->logFailure('OIRS API request failed.', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'response' => $body,
            ]);

            throw new OIRSException(
                $message,
                context: ['endpoint' => $endpoint]
            );
        }

        $data = $body['data'] ?? [];

        if (! is_array($data)) {
            $this->logFailure('OIRS returned invalid data payload.', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'response' => $body,
            ]);

            throw new OIRSException(
                'OIRS returned an invalid data payload.',
                context: ['endpoint' => $endpoint]
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paymentValidationData(array $data, string $ipn, string $terminalId): PaymentValidationData
    {
        // Handle nested 'data' wrapper in the response
        $payload = $data['data'] ?? $data;

        $customerPayload = $payload['customer'] ?? $payload['payer'] ?? $payload;

        return new PaymentValidationData(
            ipn: $this->stringValue($payload['ipn'] ?? $data['ipn'] ?? $ipn),
            terminalId: $this->stringValue($payload['terminal_id'] ?? $payload['terminalId'] ?? $data['terminal_id'] ?? $data['terminalId'] ?? $terminalId),
            customer: is_array($customerPayload) ? $this->customerData($customerPayload) : null,
            status: $this->nullableString($payload['status'] ?? null),
            amount: $this->floatValue($payload['amount'] ?? 0),
            totalAmount: $this->floatValue($payload['total_amount'] ?? $payload['totalAmount'] ?? 0),
            amountPaid: $this->floatValue($payload['amount_paid'] ?? $payload['amountPaid'] ?? 0),
            description: $this->nullableString($payload['description'] ?? null),
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function customerData(array $data): CustomerData
    {
        return new CustomerData(
            id: $this->nullableString($data['id'] ?? $data['customer_id'] ?? $data['payer_id'] ?? $data['pid'] ?? null),
            ipn: $this->nullableString($data['ipn'] ?? null),
            name: $this->nullableString($data['name'] ?? $data['customer_name'] ?? $data['payer_name'] ?? null),
            email: $this->nullableString($data['email'] ?? null),
            phone: $this->nullableString($data['phone'] ?? $data['phone_number'] ?? $data['mobile'] ?? null),
            address: $this->nullableString($data['address'] ?? null),
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paymentNotificationData(
        array $data,
        string $ipn,
        float $amountPaid,
        string $terminalId,
        CarbonImmutable $paidAt
    ): PaymentNotificationData {
        // Some OIRS endpoints nest the payload one level deeper.
        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        return new PaymentNotificationData(
            ipn: $this->stringValue($payload['ipn'] ?? $data['ipn'] ?? $ipn),
            amountPaid: $this->floatValue($payload['amount_paid'] ?? $payload['amountPaid'] ?? $amountPaid),
            terminalId: $this->stringValue($payload['terminal_id'] ?? $payload['terminalId'] ?? $terminalId),
            paidAt: $paidAt,
            reference: $this->nullableString(
                $payload['reference']
                    ?? $payload['payment_reference']
                    ?? $payload['transaction_reference']
                    ?? null
            ),
            // Only a string status is meaningful here. The envelope's own
            // "status" is a boolean success flag, not the payment's state.
            status: is_string($payload['status'] ?? null)
                ? $this->nullableString($payload['status'])
                : null,
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function invoiceData(array $data): InvoiceData
    {
        return new InvoiceData(
            ipn: $this->stringValue($data['ipn'] ?? ''),
            authorizationUrl: $this->stringValue($data['authorization_url'] ?? $data['authorizationUrl'] ?? ''),
            raw: $data,
        );
    }

    private function request(?string $baseUrl = null): PendingRequest
    {
        return $this->http
            ->baseUrl($baseUrl ?? $this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            // throw: false so a failed response comes back to us instead of
            // escaping as an unhandled RequestException; the $response->failed()
            // branches below turn it into an OIRSException carrying the upstream
            // message and status.
            ->retry(2, 500, fn (Throwable $exception): bool => $this->shouldRetry($exception), throw: false)
            ->withHeaders([
                'X-APP-KEY' => $this->appKey(),
            ]);
    }

    /**
     * Only transient failures are worth retrying. A 4xx is OIRS stating a
     * business fact ("already paid for"), and repeating the call cannot change
     * it: it just delays the response and, on payment-notification, risks
     * sending the same notification more than once.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response->serverError();
    }

    private function baseUrl(): string
    {
        $baseUrl = config('services.oirs.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException('OIRS base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    private function terminalBaseUrl(): string
    {
        $baseUrl = config('services.oirs.terminal_base_url') ?: config('services.oirs.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException('OIRS terminal base URL is not configured.');
        }

        return rtrim($baseUrl, '/');
    }

    private function appKey(): string
    {
        $appKey = config('services.oirs.key');

        if (! is_string($appKey) || trim($appKey) === '') {
            throw new InvalidArgumentException('OIRS app key is not configured.');
        }

        return $appKey;
    }

    private function validateRequiredString(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(sprintf('The %s field is required.', $field));
        }

        return $value;
    }

    private function validatePositiveAmount(float $amountPaid): void
    {
        if ($amountPaid <= 0) {
            throw new InvalidArgumentException('The amountPaid field must be greater than zero.');
        }
    }

    private function responseMessage(Response $response): ?string
    {
        $json = $response->json();

        if (! is_array($json)) {
            return null;
        }

        return $this->arrayString($json, 'message');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function arrayString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $this->nullableString($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function stringValue(mixed $value): string
    {
        return (string) $value;
    }

    private function floatValue(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function safeResponseBody(Response $response): array|string
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return $response->body();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $message, array $context): void
    {
        unset($context['headers']['X-APP-KEY'], $context['headers']['x-app-key']);

        Log::error($message, $context);
    }
}
