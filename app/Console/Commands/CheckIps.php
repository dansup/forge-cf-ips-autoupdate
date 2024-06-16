<?php

namespace App\Console\Commands;

use App\Services\ForgeService;
use Illuminate\Console\Command;

class CheckIps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-ips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Forge + Cloudflare IPs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Running cf ip update check...');
        ForgeService::handleUpdate();
        $this->info('Done!');
    }
}
