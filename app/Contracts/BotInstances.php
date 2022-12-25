<?php

namespace App\Contracts;

use Illuminate\Console\Scheduling\Schedule;

interface BotInstances
{
    public function run(): int;

    public function schedule(Schedule $schedule): void;
}
