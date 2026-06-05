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
         Schema::table('events', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('admins')->nullOnDelete();
            $table->unsignedBigInteger('ticket_price')->default(0)->after('event_date');
            $table->string('currency', 3)->default('inr')->after('ticket_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
             $table->dropConstrainedForeignId('admin_id');
            $table->dropColumn(['ticket_price', 'currency']);
        });
    }
};
