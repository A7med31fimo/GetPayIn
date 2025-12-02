<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hold;

class ReleaseExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired holds and return stock to availability';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Hold::releaseExpired();

        $this->info("Released {$count} expired holds");

        return 0;
    }
}
