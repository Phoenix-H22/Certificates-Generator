<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1 leftovers: the single-row settings table that pointed at a .docx,
     * the newsletter sign-ups from the old landing page, and the pre-L10
     * password_resets / sanctum tables that the new skeleton replaces.
     */
    public function up(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('subscriped_users');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('personal_access_tokens');
    }

    public function down(): void
    {
        // Intentionally irreversible: the v1 tables carry no data worth restoring.
    }
};
