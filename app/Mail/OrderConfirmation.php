<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Support\PickupWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Tiffin Box order '.$this->order->order_number.' is in',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-confirmation',
            with: [
                'order' => $this->order,
                'items' => $this->order->items,
                'pickupWindow' => PickupWindow::for($this->order),
                'restaurantAddress' => Setting::get('restaurant_address'),
            ],
        );
    }
}
