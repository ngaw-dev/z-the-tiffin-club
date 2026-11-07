# Order Emails (Phase 5)

Transactional emails for The Tiffin Box: order confirmation and order status updates.

## Events → Listeners → Mailables

| Event | Listener | Mailable |
|---|---|---|
| `App\Events\OrderPlaced(Order $order)` | `App\Listeners\SendOrderConfirmation` (queued) | `App\Mail\OrderConfirmation` |
| `App\Events\OrderStatusChanged(Order $order)` | `App\Listeners\SendOrderStatusUpdate` (queued) | `App\Mail\OrderStatusUpdate` |

Listeners are auto-discovered by Laravel (event auto-discovery scans `app/Listeners`); no manual registration is required. Both mailables and listeners implement `ShouldQueue`, so emails are sent via the queue, never inline in a request.

### Dispatch contract (for Phase 3 storefront & Phase 4 admin API)

```php
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;

// After the order is validated, totals recomputed, and the Order + OrderItems
// are persisted (status 'received'):
OrderPlaced::dispatch($order);            // $order must have items loaded/persisted

// After an admin PATCH advances/cancels the status and it is persisted:
OrderStatusChanged::dispatch($order->refresh());  // status already on the model
```

Fire the events **after** the DB write commits logically (post-persist). The listeners use `SerializesModels`, so only identifiers travel through the queue.

## Email contents

**Order confirmation** (`mail.order-confirmation` markdown): greeting, order number, item table built from `order_items` snapshot `name`/`unit_price`/`quantity`, total, fulfilment details (delivery address, or pickup window + restaurant address for pickup), payment method.

**Order status update** (`mail.order-status-update` markdown): human-readable copy per status (`received`, `preparing`, `out_for_delivery`, `ready_for_pickup`, `completed`, `cancelled`); includes pickup window + address when status is `ready_for_pickup`.

Pickup window text comes from the `pickup_window_start` / `pickup_window_end` settings (via `App\Support\PickupWindow`), falling back to `Setting::DEFAULTS` (18:00–20:00).

## Queue & Mailpit in DDEV

- Queue driver: `database` (`QUEUE_CONNECTION=database` in `.env`; jobs table already migrated).
- Mail: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` (already in `.env.example` DDEV section; Mailpit service in `.ddev/docker-compose.mailpit.yaml`).
- Mailpit UI: https://the-tiffin-box.ddev.site:8026 (or http://:8025 inside the project).

Run a queue worker in DDEV:

```bash
ddev ssh
php artisan queue:work --tries=3
# or non-interactively from the host:
ddev exec php artisan queue:work --tries=3
```

Without a worker, queued emails sit in the `jobs` table. For a quick one-shot flush: `ddev exec php artisan queue:work --stop-when-empty`.

## Manual verification in Mailpit

1. `ddev start` and `ddev exec php artisan queue:work &` (or run in a second terminal).
2. `ddev exec php artisan tinker`:
   ```php
   $o = Order::factory()->create(['fulfilment_type' => 'pickup']);
   $o->items()->create(['item_type' => 'meal', 'name' => 'Butter Chicken Thali', 'unit_price' => 18.00, 'quantity' => 2]);
   $o->update(['order_total' => 36.00]);
   App\Events\OrderPlaced::dispatch($o->refresh());
   $o->update(['status' => 'preparing']);
   App\Events\OrderStatusChanged::dispatch($o->refresh());
   ```
3. Check Mailpit (port 8026) for both messages.

## Tests

`tests/Feature/OrderEmailsTest.php` (runs with `php artisan test` or `./vendor/bin/phpunit`):
- Both mailables render with order number, snapshot item lines, total, fulfilment details, and pickup window where applicable.
- `Mail::fake()` + `event(...)` proves each mailable is queued to the customer email on dispatch.
