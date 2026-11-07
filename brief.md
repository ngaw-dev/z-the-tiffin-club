Project Brief — The Tiffin Box (Daily-Meal Delivery App, Single Restaurant)
Status: Draft v2 for review · v1 + the board's answers to all eight §8 open questions (2026-11-07). §8 now records the resolutions; §7 flags two small interpretations to confirm.

1. Concept
The Tiffin Box — a delivery-first web app for one restaurant with no permanent menu. Each day the restaurant decides what it is cooking and publishes one fixed meal — every customer that day orders the same dish/box. Customers can add extra items to their order and choose between delivery (free, within the launch city) and pickup (18:00–20:00). Payment happens on handover (cash or card machine); there is no online payment at launch.

The daily-menu model keeps the offering small and predictable for the kitchen while giving customers a "what's on today?" destination.

2. Confirmed decisions
Topic	Decision
Brand	The Tiffin Box — restaurant and app share the name
Platform at launch	Responsive web app first; native mobile apps later (Phase 2)
Coverage	Single restaurant — one kitchen, one brand
Menu model	One fixed meal per day — everyone gets the same dish/box
Extras	Optional extra items published alongside the daily meal
Ordering window	Same-day only — delivery orders until 16:00, pickup orders until 20:00
Pickup window	18:00–20:00 daily, shown on the storefront
Delivery fee	Free delivery — no delivery charge at launch
Meal quantity	Up to 5 daily meals per order
Daily capacity	No cap and no sold-out state at launch
Payment	Pay on delivery/pickup — cash or card machine; no online payment at launch
Accounts	Guest checkout by default; optional account with saved order history
Menu management	Restaurant owner updates the daily meal and extras
Item content	Each item shows photo, description, price
Delivery zone	Limited to one city (e.g. Wellington)
Fulfilment	Customer chooses delivery or pickup per order
Delivery details	Collects name, email, phone, address
Order updates	Email only for the time being
3. Users and roles
Customer — browses today's meal, orders as guest or signed-in account holder, pays on handover.
Restaurant owner (admin) — publishes the daily meal and extras, manages cutoff times, sees incoming orders.
No other roles at launch.

4. Launch features
4.1 Daily meal
Exactly one meal per day, identical for all customers; no browsing of past or future menus in v1.
Published by the owner with photo, description, and price.
A customer can order up to 5 of the daily meal in a single order.
The storefront shows today's meal and extras; ordering closes automatically at the cutoff for each fulfilment mode (§4.4).
4.2 Extra items
Owner publishes a small set of extras with the daily meal — each with photo, description, price.
Customer can add extras to their order and adjust quantity per extra.
4.3 Fulfilment — delivery or pickup
Customer picks delivery or pickup during checkout.
Delivery: available only within the restaurant's city (e.g. Wellington) and free — no delivery fee at launch. Checkout collects name, email, phone, and delivery address. Delivery orders close at 16:00.
Pickup: storefront shows the restaurant's address and the pickup window: 18:00–20:00 daily. Checkout collects contact details only (no address). Pickup orders close at 20:00, when the window ends.
4.4 Ordering window
Orders are same-day only — no pre-ordering in v1.
Two daily cutoffs: 16:00 for delivery, 20:00 for pickup. After 16:00 the storefront marks delivery as closed but keeps accepting pickup orders; after 20:00 it switches to "ordering closed for today".
4.5 Payment
Cash or card machine on handover — at the door for delivery, or at the counter for pickup.
No online payment integration at launch; can be added in a later phase.
4.6 Checkout and accounts
Guest checkout is the default path — a customer never has to create an account to order.
Optional account creation; the launch benefit is saved order history (past orders viewable when signed in).
4.7 Order updates
Customers receive an email confirmation after ordering, and email for any later updates.
Email only for the time being — SMS is a possible later addition (§10).
4.8 Owner administration
Simple admin to publish the daily meal and extras: upload photo, write description, set price.
Configure the cutoff times (launch defaults: 16:00 delivery / 20:00 pickup) and open/close ordering manually if needed.
No sold-out / daily-capacity feature at launch — the owner cannot cap meals per day in v1.
5. Out of scope at launch
Native mobile apps (planned Phase 2, same backend)
Online / card-not-present payments
Multiple restaurants or marketplace features
Pre-orders or scheduled future-day orders
Delivery outside the launch city
Delivery fees (free at launch)
Sold-out state / daily capacity caps
SMS notifications
6. Key flows
Customer: open site → see today's meal + extras → add to order (meal × up to 5, extras as needed) → choose delivery (enter name, email, phone, address) or pickup → place order → receive email confirmation → pay on handover.

Owner: publish today's meal (photo, description, price) + extras → confirm cutoffs → watch incoming orders → fulfil (deliveries after close, pickup 18:00–20:00).

7. Assumptions
Made to keep the brief complete — correct any that are wrong:

Pickup orders collect contact details (name, email, phone) but not an address.
Two cutoffs, not one: I read "cutoff for order 16:00, cutoff for pickup 20:00" as delivery closes 16:00; pickup ordering stays open until 20:00. If you meant a single 16:00 cutoff for all orders (20:00 only being when pickup ends), say so and I'll adjust.
The 16:00 / 20:00 times are the fixed launch defaults but remain adjustable by the owner in admin.
Only the daily meal is capped (5 per order); extras have no quantity cap at launch.
Displayed prices include tax (e.g. GST) — no separate tax line at checkout.
8. Resolved decisions
All eight v1 open questions were answered on 2026-11-07 — none remain:

#	Question	Answer
1	Brand / restaurant name	The Tiffin Box
2	Daily cutoff time(s)	Delivery orders 16:00; pickup orders 20:00
3	Delivery fee	Free
4	Meal quantity per order	Up to 5 meals
5	Daily capacity / sold out	No sold-out state at launch
6	Pickup window	18:00–20:00
7	Account benefits	Saved order history
8	Order updates	Email only for the time being
9. Proposed success measures (v1)
A first-time guest completes an order in under 2 minutes.
The owner publishes a full daily menu (meal + extras) in under 5 minutes.
Ordering opens and closes correctly at both cutoffs (16:00 delivery / 20:00 pickup) without manual intervention.
10. Phasing
Phase 1 (this build): responsive web app — everything in §4.
Phase 2 (later): native mobile apps on the same backend; optional online payments; pre-orders; possibly SMS updates.
