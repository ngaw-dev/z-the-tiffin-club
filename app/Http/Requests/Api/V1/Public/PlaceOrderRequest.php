<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Basic request-shape validation only. All §T2.2 business rules (meal cap,
 * extras of today's meal, delivery address/city, cutoffs) live in the
 * Phase 2 OrderValidationService, which the controller invokes.
 */
class PlaceOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'string'],
            'items.*.extra_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'fulfilment_type' => ['required', 'string', 'in:delivery,pickup'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'in:cash,card_on_handover'],
        ];
    }

    /**
     * Payload handed to the OrderValidationService (§T2.2 rules).
     *
     * @return array<string, mixed>
     */
    public function orderPayload(): array
    {
        return [
            'items' => $this->input('items'),
            'fulfilment_type' => $this->input('fulfilment_type'),
            'delivery_address' => $this->input('delivery_address'),
        ];
    }

    /** The signed-in user, or null for guests. */
    public function resolvedUser(): ?User
    {
        /** @var User|null */
        return $this->user();
    }
}
