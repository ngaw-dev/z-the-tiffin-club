Hi {{ $order->customer_name }},

**{{ $headline }}**

{{ $body }}

**Order number:** {{ $order->order_number }}

@if ($status === 'ready_for_pickup' && $pickupWindow)
**Pickup window:** {{ $pickupWindow }}

**Pickup address:** {{ $restaurantAddress }}
@endif

If you have any questions, just reply to this email.

Warmly,
The Tiffin Box
