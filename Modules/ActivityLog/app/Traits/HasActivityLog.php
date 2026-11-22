<?php

namespace Modules\ActivityLog\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\ActivityLog\Support\SensitiveProperties;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasActivityLog
{
    use LogsActivity;

    /**
     * Get the description for an activity log event.
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        return "admin::messages.resource_$eventName";
    }

    /**
     * Get the options for the model's activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    /**
     * Manipulate the activity log before it is saved.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (auth()->check()) {
            $activity->causer_id = auth()->id();
            $activity->causer_type = get_class(auth()->user());
        }

        $activity->batch_uuid = Str::uuid();
        $activity->event = $eventName;
        $activity->log_name = $this->getLogName($eventName);
        $this->storeProperties($activity, request());
    }

    /**
     *  Get the log name for the model.
     */
    public function getLogName(string $eventName): string
    {
        return str(class_basename(static::class))->plural()->snake()->lower().'.'.$eventName;
    }

    /**
     * Manipulate the property log before it is saved.
     */
    private function storeProperties(Activity $activity, Request $request): void
    {
        $classNamespace = static::class;
        $moduleName = str(explode('\\', $classNamespace)[1]);
        $modelNameLowerCase = str(class_basename($classNamespace))->snake()->lower();

        $excluded = array_fill_keys([
            'created_at',
            'updated_at',
            'deleted_at',
            ...$this->getHidden(),
            ...SensitiveProperties::FIELDS,
        ], true);
        // Filter before applying casts: getOriginal() can decrypt secret attributes.
        $oldAttributes = [];
        foreach (array_diff_key($this->getRawOriginal(), $excluded) as $field => $value) {
            $oldAttributes[$field] = $this->getOriginal($field);
        }
        $newAttributes = array_diff_key($this->getAttributes(), $excluded);

        foreach ($newAttributes as $field => $newValue) {
            if (is_string($newValue) && Str::isJson($newValue)) {
                $newAttributes[$field] = json_decode($newValue, true);
            }
        }

        $activity->properties = SensitiveProperties::redact([
            'old' => $oldAttributes,
            'attributes' => $newAttributes,
            'info' => [
                'ip' => $request->ip(),
                'http_method' => $request->method(),
                'user_agent' => $request->header('User-Agent'),
            ],
            'trans_params' => [
                'resource' => "{$moduleName->lower()}::{$modelNameLowerCase->plural()}.$modelNameLowerCase",
            ],
        ]);
    }
}
