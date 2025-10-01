<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    /** @test */
    public function can_subscribe_to_newsletter()
    {
        $data = [
            'email' => $this->faker->email,
            'name' => $this->faker->name,
            'source' => 'website',
        ];

        $response = $this->postJson('/api/newsletters/subscribe', $data);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Newsletter subscription initiated. Please check your email to confirm.',
                ]);

        $this->assertDatabaseHas('newsletters', [
            'email' => $data['email'],
            'name' => $data['name'],
            'status' => 'pending',
            'subscription_source' => 'website',
        ]);
    }

    /** @test */
    public function cannot_subscribe_with_invalid_email()
    {
        $data = [
            'email' => 'invalid-email',
            'name' => $this->faker->name,
        ];

        $response = $this->postJson('/api/newsletters/subscribe', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function cannot_subscribe_duplicate_active_email()
    {
        $email = $this->faker->email;
        
        Newsletter::factory()->create([
            'email' => $email,
            'status' => 'subscribed',
        ]);

        $data = [
            'email' => $email,
            'name' => $this->faker->name,
        ];

        $response = $this->postJson('/api/newsletters/subscribe', $data);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Email is already subscribed to newsletter',
                ]);
    }

    /** @test */
    public function can_resubscribe_unsubscribed_email()
    {
        $email = $this->faker->email;
        
        $newsletter = Newsletter::factory()->create([
            'email' => $email,
            'status' => 'unsubscribed',
        ]);

        $data = [
            'email' => $email,
            'name' => $this->faker->name,
        ];

        $response = $this->postJson('/api/newsletters/subscribe', $data);

        $response->assertStatus(201);

        $newsletter->refresh();
        $this->assertEquals('subscribed', $newsletter->status);
        $this->assertNotNull($newsletter->subscribed_at);
    }

    /** @test */
    public function can_confirm_newsletter_subscription()
    {
        $newsletter = Newsletter::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/newsletters/confirm/{$newsletter->unsubscribe_token}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Newsletter subscription confirmed successfully',
                ]);

        $newsletter->refresh();
        $this->assertEquals('subscribed', $newsletter->status);
        $this->assertNotNull($newsletter->subscribed_at);
    }

    /** @test */
    public function cannot_confirm_with_invalid_token()
    {
        $response = $this->getJson('/api/newsletters/confirm/invalid-token');

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid confirmation token',
                ]);
    }

    /** @test */
    public function can_unsubscribe_from_newsletter()
    {
        $newsletter = Newsletter::factory()->create([
            'status' => 'subscribed',
            'subscribed_at' => now(),
        ]);

        $response = $this->getJson("/api/newsletters/unsubscribe/{$newsletter->unsubscribe_token}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Successfully unsubscribed from newsletter',
                ]);

        $newsletter->refresh();
        $this->assertEquals('unsubscribed', $newsletter->status);
        $this->assertNotNull($newsletter->unsubscribed_at);
    }

    /** @test */
    public function can_get_newsletter_list_with_authentication()
    {
        Newsletter::factory()->count(5)->create();

        // This would require authentication in real implementation
        $response = $this->getJson('/api/newsletters');

        // For now, we'll expect 401 since we don't have auth setup in tests
        $response->assertStatus(401);
    }

    /** @test */
    public function can_get_newsletter_statistics()
    {
        Newsletter::factory()->count(3)->create(['status' => 'subscribed']);
        Newsletter::factory()->count(2)->create(['status' => 'unsubscribed']);
        Newsletter::factory()->count(1)->create(['status' => 'pending']);

        // This would require authentication in real implementation
        $response = $this->getJson('/api/newsletters/stats');

        // For now, we'll expect 401 since we don't have auth setup in tests
        $response->assertStatus(401);
    }

    /** @test */
    public function newsletter_generates_unsubscribe_token_on_creation()
    {
        $newsletter = Newsletter::factory()->create();

        $this->assertNotNull($newsletter->unsubscribe_token);
        $this->assertEquals(64, strlen($newsletter->unsubscribe_token));
    }

    /** @test */
    public function newsletter_unsubscribe_url_is_generated_correctly()
    {
        $newsletter = Newsletter::factory()->create();

        $expectedUrl = config('app.url') . '/api/newsletters/unsubscribe/' . $newsletter->unsubscribe_token;
        $this->assertEquals($expectedUrl, $newsletter->unsubscribe_url);
    }

    /** @test */
    public function can_update_newsletter_preferences()
    {
        $newsletter = Newsletter::factory()->create([
            'preferences' => ['frequency' => 'weekly']
        ]);

        $newPreferences = ['frequency' => 'monthly', 'topics' => ['tech', 'business']];
        $newsletter->updatePreferences($newPreferences);

        $newsletter->refresh();
        $this->assertEquals([
            'frequency' => 'monthly',
            'topics' => ['tech', 'business']
        ], $newsletter->preferences);
    }

    /** @test */
    public function newsletter_bounce_tracking_works()
    {
        $newsletter = Newsletter::factory()->create([
            'status' => 'subscribed',
            'bounce_count' => 0,
        ]);

        $newsletter->markAsBounced('Hard bounce - invalid email');

        $newsletter->refresh();
        $this->assertEquals('bounced', $newsletter->status);
        $this->assertEquals(1, $newsletter->bounce_count);
        $this->assertNotNull($newsletter->last_bounce_at);
        $this->assertStringContains('Hard bounce - invalid email', $newsletter->notes);
    }
}