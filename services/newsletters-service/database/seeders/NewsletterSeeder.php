<?php

namespace Database\Seeders;

use App\Models\Newsletter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NewsletterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding if data already exists to avoid conflicts
        if (Newsletter::count() > 0) {
            $this->command->info('Newsletter data already exists, skipping seeder...');
            return;
        }
        
        // Create demo newsletters with various statuses
        Newsletter::factory()
            ->count(150)
            ->subscribed()
            ->create();

        Newsletter::factory()
            ->count(25)
            ->unsubscribed()
            ->create();

        Newsletter::factory()
            ->count(15)
            ->pending()
            ->create();

        Newsletter::factory()
            ->count(8)
            ->bounced()
            ->create();

        // Create newsletters from different sources
        Newsletter::factory()
            ->count(30)
            ->subscribed()
            ->fromSource('checkout')
            ->create();

        Newsletter::factory()
            ->count(20)
            ->subscribed()
            ->fromSource('social_media')
            ->create();

        Newsletter::factory()
            ->count(25)
            ->subscribed()
            ->fromSource('import')
            ->create();

        // Create newsletters with specific preferences
        Newsletter::factory()
            ->count(40)
            ->subscribed()
            ->withPreferences([
                'frequency' => 'weekly',
                'topics' => ['tech', 'business'],
                'format' => 'html'
            ])
            ->create();

        Newsletter::factory()
            ->count(30)
            ->subscribed()
            ->withPreferences([
                'frequency' => 'monthly',
                'topics' => ['lifestyle', 'health'],
                'format' => 'html'
            ])
            ->create();

        // Create some test newsletters for development
        if (app()->environment('local', 'staging')) {
            Newsletter::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'status' => 'subscribed',
                    'subscribed_at' => now(),
                    'subscription_source' => 'manual',
                    'preferences' => [
                        'frequency' => 'weekly',
                        'topics' => ['tech', 'business', 'lifestyle'],
                        'format' => 'html'
                    ]
                ]
            );

            Newsletter::firstOrCreate(
                ['email' => 'demo@newsletter.com'],
                [
                    'name' => 'Demo Newsletter',
                    'status' => 'pending',
                    'subscription_source' => 'website'
                ]
            );
        }
    }
}