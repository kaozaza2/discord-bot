<?php

namespace App\Providers;

use App\BotInstance;
use App\Contracts\BotInstances;
use App\Contracts\SlotMachines;
use App\SlotMachine;
use Discord\Discord;
use Discord\WebSockets\Intents;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Discord::class, function () {
            return new Discord([
                'token' => config('app.tokens.discord'),
                'intents' => Intents::getDefaultIntents(),
            ]);
        });

        $this->app->singleton(BotInstances::class, BotInstance::class);
        $this->app->singleton(SlotMachines::class, SlotMachine::class);
    }
}
