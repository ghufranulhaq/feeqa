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
        Schema::table('users', function (Blueprint $table) {
            // FR-001-05: every account has a country, locale, and optional
            // avatar. Nullable here because passwordless and social sign-up
            // (T8, T9) create the account before those are collected; the
            // email+password form (T3) sets them at registration.
            $table->char('country', 2)->nullable()->after('email');
            $table->string('locale', 10)->default('en-GB')->after('country');
            $table->string('avatar_path')->nullable()->after('locale');

            // FR-001-03: 18+ confirmation, recorded at sign-up.
            $table->timestamp('date_of_birth_confirmed_at')->nullable()->after('avatar_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'locale',
                'avatar_path',
                'date_of_birth_confirmed_at',
            ]);
        });
    }
};
