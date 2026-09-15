<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('parent_name_ar')->nullable();
            $table->string('website')->nullable();
            $table->string('email_subject');
            $table->text('email_body');
            $table->text('whatsapp_message');
            $table->unsignedBigInteger('default_template_id')->nullable();
            $table->text('verification_footer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_settings');
    }
};
