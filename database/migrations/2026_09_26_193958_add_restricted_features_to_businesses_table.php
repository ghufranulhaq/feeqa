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
        // FR-006-14 step 4: which of `RestrictableFeature`'s cases
        // (flagging, invitations, profile edits) are currently switched
        // off for this Business. Same early-add reasoning as `blocked_at`
        // above — T5's `RestrictBusinessFeature` needs it now, not T6.
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('restricted_features')->nullable()->after('plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('restricted_features');
        });
    }
};
