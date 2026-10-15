<?php

use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('マイチケット')] class extends Component {
    use WithPagination;

    /**
     * Get the current user's orders, newest first.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Auth::user()->orders()
            ->with('event')
            ->withSum('items', 'quantity')
            ->latest()
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">マイチケット</flux:heading>

    @if ($this->orders->isEmpty())
        <flux:text>まだ注文がありません。</flux:text>
    @else
        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->orders as $order)
                <div class="flex flex-wrap items-center gap-4 p-4" wire:key="order-{{ $order->id }}">
                    <div class="min-w-0 flex-1">
                        <flux:heading class="truncate">{{ $order->event->title }}</flux:heading>
                        <flux:text class="mt-1">開催日: {{ $order->event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:text>
                        <flux:text class="mt-1">注文日: {{ $order->created_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:text>
                    </div>

                    <div class="text-end text-sm">
                        <div>{{ (int) $order->items_sum_quantity }}枚</div>
                        <div class="font-medium">{{ number_format($order->total_amount) }}円</div>
                    </div>

                    <flux:badge :color="$order->status->badgeColor()" size="sm">
                        {{ $order->status->label() }}
                    </flux:badge>

                    <flux:button size="sm" icon="eye" :href="route('my.orders.show', $order)" wire:navigate>
                        詳細
                    </flux:button>
                </div>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->orders" />
    @endif
</section>
