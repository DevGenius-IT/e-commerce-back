<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Newsletter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sent campaigns with performance data
        $sentCampaigns = Campaign::factory()
            ->count(12)
            ->sent()
            ->create();

        // Create draft campaigns
        Campaign::factory()
            ->count(5)
            ->draft()
            ->create();

        // Create scheduled campaigns
        Campaign::factory()
            ->count(3)
            ->scheduled()
            ->create();

        // Create campaigns of different types
        Campaign::factory()
            ->count(4)
            ->sent()
            ->ofType('promotional')
            ->create();

        Campaign::factory()
            ->count(2)
            ->sent()
            ->ofType('announcement')
            ->create();

        Campaign::factory()
            ->count(3)
            ->draft()
            ->ofType('newsletter')
            ->create();

        // Create campaigns with targeting criteria
        Campaign::factory()
            ->count(2)
            ->sent()
            ->withTargeting([
                'subscription_source' => ['website', 'checkout'],
                'exclude_bounced' => true,
                'max_bounce_count' => 2
            ])
            ->create();

        // Attach newsletters to some campaigns for realistic data
        $newsletters = Newsletter::subscribed()->take(100)->get();
        
        foreach ($sentCampaigns->take(5) as $campaign) {
            $selectedNewsletters = $newsletters->random(rand(50, 80));
            
            foreach ($selectedNewsletters as $newsletter) {
                $status = $this->getRandomEmailStatus();
                $pivotData = $this->generatePivotData($status);
                
                $campaign->newsletters()->attach($newsletter->id, $pivotData);
            }
            
            // Update campaign statistics
            $campaign->updateStatistics();
        }

        // Create some specific test campaigns for development
        if (app()->environment('local', 'staging')) {
            Campaign::factory()->create([
                'name' => 'Test Campaign - Welcome Series',
                'subject' => 'Welcome to our newsletter, {{name}}!',
                'content' => $this->getTestCampaignContent(),
                'status' => 'draft',
                'campaign_type' => 'newsletter',
                'created_by' => 1,
            ]);

            Campaign::factory()->create([
                'name' => 'Demo Promotional Campaign',
                'subject' => '🎉 Special offer for {{first_name}}!',
                'content' => $this->getPromotionalCampaignContent(),
                'status' => 'scheduled',
                'campaign_type' => 'promotional',
                'scheduled_at' => now()->addDays(2),
                'targeting_criteria' => [
                    'subscription_source' => ['website', 'checkout'],
                    'exclude_bounced' => true
                ]
            ]);
        }
    }

    /**
     * Get random email status for pivot data.
     */
    private function getRandomEmailStatus(): string
    {
        $statuses = [
            'delivered' => 85,   // 85% chance
            'opened' => 35,      // 35% chance
            'clicked' => 8,      // 8% chance
            'bounced' => 3,      // 3% chance
            'failed' => 2,       // 2% chance
        ];

        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($statuses as $status => $probability) {
            $cumulative += $probability;
            if ($random <= $cumulative) {
                return $status;
            }
        }

        return 'delivered';
    }

    /**
     * Generate realistic pivot data based on status.
     */
    private function generatePivotData(string $status): array
    {
        $baseData = [
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $sentAt = now()->subDays(rand(1, 60));
        $baseData['sent_at'] = $sentAt;

        switch ($status) {
            case 'delivered':
            case 'opened':
            case 'clicked':
                $baseData['delivered_at'] = $sentAt->copy()->addMinutes(rand(5, 120));
                
                if (in_array($status, ['opened', 'clicked'])) {
                    $baseData['opened_at'] = $baseData['delivered_at']->copy()->addMinutes(rand(10, 1440));
                    $baseData['open_count'] = rand(1, 3);
                }
                
                if ($status === 'clicked') {
                    $baseData['clicked_at'] = $baseData['opened_at']->copy()->addMinutes(rand(1, 60));
                    $baseData['click_count'] = rand(1, 5);
                    $baseData['click_data'] = [
                        'links' => [
                            ['url' => 'https://example.com/product1', 'clicks' => rand(1, 3)],
                            ['url' => 'https://example.com/offer', 'clicks' => rand(1, 2)],
                        ]
                    ];
                }
                break;

            case 'bounced':
                $baseData['bounced_at'] = $sentAt->copy()->addMinutes(rand(5, 30));
                $baseData['bounce_reason'] = collect([
                    'Hard bounce - Invalid email address',
                    'Soft bounce - Mailbox full',
                    'Soft bounce - Temporary delivery failure',
                    'Hard bounce - Domain not found'
                ])->random();
                break;

            case 'failed':
                $baseData['failed_at'] = $sentAt->copy()->addMinutes(rand(1, 10));
                $baseData['failure_reason'] = collect([
                    'SMTP connection failed',
                    'Rate limit exceeded',
                    'Content blocked by provider',
                    'Authentication failed'
                ])->random();
                break;
        }

        // Add some user agent and IP data for tracking
        if (in_array($status, ['opened', 'clicked'])) {
            $baseData['user_agent'] = collect([
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
                'Mozilla/5.0 (Android 11; Mobile; rv:68.0) Gecko/68.0 Firefox/88.0'
            ])->random();
            
            $baseData['ip_address'] = collect([
                '192.168.1.100',
                '10.0.0.50',
                '172.16.0.25',
                '203.0.113.42'
            ])->random();
        }

        return $baseData;
    }

    /**
     * Get test campaign content.
     */
    private function getTestCampaignContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to Our Newsletter</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #007bff; color: white; padding: 20px; text-align: center;">
        <h1>Welcome {{name}}!</h1>
    </div>
    <div style="padding: 30px;">
        <p>Hi {{first_name}},</p>
        <p>Thank you for subscribing to our newsletter! We\'re excited to have you as part of our community.</p>
        
        <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px;">
            <h3>What to expect:</h3>
            <ul>
                <li>Weekly updates on the latest trends</li>
                <li>Exclusive offers and discounts</li>
                <li>Helpful tips and insights</li>
            </ul>
        </div>
        
        <p>We promise to keep your inbox interesting and never spam you.</p>
        <p>Welcome aboard!</p>
        <p>The Newsletter Team</p>
    </div>
    <div style="background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;">
        <p><a href="{{unsubscribe_url}}" style="color: #ffffff;">Unsubscribe</a> | Contact Support</p>
    </div>
</body>
</html>';
    }

    /**
     * Get promotional campaign content.
     */
    private function getPromotionalCampaignContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Special Offer Inside!</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">
        <h1 style="margin: 0; font-size: 28px;">🎉 Special Offer</h1>
        <p style="margin: 10px 0 0 0; font-size: 18px;">Just for you, {{first_name}}!</p>
    </div>
    <div style="padding: 30px;">
        <h2 style="color: #333;">Limited Time: 50% Off Everything!</h2>
        
        <p>Hi {{name}},</p>
        <p>We have something special just for our valued subscribers. For the next 48 hours, enjoy <strong>50% off</strong> on all our products!</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="#" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                Shop Now & Save 50%
            </a>
        </div>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0;"><strong>⏰ Hurry!</strong> This offer expires in 48 hours.</p>
        </div>
        
        <p>Don\'t miss out on these incredible savings!</p>
        <p>Happy shopping!</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d;">
        <p><a href="{{unsubscribe_url}}" style="color: #6c757d;">Unsubscribe</a> | Terms & Conditions</p>
    </div>
</body>
</html>';
    }
}