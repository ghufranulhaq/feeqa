<?php

namespace Database\Factories;

use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessTransactionRecord>
 */
class BusinessTransactionRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reference_hash' => TransactionRecordHash::reference($this->faker->bothify('REF-########')),
            'email_hash' => TransactionRecordHash::email($this->faker->safeEmail()),
            'transaction_date' => now()->subDays($this->faker->numberBetween(1, 300))->toDateString(),
        ];
    }
}
