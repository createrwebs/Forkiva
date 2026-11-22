<?php

namespace Modules\Notification\Http\Requests\Api\V1;

use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;

class UpdateNotificationWorkflowRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'channels' => ['required', 'array'],
            'channels.*' => ['array'],
            'channels.*.*' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('channels', []) as $roles) {
                if (! is_array($roles)) {
                    continue;
                }

                foreach ($roles as $enabled) {
                    if (filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }
                }
            }

            $validator->errors()->add(
                'channels',
                __('notification::notification_workflows.errors.at_least_one_recipient')
            );
        });
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'notification::attributes.notification_workflows';
    }
}
