<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();

            /*
             | test = sandbox / testing SMTP
             | live = production SMTP
             */
            $table->string('environment')->default('live')->index();

            /*
             | pending   = saved but not active
             | active    = currently used by the app
             | previous  = old working version, available for rollback
             | revoked   = discarded pending version
             | failed    = failed validation or rollback target
             */
            $table->string('status')->default('pending')->index();

            $table->string('mail_mailer')->default('smtp');
            $table->string('mail_scheme')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedInteger('mail_port')->nullable();
            $table->string('mail_username')->nullable();

            // Encrypted value becomes longer, so use TEXT.
            $table->text('mail_password')->nullable();

            // Safe fingerprint for display/audit, never the real password.
            $table->string('secret_fingerprint')->nullable();

            $table->string('mail_from_address');
            $table->string('mail_from_name');

            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['environment', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};