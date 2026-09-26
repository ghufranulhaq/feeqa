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
        // FR-006-05, FR-006-20: an opt-out table — a rule with no row here
        // is enabled. Only a rule the weekly audit (T8) has disabled for
        // falling under 99% precision gets a row.
        Schema::create('screening_rule_states', function (Blueprint $table) {
            $table->string('rule_id')->primary();
            $table->boolean('enabled')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_rule_states');
    }
};
