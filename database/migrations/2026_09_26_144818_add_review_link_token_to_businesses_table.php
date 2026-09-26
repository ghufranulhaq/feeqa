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
        // FR-005-04: the `link` method's own stable, unique identifier. It
        // needs no `review_invitations` row per use — one token per
        // Business, generated once (booted() hook) and never rotated,
        // unlike `bcc_address` (a public QR code is meant to keep working
        // for as long as it's printed).
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('review_link_token')->nullable()->unique()->after('bcc_no_reference_match_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('review_link_token');
        });
    }
};
