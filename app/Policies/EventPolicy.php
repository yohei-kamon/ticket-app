<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Published events are visible to everyone; others only to the owner and admins.
     */
    public function view(?User $user, Event $event): bool
    {
        if ($event->isPublished()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->isAdmin() || $this->isOwner($user, $event);
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user): bool
    {
        return $user->isOrganizer();
    }

    /**
     * Determine whether the user can update the event (including publish / draft toggling).
     */
    public function update(User $user, Event $event): bool
    {
        return $this->isOwner($user, $event);
    }

    /**
     * Determine whether the user can switch the event between published and draft.
     *
     * Events unpublished by an admin cannot be switched by the organizer.
     */
    public function publish(User $user, Event $event): bool
    {
        return $this->isOwner($user, $event) && $event->status !== EventStatus::Unpublished;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->isOwner($user, $event);
    }

    /**
     * Determine whether the user can unpublish the event as an admin.
     */
    public function unpublish(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user organizes the event.
     */
    private function isOwner(User $user, Event $event): bool
    {
        return $event->organizer_id === $user->id;
    }
}
