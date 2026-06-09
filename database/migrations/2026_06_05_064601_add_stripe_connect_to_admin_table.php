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
        Schema::table('admins', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->unique()->after('email');

            $table->boolean('stripe_details_submitted')->default(false);
            $table->boolean('stripe_charges_enabled')->default(false);
            $table->boolean('stripe_payouts_enabled')->default(false);

            $table->timestamp('stripe_onboarded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'stripe_details_submitted',
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'stripe_onboarded_at',
            ]);
        });
    }
};
