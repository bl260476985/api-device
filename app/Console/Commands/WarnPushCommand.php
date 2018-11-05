<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\WarnInfoPush;

class WarnPushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warn:push';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'warn push again';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $collect;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(WarnInfoPush $collect)
    {
        parent::__construct();
        $this->collect = $collect;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->collect->pushChange();
    }
}
