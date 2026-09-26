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
        // FR-002-19: 0-8 attributes per question set.
        Schema::create('category_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_question_set_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('label');
            $table->string('type');
            $table->boolean('required')->default(false);
            // single_choice's option labels, e.g. {"en-GB": ["Economy", "Business"]}.
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['category_question_set_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_questions');
    }
};
