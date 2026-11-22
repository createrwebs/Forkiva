<?php

namespace Modules\Notification\Http\Requests\Api\V1;

use Illuminate\Validation\Rules\Enum;
use Modules\Core\Http\Requests\Request;
use Modules\Notification\Enums\NotificationContext;
use Modules\Notification\Support\NotificationSearch;

class IndexNotificationRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:'.NotificationSearch::MIN_LENGTH, 'max:255'],
            'status' => ['nullable', 'in:all,unread,read'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'app_context' => ['nullable', new Enum(NotificationContext::class)],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'notification::attributes.notifications';
    }
}
