<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->restrictOnDelete();
            $table->string('name');
            $table->string('status', 30)->index();
            $table->json('deliver_via');
            $table->json('fixed_values');
            $table->json('column_map');
            $table->string('source_path')->nullable();
            $table->string('source_original_name')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('rendered_count')->default(0);
            $table->unsignedInteger('render_failed_count')->default(0);
            $table->unsignedInteger('email_sent_count')->default(0);
            $table->unsignedInteger('email_failed_count')->default(0);
            $table->unsignedInteger('whatsapp_sent_count')->default(0);
            $table->unsignedInteger('whatsapp_failed_count')->default(0);
            $table->string('zip_path')->nullable();
            $table->string('error_report_path')->nullable();
            $table->string('bus_batch_id')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
