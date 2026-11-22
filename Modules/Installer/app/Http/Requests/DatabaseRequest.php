<?php

namespace Modules\Installer\Http\Requests;

use Modules\Core\Http\Requests\Request;

class DatabaseRequest extends Request
{
    /**
     * Validate database connection fields for safe environment persistence.
     */
    public function rules(): array
    {
        $literal = ['string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'];

        return [
            'db_connection' => ['sometimes', 'required', 'in:mysql,pgsql'],
            'db_host' => ['required', ...$literal, 'regex:/^[a-zA-Z0-9.\-:\[\]]+$/'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'db_user' => ['required', ...$literal],
            'db_pass' => ['nullable', ...$literal],
            'with_demo' => ['required', 'boolean'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'installer::validation.attributes';
    }
}
