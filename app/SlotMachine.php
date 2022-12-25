<?php

namespace App;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Fiber;

class SlotMachine implements Contracts\SlotMachines
{
    protected array $pending = [];

    protected array $wheel = [];

    protected int $channelId = 865066286399881276;

    protected string $state = 'stopped';

    public function __construct(
        protected Discord $discord,
    ) {
        $this->wheel[] = [
            'number' => 0,
            'odd' => false,
            'black' => false,
        ];

        for ($i = 1; $i < 37; $i++) {
            $this->wheel[] = [
                'number' => $i,
                'odd' => $i % 2 === 1,
                'black' => in_array($i, [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35]),
            ];
        }
    }

    public function push(User $user, string $type, int $amount): void
    {
        $this->pending[$user->id] = [
            'user' => $user,
            'type' => $type,
            'amount' => $amount,
        ];

        if ($this->state === 'stopped') {
            $this->start();
        }
    }

    protected function start(): void
    {
        $this->state = 'waiting';

        $fiber = new Fiber(function () {
            $this->state = 'running';

            $channel = $this->discord->getChannel($this->channelId);

            $channel->sendMessage(
                MessageBuilder::new()
                    ->addEmbed(
                        (new Embed($this->discord))
                            ->setTitle('กำลังปั่นรูเล็ต')
                            ->setDescription('กรุณาลงเดิมพันใน 20 วินาทีก่อนรูเล็ตหยุด...')
                            ->setColor(0x00FF00)
                    )
            );

            usleep(20000000);

            $result = collect($this->wheel)->shuffle()->first();

            $builder = MessageBuilder::new();

            $embed = new Embed($this->discord);
            $embed->setTitle('รูเล็ตหยุดแล้ว');

            if ($result['number'] == 0) {
                $embed->setDescription('รูเล็ตหยุดที่ 0, ไม่มีผู้ชนะ');

                foreach ($this->pending as $pending) {
                    $user = $pending['user'];
                    $amount = $pending['amount'];
                    $user->decrement('coins', $amount);
                }
            } else {
                $embed->setDescription(
                    sprintf(
                        'รูเล็ตหยุดที่ %s %s %s',
                        $result['number'],
                        $result['odd'] ? 'odd' : 'even',
                        $result['black'] ? 'black' : 'red',
                    ),
                );

                $builder->addEmbed($embed);

                $embed = new Embed($this->discord);
                $embed->setTitle('ผลรางวัล');

                $won = collect();

                foreach ($this->pending as $pending) {
                    $user = $pending['user'];
                    $type = $pending['type'];
                    $amount = $pending['amount'];

                    $isWon = $result[$type];

                    $user->increment('coins', $isWon ? $amount : -$amount);

                    $won->push(
                        sprintf('%s %s%s', $user->username, $isWon ? '+' : '-', $amount),
                    );
                }
                $embed->setDescription($won->implode(', '));
            }
            $builder->addEmbed($embed);
            $channel->sendMessage($builder);

            $this->state = 'stopped';
        });

        $fiber->start();
    }
}
