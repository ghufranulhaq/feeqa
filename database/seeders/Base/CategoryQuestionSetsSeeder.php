<?php

namespace Database\Seeders\Base;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * FR-002-19, FR-002-20: launch content from travel-content.md §2
 * (client-approved). Question sets are versioned/immutable by design
 * (FR-002-20) — this only ever creates version 1, for a category that
 * doesn't have one yet. A real content change goes through
 * App\Actions\Staff\PublishCategoryQuestionSet instead of a re-seed.
 */
class CategoryQuestionSetsSeeder extends Seeder
{
    public function run(): void
    {
        $this->publish('travel', [
            ['key' => 'booking_ease', 'label' => 'How easy was booking?', 'type' => 'rating_1_5'],
            ['key' => 'value_for_money', 'label' => 'Value for money', 'type' => 'rating_1_5'],
            ['key' => 'use_again', 'label' => 'Would you use them again?', 'type' => 'yes_no', 'required' => true],
        ]);

        $this->publish('airlines', [
            ['key' => 'on_time', 'label' => 'Punctuality (on-time departure/arrival)', 'type' => 'rating_1_5', 'required' => true],
            ['key' => 'baggage', 'label' => 'Baggage handling', 'type' => 'rating_1_5'],
            ['key' => 'crew', 'label' => 'Cabin crew', 'type' => 'rating_1_5'],
            ['key' => 'seat_comfort', 'label' => 'Seat comfort', 'type' => 'rating_1_5'],
            ['key' => 'disruption_handling', 'label' => 'Handling of delays/cancellations (if any)', 'type' => 'rating_1_5'],
            ['key' => 'cabin_class', 'label' => 'Cabin class', 'type' => 'single_choice', 'options' => ['Economy', 'Premium Economy', 'Business', 'First']],
            ['key' => 'flight_type', 'label' => 'Flight type', 'type' => 'single_choice', 'options' => ['Short-haul', 'Long-haul']],
        ]);

        $this->publish('travel-agencies-otas', [
            ['key' => 'price_transparency', 'label' => 'Were all fees shown up front?', 'type' => 'yes_no', 'required' => true],
            ['key' => 'customer_service', 'label' => 'Customer service', 'type' => 'rating_1_5'],
            ['key' => 'changes_cancellations', 'label' => 'Handling of changes/cancellations (if any)', 'type' => 'rating_1_5'],
            ['key' => 'refund_handling', 'label' => 'Refund handling (if any)', 'type' => 'rating_1_5'],
            ['key' => 'documents_on_time', 'label' => 'Tickets/documents received on time', 'type' => 'yes_no'],
        ]);

        $this->publish('airports', [
            ['key' => 'security_wait', 'label' => 'Security queue time', 'type' => 'rating_1_5', 'required' => true],
            ['key' => 'cleanliness', 'label' => 'Cleanliness', 'type' => 'rating_1_5'],
            ['key' => 'signage', 'label' => 'Ease of finding your way', 'type' => 'rating_1_5'],
            ['key' => 'accessibility', 'label' => 'Accessibility / assistance services', 'type' => 'rating_1_5'],
            ['key' => 'facilities', 'label' => 'Shops, food, and seating', 'type' => 'rating_1_5'],
        ]);
    }

    /**
     * @param  list<array{key: string, label: string, type: string, required?: bool, options?: list<string>}>  $questions
     */
    private function publish(string $categorySlug, array $questions): void
    {
        $category = Category::where('slug', $categorySlug)->first();

        if ($category === null || $category->questionSets()->exists()) {
            return;
        }

        $questionSet = $category->questionSets()->create(['version' => 1, 'published_at' => now()]);

        foreach ($questions as $order => $question) {
            $questionSet->questions()->create([
                'key' => $question['key'],
                'label' => ['en-GB' => $question['label']],
                'type' => $question['type'],
                'required' => $question['required'] ?? false,
                'options' => isset($question['options']) ? ['en-GB' => $question['options']] : null,
                'order' => $order,
            ]);
        }
    }
}
