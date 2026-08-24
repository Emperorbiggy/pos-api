<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_merchant_sees_their_payments_newest_first(): void
    {
        $merchant = User::factory()->create();
        $oldest = Payment::factory()->for($merchant)->create(['ipn' => '111111111111']);
        $newest = Payment::factory()->for($merchant)->create(['ipn' => '222222222222']);

        $response = $this->listPayments($merchant)->assertOk();

        $response
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_payload_carries_the_full_record(): void
    {
        $merchant = User::factory()->create();
        Payment::factory()->for($merchant)->create([
            'ipn' => '331622459317',
            'terminal_id' => '204401PG',
            'status' => 'pending',
            'amount' => 12000,
            'total_amount' => 12000,
            'amount_paid' => 0,
            'description' => 'Osun State Harmonised Bill',
            'customer_id' => '454368',
            'customer_name' => 'OLUJIDE JEREMIAH AMBEE',
            'customer_phone' => '07050710801',
            'customer_address' => 'ZY1, BOLORUNDURO OKE IYANU, ILESA.',
        ]);

        $this->listPayments($merchant)
            ->assertOk()
            ->assertJsonPath('data.0.ipn', '331622459317')
            ->assertJsonPath('data.0.terminal_id', '204401PG')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.amount', 12000)
            ->assertJsonPath('data.0.total_amount', 12000)
            ->assertJsonPath('data.0.amount_paid', 0)
            ->assertJsonPath('data.0.description', 'Osun State Harmonised Bill')
            ->assertJsonPath('data.0.paid_at', null)
            ->assertJsonPath('data.0.customer.id', '454368')
            ->assertJsonPath('data.0.customer.name', 'OLUJIDE JEREMIAH AMBEE')
            ->assertJsonPath('data.0.customer.phone', '07050710801')
            ->assertJsonPath('data.0.customer.address', 'ZY1, BOLORUNDURO OKE IYANU, ILESA.');
    }

    public function test_a_merchant_never_sees_another_merchants_payments(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();

        Payment::factory()->for($merchant)->create();
        Payment::factory()->count(3)->for($other)->create();

        $this->listPayments($merchant)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_payments_can_be_filtered_by_status(): void
    {
        $merchant = User::factory()->create();
        Payment::factory()->count(2)->for($merchant)->create();
        Payment::factory()->for($merchant)->paid()->create();

        $this->listPayments($merchant, ['status' => 'paid'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');

        $this->listPayments($merchant, ['status' => 'pending'])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_payments_can_be_filtered_by_terminal_and_ipn(): void
    {
        $merchant = User::factory()->create();
        Payment::factory()->for($merchant)->create(['terminal_id' => '204401PG', 'ipn' => '999999999999']);
        Payment::factory()->for($merchant)->create(['terminal_id' => 'OTHER01', 'ipn' => '888888888888']);

        $this->listPayments($merchant, ['terminal_id' => '204401PG'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.terminal_id', '204401PG');

        $this->listPayments($merchant, ['ipn' => '888888888888'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ipn', '888888888888');
    }

    public function test_results_are_paginated(): void
    {
        $merchant = User::factory()->create();
        Payment::factory()->count(30)->for($merchant)->create();

        $this->listPayments($merchant)
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.last_page', 2);

        $this->listPayments($merchant, ['per_page' => 10, 'page' => 3])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_an_out_of_range_page_size_is_rejected(): void
    {
        $merchant = User::factory()->create();

        $this->listPayments($merchant, ['per_page' => 500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_a_merchant_with_no_payments_gets_an_empty_list(): void
    {
        $merchant = User::factory()->create();

        $this->listPayments($merchant)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function listPayments(User $merchant, array $query = []): TestResponse
    {
        $token = JWTAuth::fromUser($merchant);

        $this->isolateNextRequest();

        $url = '/api/v1/payments'.($query === [] ? '' : '?'.http_build_query($query));

        return $this->withHeader('Authorization', 'Bearer '.$token)->getJson($url);
    }
}
