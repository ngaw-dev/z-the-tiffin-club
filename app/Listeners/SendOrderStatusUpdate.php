<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Mail\OrderStatusUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderStatusUpdate implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function handle(OrderStatusChanged $event): void
    {
        Mail::to($event->order->customer_email)
            ->send(new OrderStatusUpdate($event->order));
    }
}
