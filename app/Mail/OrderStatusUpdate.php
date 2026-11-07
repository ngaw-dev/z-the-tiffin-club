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

class OrderStatusUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    public string $status;

    public function __construct(Order $order, ?string $status = null)
    {
        $this->order = $order->loadMissing('items');
        $this->status = $status ?? $order->status;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your Tiffin Box order '.$this->order->order_number,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-status-update',
            with: [
                'order' => $this->order,
                'status' => $this->status,
                'headline' => $this->headline(),
                'body' => $this->bodyCopy(),
                'pickupWindow' => PickupWindow::for($this->order),
                'restaurantAddress' => Setting::get('restaurant_address'),
            ],
        );
    }

    public function headline(): string
    {
        return match ($this->status) {
            'preparing' => "We're cooking your order",
            'out_for_delivery' => 'Your order is on its way',
            'ready_for_pickup' => 'Your order is ready for pickup',
            'completed' => 'Enjoy your meal!',
            'cancelled' => 'Your order has been cancelled',
            default => 'Update on your order',
        };
    }

    public function bodyCopy(): string
    {
        return match ($this->status) {
            'preparing' => "Your tiffin is being prepared in the kitchen. We'll let you know as soon as it moves on.",
            'out_for_delivery' => "Your tiffin has left the kitchen and is on its way to you. Please have the payment ready if you're paying on handover.",
            'ready_for_pickup' => 'Your tiffin is packed and waiting for you. See the pickup details below.',
            'completed' => 'Thanks for ordering from The Tiffin Box — we hope it hit the spot. See you tomorrow for the next meal!',
            'cancelled' => "Your order has been cancelled and you won't be charged. If this looks wrong, just reply to this email.",
            default => 'The status of your order has changed.',
        };
    }
}
