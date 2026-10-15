<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Canceled = 'canceled';

    /**
     * Get the Japanese label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => '支払い待ち',
            self::Paid => '支払い済み',
            self::Canceled => 'キャンセル',
        };
    }

    /**
     * Get the Flux badge color for the status.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Paid => 'green',
            self::Canceled => 'zinc',
        };
    }
}
