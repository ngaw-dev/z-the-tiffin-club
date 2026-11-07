Hi {{ $order->customer_name }},

Thanks for your order! We've received it and it's on our board.

**Order number:** {{ $order->order_number }}

**Your order:**

| Item | Qty | Price |
|:-----|----:|------:|
@foreach ($items as $item)
| {{ $item->name }} | {{ $item->quantity }} | ${{ number_format((float) $item->unit_price * $item->quantity, 2) }} |
@endforeach
| **Total** | | **${{ number_format((float) $order->order_total, 2) }}** |

@if ($order->fulfilment_type === 'pickup')
**Pickup** — collect your tiffin between **{{ $pickupWindow }}** from:

{{ $restaurantAddress }}
@else
**Delivery** — we'll bring your tiffin to:

{{ $order->delivery_address }}
@endif

**Payment:** {{ $order->payment_method === 'cash' ? 'Cash on handover' : 'Card on handover' }}

We'll email you when the status of your order changes.

Warmly,
The Tiffin Box
