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
        // FR-006-16: the Consumer Warning banner's own two facts.
        // `Business::trustSignalsHidden()` reads `consumer_warning_at`
        // rather than the pre-existing `status` column — a Consumer
        // Warning is a distinct concept from `BusinessStatus::Suspended`
        // (unused elsewhere today), not that status under another name.
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('consumer_warning_at')->nullable()->after('restricted_features');
            $table->string('consumer_warning_reason')->nullable()->after('consumer_warning_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['consumer_warning_at', 'consumer_warning_reason']);
        });
    }
};
