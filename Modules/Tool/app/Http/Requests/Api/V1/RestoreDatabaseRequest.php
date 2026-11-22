<?php

namespace Modules\Tool\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class RestoreDatabaseRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:sql,txt,sqlite,sqlite3,db',
                'max:'.config('tool.database.max_upload_kb', 512000),
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'tool::attributes.database';
    }
}
