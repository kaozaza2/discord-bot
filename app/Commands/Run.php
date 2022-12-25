<?php

namespace App\Commands;

use App\Contracts\BotInstances;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Run extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'run';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Startup the bot';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return app(BotInstances::class)->run();
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        app(BotInstances::class)->schedule($schedule);
    }
}
