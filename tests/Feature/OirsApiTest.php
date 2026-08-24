<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class OirsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_business_rejection_from_oirs_is_relayed_to_the_caller(): void
    {
        Http::fake(['*' => Http::response([
            'status' => false,
            'message' => 'The ipn has already been paid for.',
            'data' => [],
        ], 400)]);

        $this->validateIpn()
            ->assertStatus(400)
            ->assertExactJson(['message' => 'The ipn has already been paid for.']);
    }

    public function test_a_business_rejection_is_not_retried(): void
    {
        Http::fake(['*' => Http::response([
            'status' => false,
            'message' => 'The ipn has already been paid for.',
        ], 400)]);

        $this->validateIpn()->assertStatus(400);

        // Repeating the call cannot change OIRS's answer, so it must go out once.
        Http::assertSentCount(1);
    }

    public function test_an_oirs_rejection_of_our_own_credentials_is_not_blamed_on_the_caller(): void
    {
        Http::fake(['*' => Http::response([
            'status' => false,
            'message' => 'Invalid app key.',
        ], 401)]);

        // A relayed 401 would look to a POS like an expired JWT and log the
        // cashier out, when the real fault is our app key.
        $this->validateIpn()->assertStatus(502);
    }

    public function test_an_oirs_outage_is_reported_as_a_gateway_error(): void
    {
        Http::fake(['*' => Http::response(['status' => false, 'message' => 'boom'], 500)]);

        $this->validateIpn()->assertStatus(502);
    }

    public function test_a_network_failure_is_reported_as_a_gateway_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->validateIpn()
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Unable to connect to the OIRS service.']);
    }

    public function test_no_error_response_leaks_a_stack_trace(): void
    {
        Http::fake(['*' => Http::response([
            'status' => false,
            'message' => 'The ipn has already been paid for.',
        ], 400)]);

        $body = $this->validateIpn()->getContent();

        $this->assertStringNotContainsString('exception', $body);
        $this->assertStringNotContainsString('trace', $body);
        $this->assertStringNotContainsString('vendor', $body);
    }

    public function test_a_successful_validation_returns_the_payer(): void
    {
        Http::fake(['*' => Http::response([
            'status' => true,
            'message' => 'ok',
            'data' => [
                'ipn' => '883060198890',
                'terminal_id' => '204401PG',
                'customer' => ['id' => '79453121', 'name' => 'John Doe'],
            ],
        ], 200)]);

        $this->validateIpn()
            ->assertOk()
            ->assertJsonPath('data.ipn', '883060198890')
            ->assertJsonPath('data.customer.name', 'John Doe');
    }

    /**
     * Call validate-ipn as an authenticated terminal.
     */
    private function validateIpn(): TestResponse
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/validate-ipn', [
                'ipn' => '883060198890',
                'terminal_id' => '204401PG',
            ]);
    }
}
