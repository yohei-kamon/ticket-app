<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /**
     * Handle a Stripe webhook.
     *
     * The signature is verified with the webhook secret. A completed and paid Checkout Session
     * marks its order as paid. Other events are acknowledged without doing anything.
     */
    public function __invoke(Request $request, OrderService $orderService): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            abort(400);
        }

        if ($event->type === 'checkout.session.completed') {
            /** @var Session $session */
            $session = $event->data->object;
            $orderId = $session->metadata['order_id'] ?? null;
            $order = is_string($orderId) ? Order::query()->whereKey($orderId)->first() : null;

            if ($order !== null && $session->payment_status === 'paid') {
                $orderService->markAsPaid($order, is_string($session->payment_intent) ? $session->payment_intent : null);
            }
        }

        return response()->noContent(200);
    }
}
