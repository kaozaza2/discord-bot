<?php

namespace App;

use App\Contracts\SlotMachines;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;

class BotInstance implements Contracts\BotInstances
{
    protected array $cached = [];

    protected array $voiceChannels = [
        398011171648569348,
        612999725452361739,
        612999755420794893,
        764413776308731914,
        772820978287509516,
        764413925885345813,
        768342123120164934,
    ];

    protected bool $started = false;

    protected int $exitCode = 0;

    protected array $events = [];

    public function __construct(
        protected Discord $discord,
    ) {
        $this->registerEvents();

        $this->discord->on('ready', function ($discord) {
            $this->started = true;

            foreach ($this->events as $event => $callback) {
                $discord->on($event, $callback);
            }
        });
    }

    protected function registerEvents(): void
    {
        $this->addEvent(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
            $userId = $message->author->id;

            if ($userId === $discord->id) {
                return;
            }

            $user = $this->getUser($userId, $message->author->username);

            if (str_starts_with($message->content, '!')) {
                $this->handleCommand($message, $user);
            }
        });
    }

    protected function getUser($userId, $username): User
    {
        if (! isset($this->cached[$userId])) {
            $this->cached[$userId] = [
                'last_updated' => now(),
                'user' => User::query()->firstOrCreate(
                    attributes: ['id' => $userId],
                    values: [
                        'username' => $username,
                        'coins' => 0,
                    ],
                ),
            ];
        }

        if ($this->cached[$userId]['last_updated']->lt(now()->subMinute())) {
            $this->cached[$userId]['user']->refresh();
            $this->cached[$userId]['last_updated'] = now();
        }

        return $this->cached[$userId]['user'];
    }

    protected function handleCommand(Message $message, User $user): void
    {
        $command = substr($message->content, 1);

        [$command, $arguments] = explode(' ', $command, 2) + [1 => null];

        $arguments = collect(explode(' ', $arguments))
            ->filter(fn ($argument) => filled($argument))
            ->values();

        switch ($command) {
            case 'balance':
                $message->reply("คุณมี {$user->coins} เหรียญ.");
                break;
            case 'agive':
                if ($user->moderator() || $user->admin()) {
                    $this->handleGiveCommand($message, $arguments, true);
                }
                break;
            case 'give':
                $this->handleGiveCommand($message, $arguments);
                break;
        }
    }

    protected function handleBetCommand(Message $message, Collection $arguments): void
    {
        if ($arguments->count() !== 2) {
            $message->reply('วิธีใช้: !bet <amount> [red|black|odd|even]');
            return;
        }

        $amount = $arguments[0];
        $type = $arguments[1];

        if (! in_array($type, ['red', 'black', 'odd', 'even'])) {
            $message->reply('วิธีใช้: !bet <amount> [red|black|odd|even]');
            return;
        }

        $user = $this->getUser($message->author->id, $message->author->username);

        if ($user->coins < $amount) {
            $message->reply('คุณมีเหรียญไม่พอ.');
            return;
        }

        app(SlotMachines::class)->push(
            user: $user,
            type: $arguments[0],
            amount: $arguments[1],
        );
    }

    protected function handleGiveCommand(Message $message, Collection $arguments, $admin = false): void
    {
        if ($arguments->count() !== 2) {
            $give = $admin ? 'agive' : 'give';
            $message->reply("วิธีใช้: !$give @<user> <amount>");

            $arguments->dump();

            return;
        }

        $target = $arguments[0];
        $amount = $arguments[1];

        if (! $admin) {
            $user = $this->getUser($message->author->id, $message->author->username);
            if ($user->coins < $amount) {
                $message->reply('คุณมีเหรียญไม่พอ.');

                return;
            }
        }

        if (preg_match('/<@!?(?<id>\d+)>/', $target, $matches) === 1) {
            $target = $this->getUser($matches['id'], $target);
        } else {
            foreach ($this->cached as $cachedUser) {
                if ($cachedUser['user']->username === $target) {
                    $target = $cachedUser['user'];
                    break;
                }
            }
        }

        if (! isset($target)) {
            $message->reply('ไม่พบผู้ใช้นี้');

            return;
        }

        if (! $admin) {
            $user->decrement('coins', $amount);
        }

        $target->increment('coins', $amount);

        $message->reply("ให้ {$amount} เหรียญไปยัง <@$target->id>.");
    }

    protected function addEvent(string $event, $callback): void
    {
        $this->events[$event] = $callback;
    }

    public function run(): int
    {
        $this->discord->run();

        return $this->exitCode;
    }

    public function schedule(Schedule $schedule): void
    {
        if ($this->started) {
            $schedule->call(function () {
                foreach ($this->voiceChannels as $channelId) {
                    $channel = $this->discord->getChannel($channelId);
                    foreach ($channel->members as $member) {
                        $user = $member->member->user;
                        $user = $this->getUser($user->id, $user->username);
                        $user->increment('coins');
                    }
                }
            })->everyMinute();
        }
    }
}
