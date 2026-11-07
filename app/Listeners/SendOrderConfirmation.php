<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->customer_email)
            ->send(new OrderConfirmation($event->order));
    }
}
