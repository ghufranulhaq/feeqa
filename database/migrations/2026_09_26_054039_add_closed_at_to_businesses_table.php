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
        // Edge cases table: "Business closes permanently: ... New
        // reviews are blocked 12 months after the closure date."
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('claimed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
