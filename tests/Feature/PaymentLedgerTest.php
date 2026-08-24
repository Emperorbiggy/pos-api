<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class PaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    private const IPN = '331622459317';

    public function test_validating_a_bill_records_it_against_the_merchant_as_pending(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        $this->fakeOirs();
        $this->validateIpn($merchant)->assertOk();

        $payment = Payment::query()->sole();

        $this->assertSame($merchant->id, $payment->user_id);
        $this->assertSame(self::IPN, $payment->ipn);
        $this->assertSame('204401PG', $payment->terminal_id);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(12000.0, $payment->amount);
        $this->assertSame(12000.0, $payment->total_amount);
        $this->assertSame(0.0, $payment->amount_paid);
        $this->assertSame('Osun State Harmonised Bill', $payment->description);
        $this->assertSame('454368', $payment->customer_id);
        $this->assertSame('OLUJIDE JEREMIAH AMBEE', $payment->customer_name);
        $this->assertSame('07050710801', $payment->customer_phone);
        $this->assertSame('ZY1, BOLORUNDURO OKE IYANU, ILESA.', $payment->customer_address);
        $this->assertNull($payment->customer_email);
        $this->assertNull($payment->paid_at);
    }

    public function test_revalidating_the_same_bill_does_not_create_a_second_record(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        $this->fakeOirs();
        $this->validateIpn($merchant)->assertOk();
        $this->validateIpn($merchant)->assertOk();

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_payment_notification_moves_the_record_to_the_status_oirs_returns(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        $this->fakeOirs(notification: [
            'ipn' => self::IPN,
            'terminal_id' => '204401PG',
            'status' => 'paid',
            'amount_paid' => 12000,
            'reference' => 'TRX-556677',
        ]);

        $this->validateIpn($merchant)->assertOk();
        $this->assertSame('pending', Payment::query()->sole()->status);

        $this->notifyPayment($merchant)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $payment = Payment::query()->sole();

        $this->assertSame('paid', $payment->status);
        $this->assertSame(12000.0, $payment->amount_paid);
        $this->assertSame('TRX-556677', $payment->reference);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_a_part_payment_status_is_recorded_verbatim(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        $this->fakeOirs(notification: ['status' => 'part-payment', 'amount_paid' => 5000]);

        $this->validateIpn($merchant)->assertOk();
        $this->notifyPayment($merchant, 5000)->assertOk();

        $payment = Payment::query()->sole();

        $this->assertSame('part-payment', $payment->status);
        $this->assertSame(5000.0, $payment->amount_paid);
    }

    public function test_a_notification_without_a_status_keeps_the_previous_one(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        // The envelope's boolean "status" must not be mistaken for a payment state.
        $this->fakeOirs(notification: ['amount_paid' => 12000, 'reference' => 'TRX-1']);

        $this->validateIpn($merchant)->assertOk();
        $this->notifyPayment($merchant)->assertOk();

        $payment = Payment::query()->sole();

        $this->assertSame('pending', $payment->status);
        $this->assertSame('TRX-1', $payment->reference);
        $this->assertSame(12000.0, $payment->amount_paid);
    }

    public function test_each_merchant_keeps_its_own_ledger(): void
    {
        $first = User::factory()->create(['terminal_id' => '204401PG']);
        $second = User::factory()->create(['terminal_id' => '999999XX']);

        $this->fakeOirs(notification: ['status' => 'paid', 'amount_paid' => 12000]);

        $this->validateIpn($first)->assertOk();
        $this->validateIpn($second)->assertOk();

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(1, $first->payments()->count());
        $this->assertSame(1, $second->payments()->count());

        // One merchant's notification must not touch another's record.
        $this->notifyPayment($first)->assertOk();

        $this->assertSame('paid', $first->payments()->sole()->status);
        $this->assertSame('pending', $second->payments()->sole()->status);
    }

    public function test_a_failed_validation_records_nothing(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        Http::fake(['*' => Http::response([
            'status' => false,
            'message' => 'The ipn has already been paid for.',
        ], 400)]);

        $this->validateIpn($merchant)->assertStatus(400);

        $this->assertSame(0, Payment::query()->count());
    }

    /**
     * Stub both OIRS endpoints at once. Http::fake matches stubs in the order
     * they were registered, so a later fake() for a second endpoint would never
     * be reached — they have to be keyed by URL in one call.
     *
     * @param  array<string, mixed>|null  $notification
     */
    private function fakeOirs(?array $notification = null): void
    {
        Http::fake([
            '*payment-notification*' => Http::response([
                'status' => true,
                'message' => 'ok',
                'data' => $notification ?? [],
            ], 200),
            '*validate-ipn*' => Http::response([
                'status' => true,
                'message' => 'ok',
                'data' => [
                    'ipn' => self::IPN,
                    'terminal_id' => '204401PG',
                    'status' => 'pending',
                    'amount' => 12000,
                    'total_amount' => 12000,
                    'amount_paid' => 0,
                    'description' => 'Osun State Harmonised Bill',
                    'customer' => [
                        'id' => '454368',
                        'ipn' => null,
                        'name' => 'OLUJIDE JEREMIAH AMBEE',
                        'email' => null,
                        'phone' => '07050710801',
                        'address' => 'ZY1, BOLORUNDURO OKE IYANU, ILESA.',
                    ],
                ],
            ], 200),
        ]);
    }

    private function validateIpn(User $merchant): TestResponse
    {
        return $this->asMerchant($merchant)
            ->postJson('/api/v1/validate-ipn', [
                'ipn' => self::IPN,
                'terminal_id' => $merchant->terminal_id,
            ]);
    }

    private function notifyPayment(User $merchant, float $amount = 12000): TestResponse
    {
        return $this->asMerchant($merchant)
            ->postJson('/api/v1/payment-notification', [
                'ipn' => self::IPN,
                'amount_paid' => $amount,
                'terminal_id' => $merchant->terminal_id,
                'paid_at' => '2026-08-24 10:30:00',
            ]);
    }

    private function asMerchant(User $merchant): self
    {
        $token = JWTAuth::fromUser($merchant);

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
