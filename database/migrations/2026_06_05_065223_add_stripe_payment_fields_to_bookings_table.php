<?php

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
         Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('total_amount')->default(0)->after('event_id');
            $table->string('currency', 3)->default('inr')->after('total_amount');

            $table->string('payment_gateway')->nullable()->after('currency');
            $table->string('payment_status')->default('pending')->after('payment_gateway');

            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();

            $table->unsignedBigInteger('platform_fee_amount')->default(0);
            $table->unsignedBigInteger('vendor_amount')->default(0);

            $table->timestamp('paid_at')->nullable();
            $table->json('stripe_payload')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
             $table->dropColumn([
                'total_amount',
                'currency',
                'payment_gateway',
                'payment_status',
                'stripe_checkout_session_id',
                'stripe_payment_intent_id',
                'platform_fee_amount',
                'vendor_amount',
                'paid_at',
                'stripe_payload',
            ]);
        });
    }
};
