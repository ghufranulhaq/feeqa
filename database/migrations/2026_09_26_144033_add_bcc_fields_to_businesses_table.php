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
        // FR-005-06, FR-005-07: the BCC method's own per-Business settings.
        // `bcc_address` is generated for every Business (booted() hook) so
        // it always exists without a separate "activate BCC" step;
        // `RotateBusinessBccAddress` is what makes it rotatable.
        // `bcc_registered_senders` holds exact From: addresses the alignment
        // check accepts alongside the Business's own verified domains.
        // `bcc_reference_pattern` overrides the platform default regex used
        // to find a transaction reference in the subject/body.
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('bcc_address')->nullable()->unique()->after('additional_domains');
            $table->json('bcc_registered_senders')->nullable()->after('bcc_address');
            $table->string('bcc_reference_pattern')->nullable()->after('bcc_registered_senders');
            $table->unsignedInteger('bcc_failed_alignment_count')->default(0)->after('bcc_reference_pattern');
            $table->unsignedInteger('bcc_no_reference_match_count')->default(0)->after('bcc_failed_alignment_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'bcc_address',
                'bcc_registered_senders',
                'bcc_reference_pattern',
                'bcc_failed_alignment_count',
                'bcc_no_reference_match_count',
            ]);
        });
    }
};
