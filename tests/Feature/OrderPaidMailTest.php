<?php

use App\Mail\OrderPaidMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;

test('the purchase mail shows the event, the items, the total and a link to the order', function () {
    $event = Event::factory()->published()->create(['title' => '夏祭り', 'venue' => '中央公園']);
    $order = Order::factory()->paid()->for($event)->create(['total_amount' => 7000]);
    $item = OrderItem::factory()->for($order)->create(['quantity' => 2, 'unit_price' => 3500]);

    $mail = new OrderPaidMail($order);

    $mail->assertHasSubject('【まちチケ】チケットの購入が完了しました（夏祭り）');
    $mail->assertSeeInHtml('夏祭り')
        ->assertSeeInHtml($event->starts_at->isoFormat('M月D日(ddd) HH:mm'))
        ->assertSeeInHtml('中央公園')
        ->assertSeeInHtml('<table', false)
        ->assertSeeInHtml($item->ticketType->name)
        ->assertSeeInHtml('7,000円')
        ->assertSeeInHtml(route('my.orders.show', $order));
});
