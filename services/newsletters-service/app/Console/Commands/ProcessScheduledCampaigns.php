<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletters:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled newsletter campaigns that are ready to be sent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing scheduled campaigns...');

        $campaigns = Campaign::readyToSend()->get();

        if ($campaigns->isEmpty()) {
            $this->info('No campaigns ready to send.');
            return;
        }

        $this->info("Found {$campaigns->count()} campaigns ready to send.");

        foreach ($campaigns as $campaign) {
            try {
                $this->info("Processing campaign: {$campaign->name} (ID: {$campaign->id})");
                
                // Mark as sending to prevent duplicate processing
                $campaign->markAsSending();
                
                // Dispatch the send job
                SendCampaignJob::dispatch($campaign);
                
                $this->info("Campaign {$campaign->id} dispatched successfully.");
                
            } catch (\Exception $e) {
                $this->error("Failed to process campaign {$campaign->id}: " . $e->getMessage());
                
                Log::error("Failed to dispatch campaign", [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage()
                ]);
                
                $campaign->markAsFailed();
            }
        }

        $this->info('Finished processing scheduled campaigns.');
    }
}