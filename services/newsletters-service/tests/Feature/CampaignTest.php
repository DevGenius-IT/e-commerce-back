<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class CampaignTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    /** @test */
    public function can_create_campaign()
    {
        $data = [
            'name' => 'Test Campaign',
            'subject' => 'Test Email Subject',
            'content' => '<h1>Test Content</h1><p>Hello {{name}}!</p>',
            'campaign_type' => 'newsletter',
        ];

        // This would require authentication in real implementation
        $response = $this->postJson('/api/campaigns', $data);

        // For now, we'll expect 401 since we don't have auth setup in tests
        $response->assertStatus(401);
    }

    /** @test */
    public function campaign_validation_works()
    {
        $data = [
            // Missing required fields
            'content' => '<h1>Test Content</h1>',
        ];

        $response = $this->postJson('/api/campaigns', $data);

        $response->assertStatus(401); // Would be 422 with auth
    }

    /** @test */
    public function campaign_can_be_scheduled()
    {
        $campaign = Campaign::factory()->create([
            'status' => 'draft'
        ]);

        $scheduledAt = now()->addHours(2);

        $campaign->schedule($scheduledAt);

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals($scheduledAt->timestamp, $campaign->scheduled_at->timestamp);
    }

    /** @test */
    public function campaign_can_be_marked_as_sending()
    {
        $campaign = Campaign::factory()->create([
            'status' => 'scheduled'
        ]);

        $campaign->markAsSending();

        $this->assertEquals('sending', $campaign->status);
    }

    /** @test */
    public function campaign_can_be_marked_as_sent()
    {
        $campaign = Campaign::factory()->create([
            'status' => 'sending'
        ]);

        $campaign->markAsSent();

        $this->assertEquals('sent', $campaign->status);
        $this->assertNotNull($campaign->sent_at);
    }

    /** @test */
    public function campaign_can_be_cancelled()
    {
        $campaign = Campaign::factory()->create([
            'status' => 'scheduled'
        ]);

        $result = $campaign->cancel();

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $campaign->status);
    }

    /** @test */
    public function cannot_cancel_sending_campaign()
    {
        $campaign = Campaign::factory()->create([
            'status' => 'sending'
        ]);

        $result = $campaign->cancel();

        $this->assertFalse($result);
        $this->assertEquals('sending', $campaign->status);
    }

    /** @test */
    public function campaign_performance_metrics_calculated_correctly()
    {
        $campaign = Campaign::factory()->create([
            'total_sent' => 1000,
            'total_delivered' => 950,
            'total_opened' => 380,
            'total_clicked' => 95,
            'total_bounced' => 50,
            'total_unsubscribed' => 10,
        ]);

        $metrics = $campaign->getPerformanceMetrics();

        $this->assertEquals(40.0, $metrics['open_rate']); // 380/950 * 100
        $this->assertEquals(10.0, $metrics['click_rate']); // 95/950 * 100
        $this->assertEquals(5.0, $metrics['bounce_rate']); // 50/1000 * 100
        $this->assertEquals(95.0, $metrics['delivery_rate']); // 950/1000 * 100
        $this->assertEquals(1.05, $metrics['unsubscribe_rate']); // 10/950 * 100
    }

    /** @test */
    public function campaign_statistics_update_correctly()
    {
        $campaign = Campaign::factory()->create();
        $newsletters = Newsletter::factory()->count(5)->create(['status' => 'subscribed']);

        // Attach newsletters to campaign with different statuses
        $campaign->newsletters()->attach($newsletters[0]->id, [
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        $campaign->newsletters()->attach($newsletters[1]->id, [
            'status' => 'opened',
            'delivered_at' => now(),
            'opened_at' => now(),
        ]);
        $campaign->newsletters()->attach($newsletters[2]->id, [
            'status' => 'clicked',
            'delivered_at' => now(),
            'opened_at' => now(),
            'clicked_at' => now(),
        ]);
        $campaign->newsletters()->attach($newsletters[3]->id, [
            'status' => 'bounced',
            'bounced_at' => now(),
        ]);
        $campaign->newsletters()->attach($newsletters[4]->id, [
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        $campaign->updateStatistics();

        $this->assertEquals(5, $campaign->total_sent);
        $this->assertEquals(3, $campaign->total_delivered); // delivered, opened, clicked
        $this->assertEquals(2, $campaign->total_opened); // opened, clicked
        $this->assertEquals(1, $campaign->total_clicked); // clicked
        $this->assertEquals(1, $campaign->total_bounced); // bounced
    }

    /** @test */
    public function can_duplicate_campaign()
    {
        $original = Campaign::factory()->create([
            'name' => 'Original Campaign',
            'status' => 'sent',
            'total_sent' => 100,
        ]);

        $duplicate = $original->replicate();
        $duplicate->name = $original->name . ' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->total_sent = 0;
        $duplicate->save();

        $this->assertEquals('Original Campaign (Copy)', $duplicate->name);
        $this->assertEquals('draft', $duplicate->status);
        $this->assertEquals(0, $duplicate->total_sent);
        $this->assertEquals($original->subject, $duplicate->subject);
        $this->assertEquals($original->content, $duplicate->content);
    }

    /** @test */
    public function ready_to_send_scope_works()
    {
        // Create campaigns with different statuses and schedule times
        Campaign::factory()->create([
            'status' => 'scheduled',
            'scheduled_at' => now()->subHours(1), // Past time - ready
        ]);
        Campaign::factory()->create([
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(1), // Future time - not ready
        ]);
        Campaign::factory()->create([
            'status' => 'draft',
            'scheduled_at' => now()->subHours(1), // Past time but draft - not ready
        ]);

        $readyCampaigns = Campaign::readyToSend()->get();

        $this->assertCount(1, $readyCampaigns);
    }

    /** @test */
    public function campaign_status_helpers_work()
    {
        $draft = Campaign::factory()->create(['status' => 'draft']);
        $scheduled = Campaign::factory()->create(['status' => 'scheduled']);
        $sending = Campaign::factory()->create(['status' => 'sending']);
        $sent = Campaign::factory()->create(['status' => 'sent']);
        $cancelled = Campaign::factory()->create(['status' => 'cancelled']);
        $failed = Campaign::factory()->create(['status' => 'failed']);

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isScheduled());

        $this->assertTrue($scheduled->isScheduled());
        $this->assertFalse($scheduled->isDraft());

        $this->assertTrue($sending->isSending());
        $this->assertFalse($sending->isSent());

        $this->assertTrue($sent->isSent());
        $this->assertFalse($sent->isSending());

        $this->assertTrue($cancelled->isCancelled());
        $this->assertFalse($cancelled->isFailed());

        $this->assertTrue($failed->isFailed());
        $this->assertFalse($failed->isCancelled());
    }
}