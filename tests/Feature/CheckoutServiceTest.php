<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('the payment page expires before the reservation of the order ends', function () {
    $this->freezeTime();
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    $sentParams = null;
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('request')
        ->once()
        ->andReturnUsing(function (string $method, string $url, array $headers, array $params) use (&$sentParams): array {
            $sentParams = $params;

            return [json_encode(['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test']), 200, []];
        });
    ApiRequestor::setHttpClient($httpClient);
    $this->app->instance(StripeClient::class, new StripeClient('sk_test_dummy'));

    expect(app(CheckoutService::class)->createSession($order))->toBe('https://checkout.stripe.com/c/pay/cs_test')
        ->and($sentParams['expires_at'])->toBe(now()->addMinutes(CheckoutService::EXPIRES_MINUTES)->timestamp)
        ->and(CheckoutService::EXPIRES_MINUTES)->toBeLessThan(OrderService::PENDING_MINUTES);
});
