<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();

            // The merchant this payment belongs to.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('ipn', 50);
            $table->string('terminal_id', 50);
            $table->string('status', 50)->default('pending');

            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->text('description')->nullable();

            // Payer details, as returned by OIRS at validation time.
            $table->string('customer_id', 50)->nullable();
            $table->string('customer_ipn', 50)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();

            // Filled in when the terminal reports the payment.
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // One row per IPN per merchant: re-validating the same bill updates
            // the existing record rather than creating a duplicate.
            $table->unique(['user_id', 'ipn']);
            $table->index('ipn');
            $table->index('terminal_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
