<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class InvoiceLookupTest extends TestCase
{
    use RefreshDatabase;

    private const IPN = '426878163229';

    public function test_an_invoice_is_returned_for_an_ipn(): void
    {
        $this->fakeInvoice([
            'ipn' => self::IPN,
            'status' => 'pending',
            'amount' => 12000,
            'total_amount' => 12000,
            'amount_paid' => 0,
            'description' => 'Osun State Harmonised Bill',
            'revenue_code' => '4020154',
            'agency_code' => '4100000',
            'payment_type' => 'individual',
            'customer' => [
                'id' => '454368',
                'name' => 'OLUJIDE JEREMIAH AMBEE',
                'phone' => '07050710801',
                'address' => 'ZY1, BOLORUNDURO OKE IYANU, ILESA.',
            ],
        ]);

        $this->fetchInvoice()
            ->assertOk()
            ->assertJsonPath('data.ipn', self::IPN)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 12000)
            ->assertJsonPath('data.total_amount', 12000)
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.description', 'Osun State Harmonised Bill')
            ->assertJsonPath('data.revenue_code', '4020154')
            ->assertJsonPath('data.agency_code', '4100000')
            ->assertJsonPath('data.payment_type', 'individual')
            ->assertJsonPath('data.customer.name', 'OLUJIDE JEREMIAH AMBEE')
            ->assertJsonPath('data.customer.phone', '07050710801');
    }

    public function test_it_calls_the_oirs_base_url_not_the_terminal_one(): void
    {
        $this->fakeInvoice(['ipn' => self::IPN]);

        $this->fetchInvoice()->assertOk();

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request->url() === rtrim((string) config('services.oirs.base_url'), '/').'/invoices/'.self::IPN
                // The service sends X-APP-KEY; HTTP header names are
                // case insensitive on the wire, so curl's lowercase form is the
                // same header.
                && $request->hasHeader('X-APP-KEY');
        });
    }

    public function test_a_nested_data_wrapper_is_unwrapped(): void
    {
        $this->fakeInvoice(['data' => ['ipn' => self::IPN, 'status' => 'paid', 'amount' => 500]]);

        $this->fetchInvoice()
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount', 500);
    }

    public function test_an_invoice_with_no_customer_returns_null(): void
    {
        $this->fakeInvoice(['ipn' => self::IPN, 'status' => 'pending']);

        $this->fetchInvoice()
            ->assertOk()
            ->assertJsonPath('data.customer', null);
    }

    public function test_an_unknown_ipn_relays_the_oirs_message(): void
    {
        Http::fake(['*' => Http::response([
            'status' => false,
            'message' => 'Invoice not found.',
        ], 404)]);

        $this->fetchInvoice()
            ->assertNotFound()
            ->assertExactJson(['message' => 'Invoice not found.']);
    }

    public function test_an_oirs_failure_is_reported_as_a_gateway_error(): void
    {
        Http::fake(['*' => Http::response(['status' => false, 'message' => 'boom'], 500)]);

        $this->fetchInvoice()->assertStatus(502);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/invoices/'.self::IPN)->assertUnauthorized();
    }

    public function test_a_malformed_ipn_does_not_reach_oirs(): void
    {
        Http::fake();

        $this->asMerchant()
            ->getJson('/api/v1/invoices/'.urlencode('../../etc/passwd'))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fakeInvoice(array $data): void
    {
        Http::fake(['*' => Http::response([
            'status' => true,
            'message' => 'ok',
            'data' => $data,
        ], 200)]);
    }

    private function fetchInvoice(): TestResponse
    {
        return $this->asMerchant()->getJson('/api/v1/invoices/'.self::IPN);
    }

    private function asMerchant(): self
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
