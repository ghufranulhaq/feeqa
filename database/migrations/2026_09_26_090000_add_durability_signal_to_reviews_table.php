<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-003-22: null until the first lifecycle update is published —
        // there's nothing to compare the current rating against before then.
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('durability_signal')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('durability_signal');
        });
    }
};
