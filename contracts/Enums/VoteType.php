<?php

declare(strict_types=1);

namespace Worker\Contracts\Enums;

enum VoteType: string
{
    case UP = 'UP';
    case DOWN = 'DOWN';
}