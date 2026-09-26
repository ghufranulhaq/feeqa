<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // FR-002-08: the identifying detail for a business with no
            // website (alongside `name` and `country`); also FR-002-09's
            // fuzzy duplicate match key.
            $table->string('city')->nullable()->after('country');
            // FR-002-10.
            $table->timestamp('listing_checked_at')->nullable()->after('import_batch');
            $table->string('listing_check_notes')->nullable()->after('listing_checked_at');
        });

        // Trigram GIN indexes for the FR-002-09 fuzzy name(+city) match —
        // Eloquent's schema builder has no trigram opclass support.
        DB::statement('CREATE INDEX businesses_name_trgm_idx ON businesses USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX businesses_city_trgm_idx ON businesses USING gin (city gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS businesses_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS businesses_city_trgm_idx');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['city', 'listing_checked_at', 'listing_check_notes']);
        });
    }
};
