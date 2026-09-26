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
        // FR-003-01, FR-003-02: a service or location review. Product
        // reviews (deferred, FR-003-03) aren't modelled yet — adding them
        // later needs no change here, just a new nullable column.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->string('source_label');
            $table->unsignedTinyInteger('star_rating');
            $table->string('title');
            $table->text('text');
            $table->date('date_of_experience');
            $table->string('reference_number')->nullable();
            $table->string('language')->default('en');
            $table->unsignedInteger('question_set_version')->nullable();
            $table->json('answers')->nullable();
            $table->foreignId('tagged_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->boolean('confirmed_genuine')->default(false);
            $table->string('idempotency_key')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['reviewer_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
