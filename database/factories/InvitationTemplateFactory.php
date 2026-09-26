<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\InvitationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitationTemplate>
 */
class InvitationTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'locale' => 'en-GB',
            'subject' => 'How was your experience with {business_name}?',
            'body' => "Hi {recipient_name},\n\nThanks for your recent booking. We'd value your honest feedback.\n\n{review_link}\n\nDon't want these emails? {unsubscribe_link}",
            'is_active' => true,
        ];
    }
}
