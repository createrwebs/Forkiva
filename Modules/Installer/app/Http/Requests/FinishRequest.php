<?php

namespace Modules\Installer\Http\Requests;

use Modules\Core\Http\Requests\Request;

class FinishRequest extends Request
{
    /**
     * Validate the public HTTP or HTTPS application URL.
     */
    public function rules(): array
    {
        return ['app_url' => ['required', 'url:http,https', 'max:255', 'not_regex:/[\x00-\x20\x7F]/']];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'installer::validation.attributes';
    }
}
