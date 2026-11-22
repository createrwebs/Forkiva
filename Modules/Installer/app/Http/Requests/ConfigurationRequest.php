<?php

namespace Modules\Installer\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Currency\Currency;
use Modules\Support\Country;
use Modules\Support\Locale;
use Modules\Support\Security\PasskeyConfigurationValidator;
use Modules\Support\TimeZone;

class ConfigurationRequest extends Request
{
    /**
     * Get validation rules for dashboard filters and widget options.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            'app_url' => ['required', 'url:http,https', 'max:255', 'not_regex:/[\x00-\x20\x7F]/'],
            'supported_countries' => ['required', 'array', 'min:1'],
            'supported_countries.*' => ['required', 'string', 'distinct', Rule::in(Country::codes())],
            'default_country' => ['required', 'string', Rule::in((array) $this->input('supported_countries', []))],
            'supported_locales' => ['required', 'array', 'min:1'],
            'supported_locales.*' => ['required', 'string', 'distinct', Rule::in(Locale::codes())],
            'default_locale' => ['required', 'string', Rule::in((array) $this->input('supported_locales', []))],
            'default_timezone' => ['required', 'string', Rule::in(TimeZone::keys())],
            'supported_currencies' => ['required', 'array', 'min:1'],
            'supported_currencies.*' => ['required', 'string', 'distinct', Rule::in(Currency::codes())],
            'default_currency' => ['required', 'string', Rule::in((array) $this->input('supported_currencies', []))],
            'passkeys_enabled' => ['required', 'boolean'],
            'passkeys_relying_party_id' => ['nullable', 'required_if:passkeys_enabled,true', 'string', 'max:253'],
            'passkeys_allowed_origins' => ['present', 'array', 'max:20', 'required_if:passkeys_enabled,true'],
            'passkeys_allowed_origins.*' => ['required', 'string', 'max:255', 'distinct', 'not_regex:/[\x00-\x20\x7F]/'],
            'cache_enabled' => ['required', 'boolean'],
            'cache_store' => ['required', Rule::in(['file', 'database'])],
        ];
    }

    /**
     * Validate the bounded synchronous reporting window.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $url = $this->string('app_url')->toString();
            $parts = parse_url($url);
            if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['path'])) {
                $validator->errors()->add('app_url', 'Enter an exact website origin without a path or trailing slash, for example https://pos.example.com.');
            }
            if ($this->boolean('passkeys_enabled')) {
                if (! PasskeyConfigurationValidator::valid($this->input('passkeys_relying_party_id'), $this->input('passkeys_allowed_origins'))) {
                    $validator->errors()->add('passkeys_relying_party_id', 'Use a host-only relying-party ID that matches every allowed origin. Origins require HTTPS, except local development addresses.');
                }
                if (! in_array($url, $this->input('passkeys_allowed_origins'), true)) {
                    $validator->errors()->add('passkeys_allowed_origins', 'Include the public application URL in the allowed origins.');
                }
            }
        }];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'installer::validation.attributes';
    }
}
