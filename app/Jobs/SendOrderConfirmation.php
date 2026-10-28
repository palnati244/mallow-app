<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {
    }

    public function handle(): void
    {
        $order = Order::with('customer')->find($this->orderId);

        if (! $order) {
            return;
        }

        Log::info('Order confirmation simulated.', [
            'order_id' => $order->id,
            'customer_email' => $order->customer->email,
            'grand_total' => $order->grand_total,
        ]);
    }
}