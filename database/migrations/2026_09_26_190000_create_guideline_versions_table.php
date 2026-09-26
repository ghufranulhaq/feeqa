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
        // FR-006-01: each version is dated and old versions stay
        // available — nothing here is ever deleted, only superseded.
        Schema::create('guideline_versions', function (Blueprint $table) {
            $table->id();
            $table->string('audience');
            $table->unsignedInteger('version');
            $table->text('body');
            $table->timestamp('published_at');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['audience', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guideline_versions');
    }
};
