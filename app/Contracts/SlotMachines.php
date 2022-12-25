<?php

namespace App\Contracts;

use App\User;

interface SlotMachines
{
    public function push(User $user, string $type, int $amount): void;
}
