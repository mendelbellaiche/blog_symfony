<?php

namespace App\Enum;

enum ModerationWordType: string
{
    case Watch = 'watch';
    case Forbidden = 'forbidden';

    public function label(): string
    {
        return match ($this) {
            self::Watch => 'À surveiller',
            self::Forbidden => 'Interdit',
        };
    }
}
