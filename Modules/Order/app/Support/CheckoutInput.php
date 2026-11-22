<?php

namespace Modules\Order\Support;

final class CheckoutInput
{
    /**
     * Allow selection identifiers and channel-specific metadata; prohibit client pricing.
     */
    public static function rules(bool $public = false): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['required', 'array:line_id,product_id,qty,options,order_product_id,actions'],
            'items.*.line_id' => ['required', 'string', 'max:100', 'distinct'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:0', 'max:'.($public ? '99' : '9999')],
            'items.*.options' => ['present', 'array'],
            'items.*.order_product_id' => [$public ? 'prohibited' : 'nullable', 'integer'],
            'items.*.actions' => [$public ? 'prohibited' : 'nullable', 'array:cancel,refund'],
            'items.*.actions.*' => ['integer', 'min:1', 'max:9999'],
            'customer_id' => [$public ? 'prohibited' : 'nullable', 'integer'],
            'discount_id' => [$public ? 'prohibited' : 'nullable', 'integer'],
            'voucher_code' => ['nullable', 'string', 'max:150'],
            'gift_id' => [$public ? 'prohibited' : 'nullable', 'integer'],
            'delivery' => [$public ? 'prohibited' : 'nullable', 'array:customer_address_id,delivery_method,payment_collection_channel,note'],
            'delivery.customer_address_id' => ['nullable', 'integer'],
            'delivery.delivery_method' => ['nullable', 'string', 'max:50'],
            'delivery.payment_collection_channel' => ['nullable', 'string', 'max:50'],
            'delivery.note' => ['nullable', 'string', 'max:1000'],
            'edit_version' => [$public ? 'prohibited' : 'nullable', 'string', 'size:64'],
            'quote_token' => ['nullable', 'string', 'max:4096'],
            'displayed_pricing' => [$public ? 'prohibited' : 'nullable', 'string', 'max:131072'],
            'idempotency_key' => ['nullable', 'uuid'],
            'total' => ['prohibited'], 'subtotal' => ['prohibited'], 'subTotal' => ['prohibited'],
            'prices' => ['prohibited'], 'taxes' => ['prohibited'], 'charges' => ['prohibited'],
            'source' => ['prohibited'], 'currency' => ['prohibited'],
        ];
    }

    /**
     * Fingerprint the operation independently of renewable quote and retry identifiers.
     */
    public static function hash(array $data): string
    {
        unset($data['quote_token'], $data['idempotency_key']);

        return hash('sha256', json_encode(self::canonical($data), JSON_THROW_ON_ERROR));
    }

    /**
     * Sort associative keys recursively while preserving meaningful list order.
     */
    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}
