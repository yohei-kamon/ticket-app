<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Send a checkout.session.completed webhook for the order, signed with the given secret.
 */
function sendCheckoutCompletedWebhook(
    Order $order,
    string $secret = 'whsec_test',
    string $type = 'checkout.session.completed',
    string $paymentStatus = 'paid',
    ?string $orderId = null,
): TestResponse {
    $payload = json_encode([
        'id' => 'evt_test',
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => [
                'id' => 'cs_test',
                'object' => 'checkout.session',
                'payment_status' => $paymentStatus,
                'payment_intent' => 'pi_test',
                'metadata' => ['order_id' => $orderId ?? (string) $order->id],
            ],
        ],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return test()->call('POST', route('stripe.webhook'), server: [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload);
}
