<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Orders are visible to the buyer, the event's organizer and admins.
     */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id
            || $order->event->organizer_id === $user->id
            || $user->isAdmin();
    }
}
