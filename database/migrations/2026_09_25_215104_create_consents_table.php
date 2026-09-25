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
        // FR-001-21: append-only — a new row each time consent is given,
        // never updated in place, so the platform can always show what a
        // user agreed to and when.
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version');
            $table->string('privacy_version');
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->index(['user_id', 'consented_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
