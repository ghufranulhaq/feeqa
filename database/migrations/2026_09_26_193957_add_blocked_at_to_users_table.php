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
        // FR-006-15: the reviewer ladder's final step. No existing
        // account-block field from 001, so T5's `BlockUserAccount` (built
        // to cover the account/business verbs the T6 ladder needs) adds
        // its own column now rather than waiting for T6's own migration.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('deletion_requested_at');
            $table->string('blocked_reason')->nullable()->after('blocked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
