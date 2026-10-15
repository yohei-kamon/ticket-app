<x-mail::message>
# チケットの購入が完了しました

{{ $order->user->name }} 様

以下の内容でチケットの購入が完了しました。

**イベント**: {{ $order->event->title }}<br>
**日時**: {{ $order->event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}<br>
**会場**: {{ $order->event->venue }}

<x-mail::table>
| 券種 | 枚数 | 小計 |
|:-----|-----:|-----:|
@foreach ($order->items as $item)
| {{ $item->ticketType->name }} | {{ $item->quantity }}枚 | {{ number_format($item->unit_price * $item->quantity) }}円 |
@endforeach
| **合計** | | **{{ number_format($order->total_amount) }}円** |
</x-mail::table>

チケットのコードは注文の詳細で確認できます。当日は受付でこの画面をお見せください。

<x-mail::button :url="route('my.orders.show', $order)">
注文の詳細を見る
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
