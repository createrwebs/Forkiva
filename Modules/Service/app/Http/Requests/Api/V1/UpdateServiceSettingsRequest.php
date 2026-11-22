<?php

namespace Modules\Service\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;

class UpdateServiceSettingsRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return app(ServiceRegistryServiceInterface::class)->rules(
            category: (string) $this->route('category'),
            service: (string) $this->route('service'),
            data: $this->all()
        );
    }

    /** {@inheritDoc} */
    public function attributes(): array
    {
        return app(ServiceRegistryServiceInterface::class)->attributes(
            category: (string) $this->route('category'),
            service: (string) $this->route('service')
        );
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'service::attributes.settings';
    }
}
