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
        // FR-002-19, FR-002-20: one row per version of a category's
        // question set. The highest `version` for a category is the
        // current one; earlier versions are kept only so an existing
        // review's stored answers can cite the version they were
        // answered under (spec 003) — nothing here ever gets rewritten.
        Schema::create('category_question_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['category_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_question_sets');
    }
};
