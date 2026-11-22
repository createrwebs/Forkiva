<?php

namespace Modules\UserPreference\Http\Requests\Api\V1;

use Closure;
use JsonException;
use Modules\Core\Http\Requests\Request;

class StoreUserPreferenceRequest extends Request
{
    /**
     * Limit user-supplied values to a bounded JSON payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => [
                'present',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        $encoded = json_encode($value, JSON_THROW_ON_ERROR, 16);
                    } catch (JsonException) {
                        $fail(__('userpreference::messages.invalid_value'));

                        return;
                    }

                    if (strlen($encoded) > 16384) {
                        $fail(__('userpreference::messages.value_too_large'));
                    }
                },
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'userpreference::messages.attributes';
    }
}
