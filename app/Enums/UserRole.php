<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Organizer = 'organizer';
    case Admin = 'admin';

    /**
     * Get the Japanese label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => '参加者',
            self::Organizer => '主催者',
            self::Admin => '管理者',
        };
    }

    /**
     * Get the roles that users can choose when registering.
     *
     * @return array<int, self>
     */
    public static function selfRegisterable(): array
    {
        return [self::Customer, self::Organizer];
    }
}
