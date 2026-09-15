<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('design', 40);
            $table->json('fields_schema');
            $table->json('layout_config');
            $table->boolean('is_active')->default(true);
            $table->string('preview_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->foreign('default_template_id')->references('id')->on('templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->dropForeign(['default_template_id']);
        });

        Schema::dropIfExists('templates');
    }
};
