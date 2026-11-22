<?php

namespace Modules\Export\Support;

use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use JsonSerializable;
use Modules\Support\Enums\DateTimeFormat;
use Modules\Support\Money;
use Stringable;

readonly class ExportValueNormalizer
{
    /**
     * Normalize an export value into a scalar string/number.
     *
     * @param  array<string,mixed>  $column
     */
    public function normalize(mixed $value, array $column = []): string|int|float
    {
        if (is_null($value)) {
            return '';
        }

        return match ($column['type'] ?? null) {
            'boolean' => $this->normalizeBoolean((bool) $value),
            'datetime' => $this->normalizeDateTime($value),
            'date' => $this->normalizeDate($value),
            'time' => $this->normalizeTime($value),
            'money' => $this->normalizeMoney($value),
            default => $this->normalizeDefault($value),
        };
    }

    /**
     * Normalize booleans.
     */
    private function normalizeBoolean(bool $value): string
    {
        return $value ? __('admin::admin.table.yes') : __('admin::admin.table.no');
    }

    /**
     * Normalize datetime values.
     */
    private function normalizeDateTime(mixed $value): string|int|float
    {
        if ($value instanceof Carbon) {
            return dateTimeFormat($value);
        }

        return $this->normalizeDefault($value);
    }

    /**
     * Normalize default mixed values.
     */
    private function normalizeDefault(mixed $value): string|int|float
    {
        if ($value instanceof Money) {
            return $value->amount();
        }

        if ($value instanceof Carbon) {
            return dateTimeFormat($value);
        }

        if ($value instanceof BackedEnum) {
            return method_exists($value, 'trans') ? $value->trans() : (method_exists($value, 'toTrans') ? $value->toTrans()['name'] ?? $value->value : $value->value);
        }

        if (is_bool($value)) {
            return $this->normalizeBoolean($value);
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        if ($value instanceof Collection) {
            return $this->normalizeArray($value->values()->all());
        }

        if ($value instanceof JsonSerializable) {
            return json_encode($value->jsonSerialize(), JSON_UNESCAPED_UNICODE) ?: '';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Normalize array values.
     *
     * @param  array<mixed>  $value
     */
    private function normalizeArray(array $value): string|int|float
    {
        if (! array_is_list($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        $items = array_map(function (mixed $item): string|int|float {
            if (is_null($item)) {
                return '';
            }

            return $this->normalizeDefault($item);
        }, $value);

        return implode(', ', array_values(array_filter($items, fn ($item) => $item !== '')));
    }

    /**
     * Normalize date values.
     */
    private function normalizeDate(mixed $value): string|int|float
    {
        if ($value instanceof Carbon) {
            return dateTimeFormat($value, DateTimeFormat::Date);
        }

        return $this->normalizeDefault($value);
    }

    /**
     * Normalize time values.
     */
    private function normalizeTime(mixed $value): string|int|float
    {
        return dateTimeFormat($value, DateTimeFormat::Time);
    }

    /**
     * Normalize money values.
     */
    private function normalizeMoney(mixed $value): string|int|float
    {
        return $value instanceof Money ? $value->amount() : $this->normalizeDefault($value);
    }
}
