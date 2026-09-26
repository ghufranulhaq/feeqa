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
        // FR-005-20: 017 (Plans & Billing) doesn't exist yet, so this is
        // the minimal placeholder spec 005's own invitation limit needs —
        // App\Domain\Businesses\BusinessPlan. Every Business starts Free.
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('plan')->default('free')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
