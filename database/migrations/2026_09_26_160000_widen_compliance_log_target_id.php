<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FR-004-17: the first UUID-keyed model (VerificationAttestation)
     * needing a compliance log entry. `target_id` was `unsignedBigInteger`
     * because every earlier target had a bigint primary key — widen it to
     * text so it can hold either, without a new dependency (no
     * doctrine/dbal) via a raw, Postgres-specific ALTER.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE compliance_log ALTER COLUMN target_id TYPE varchar(255) USING target_id::varchar');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE compliance_log ALTER COLUMN target_id TYPE bigint USING target_id::bigint');
    }
};
