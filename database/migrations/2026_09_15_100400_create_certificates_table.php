<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 16)->unique();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('templates')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('recipient_name');
            $table->string('recipient_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->json('data');
            $table->string('status', 20);
            $table->string('pdf_path')->nullable();
            $table->text('render_error')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->string('email_status', 20)->default('not_requested');
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->string('whatsapp_status', 20)->default('not_requested');
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->text('whatsapp_error')->nullable();
            $table->unsignedInteger('verified_count')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index('email');
            $table->index('recipient_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
