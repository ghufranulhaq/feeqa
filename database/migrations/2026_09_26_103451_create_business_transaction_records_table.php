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
        // FR-004-12, FR-004-13: hashed transaction records a business
        // supplies for reference matching. Plaintext references/emails
        // are never stored — only their HMAC hashes reach this table.
        // Upserted by reference hash (edge case table), so it is unique
        // per business.
        Schema::create('business_transaction_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference_hash');
            $table->string('email_hash');
            $table->date('transaction_date');
            $table->json('skus')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'reference_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_transaction_records');
    }
};
