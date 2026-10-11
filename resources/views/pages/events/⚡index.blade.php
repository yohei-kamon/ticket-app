<?php

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('イベント一覧')] class extends Component {
    use WithPagination;

    /**
     * Get the published events that have not ended yet, soonest first.
     *
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return Event::query()
            ->published()
            ->where('ends_at', '>', now())
            ->withMin('ticketTypes', 'price')
            ->orderBy('starts_at')
            ->paginate(12);
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">イベント一覧</flux:heading>

    @if ($this->events->isEmpty())
        <flux:text>現在公開中のイベントはありません。</flux:text>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->events as $event)
                <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="event-{{ $event->id }}" class="group block">
                    <flux:card class="h-full space-y-3 p-0! overflow-hidden transition group-hover:border-zinc-400 dark:group-hover:border-zinc-500">
                        <div class="flex aspect-video items-center justify-center bg-zinc-100 dark:bg-zinc-700">
                            <flux:icon.photo class="size-10 text-zinc-400 dark:text-zinc-500" />
                        </div>

                        <div class="space-y-2 px-4 pb-4">
                            <flux:heading size="lg" class="line-clamp-2">{{ $event->title }}</flux:heading>

                            <flux:text class="flex items-center gap-1.5">
                                <flux:icon.calendar-days variant="micro" />
                                {{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}
                            </flux:text>

                            <flux:text class="flex items-center gap-1.5">
                                <flux:icon.map-pin variant="micro" />
                                <span class="truncate">{{ $event->venue }}</span>
                            </flux:text>

                            <flux:heading class="pt-1">
                                @if ($event->ticket_types_min_price === null)
                                    価格未定
                                @elseif ((int) $event->ticket_types_min_price === 0)
                                    無料
                                @else
                                    {{ number_format($event->ticket_types_min_price) }}円〜
                                @endif
                            </flux:heading>
                        </div>
                    </flux:card>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->events" />
    @endif
</section>
