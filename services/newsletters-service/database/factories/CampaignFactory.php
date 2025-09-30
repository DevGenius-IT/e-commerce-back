<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Campaign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $campaignNames = [
            'Weekly Newsletter', 'Monthly Updates', 'Product Launch Announcement',
            'Holiday Special Offers', 'Summer Sale Campaign', 'New Features Update',
            'Customer Testimonials', 'Industry News Digest', 'Black Friday Deals',
            'Year End Review', 'Welcome Series', 'Re-engagement Campaign'
        ];

        return [
            'name' => $this->faker->randomElement($campaignNames) . ' - ' . $this->faker->monthName() . ' ' . $this->faker->year(),
            'subject' => $this->faker->randomElement([
                '🎉 Exciting news from {{company_name}}!',
                '📢 Don\'t miss out on these amazing deals',
                '✨ New features you\'ll love',
                '🔥 Limited time offer inside',
                '👋 Hello {{name}}, here\'s what\'s new',
                '📈 Your monthly business update',
                '💡 Tips and insights for {{first_name}}',
                '🎯 Exclusive offer just for you',
            ]),
            'content' => $this->generateEmailContent(),
            'plain_text' => $this->faker->paragraphs(3, true),
            'status' => $this->faker->randomElement(['draft', 'scheduled', 'sent']),
            'campaign_type' => $this->faker->randomElement(['newsletter', 'promotional', 'transactional', 'announcement']),
            'scheduled_at' => $this->faker->optional(0.3)->dateTimeBetween('-1 month', '+1 month'),
            'sent_at' => $this->faker->optional(0.4)->dateTimeBetween('-2 months', 'now'),
            'created_by' => $this->faker->optional()->numberBetween(1, 10),
            'targeting_criteria' => $this->faker->optional()->randomElements([
                'subscription_source' => $this->faker->randomElements(['website', 'checkout', 'social_media'], rand(1, 2)),
                'subscribed_after' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'exclude_bounced' => true,
                'max_bounce_count' => 2,
            ]),
            'total_recipients' => $this->faker->numberBetween(50, 5000),
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_opened' => 0,
            'total_clicked' => 0,
            'total_bounced' => 0,
            'total_unsubscribed' => 0,
            'notes' => $this->faker->optional()->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Generate realistic email content.
     */
    private function generateEmailContent(): string
    {
        $templates = [
            '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
                <h1 style="color: #333;">Hello {{name}}!</h1>
                <p>We hope this email finds you well. Here are some exciting updates from our team:</p>
                <ul>
                    <li>New product features</li>
                    <li>Upcoming events</li>
                    <li>Special offers just for you</li>
                </ul>
                <p>Best regards,<br>The Team</p>
                <p><small><a href="{{unsubscribe_url}}">Unsubscribe</a></small></p>
            </div>',
            
            '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
                <div style="background: #f8f9fa; padding: 20px; text-align: center;">
                    <h1 style="color: #007bff;">{{campaign_name}}</h1>
                </div>
                <div style="padding: 20px;">
                    <p>Hi {{first_name}},</p>
                    <p>This is your regular update with the latest news and insights:</p>
                    <div style="background: #e9ecef; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff;">
                        <h3>Featured Content</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                    </div>
                    <p>Thank you for being a valued subscriber!</p>
                    <p><a href="{{unsubscribe_url}}" style="color: #6c757d; font-size: 12px;">Unsubscribe</a></p>
                </div>
            </div>',
        ];

        return $this->faker->randomElement($templates);
    }

    /**
     * Indicate that the campaign is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'scheduled_at' => null,
            'sent_at' => null,
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_opened' => 0,
            'total_clicked' => 0,
            'total_bounced' => 0,
            'total_unsubscribed' => 0,
        ]);
    }

    /**
     * Indicate that the campaign is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'sent_at' => null,
        ]);
    }

    /**
     * Indicate that the campaign has been sent.
     */
    public function sent(): static
    {
        $totalRecipients = $this->faker->numberBetween(100, 1000);
        $totalSent = $totalRecipients;
        $totalDelivered = (int) ($totalSent * $this->faker->randomFloat(2, 0.85, 0.98));
        $totalOpened = (int) ($totalDelivered * $this->faker->randomFloat(2, 0.15, 0.45));
        $totalClicked = (int) ($totalOpened * $this->faker->randomFloat(2, 0.05, 0.25));
        $totalBounced = $totalSent - $totalDelivered;
        $totalUnsubscribed = $this->faker->numberBetween(0, (int) ($totalDelivered * 0.02));

        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'total_recipients' => $totalRecipients,
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_opened' => $totalOpened,
            'total_clicked' => $totalClicked,
            'total_bounced' => $totalBounced,
            'total_unsubscribed' => $totalUnsubscribed,
        ]);
    }

    /**
     * Indicate that the campaign is of a specific type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_type' => $type,
        ]);
    }

    /**
     * Indicate that the campaign has specific targeting criteria.
     */
    public function withTargeting(array $criteria): static
    {
        return $this->state(fn (array $attributes) => [
            'targeting_criteria' => $criteria,
        ]);
    }
}