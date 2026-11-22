<?php

namespace Modules\Pos\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\BranchImportDefinition;
use Modules\Pos\Models\PosRegister;
use Modules\Printer\Models\Printer;

class PosRegisterImportDefinition extends BranchImportDefinition
{
    /** {@inheritDoc} */
    public function fields(): array
    {
        $fields = [
            ['key' => 'id', 'label' => __('admin::admin.table.id'), 'type' => 'integer', 'required' => false],
            ...$this->translatedFields('name', __('pos::pos_registers.table.name')),
            $this->branchField(),
            ['key' => 'code', 'label' => __('pos::pos_registers.table.code'), 'type' => 'text', 'required' => true],
            ...$this->translatedFields('note', __('pos::attributes.pos_registers.note'), false),
        ];
        $assignedBranchId = $this->branches->assignedBranchId();
        $printers = Printer::query()
            ->where('is_active', true)
            ->when($assignedBranchId, fn ($query) => $query->where('branch_id', $assignedBranchId))
            ->when($assignedBranchId === null, fn ($query) => $query->with('branch:id,name'))
            ->orderBy('id')
            ->get(['id', 'name', 'branch_id'])
            ->map(fn (Printer $printer) => [
                'value' => (string) $printer->id,
                'label' => $assignedBranchId === null
                    ? $printer->name.' ('.$printer->branch?->name.')'
                    : $printer->name,
                'matches' => ['branch_id' => (string) $printer->branch_id],
            ])->all();

        foreach (['invoice', 'bill', 'waiter', 'delivery'] as $printer) {
            $fields[] = [
                'key' => "{$printer}_printer_id",
                'label' => __("pos::pos_registers.table.{$printer}_printer"),
                'type' => 'integer',
                'required' => false,
                'options' => $printers,
            ];
        }
        $fields[] = ['key' => 'is_active', 'label' => __('admin::admin.table.activation'), 'type' => 'boolean', 'required' => true];

        return $fields;
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.pos_registers.import'), 403);
        $this->authorizeBranch($import);
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));
        $code = mb_strtolower(trim((string) ($data['code'] ?? '')));

        return $id !== '' ? 'id:'.hash('sha256', $id) : ($code === '' ? null : 'code:'.hash('sha256', $code));
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        $fields = ['id', 'name', 'note', 'branch_id', 'code', 'is_active', 'invoice_printer_id', 'bill_printer_id', 'waiter_printer_id', 'delivery_printer_id'];
        foreach ($this->otherLocales() as $locale) {
            $fields[] = "name_$locale";
            $fields[] = "note_$locale";
        }
        $data = $this->normalize($data, $fields);
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }
        $rules = [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'note' => 'nullable|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id,deleted_at,NULL',
            'code' => ['required', 'string', 'max:50'],
            'is_active' => 'required|boolean',
        ];
        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
            $rules["note_$locale"] = 'nullable|string|max:255';
        }
        foreach (['invoice', 'bill', 'waiter', 'delivery'] as $printer) {
            $rules["{$printer}_printer_id"] = ['nullable', 'integer', Rule::exists('printers', 'id')->where('branch_id', $data['branch_id'])->where('is_active', 1)];
        }
        $errors = Validator::make($data, $rules)->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }
        $this->resolveBranch($data);
        $existing = ! empty($data['id'])
            ? PosRegister::query()->withoutGlobalActive()->where('branch_id', $data['branch_id'])->find($data['id'])
            : PosRegister::query()->withoutGlobalActive()->where('code', $data['code'])->first();
        if ($existing && (int) $existing->branch_id !== (int) $data['branch_id']) {
            $errors['code'] = [__('validation.exists', ['attribute' => 'code'])];
        }
        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [__('validation.exists', ['attribute' => 'id'])];
        }
        if (PosRegister::query()->withoutGlobalActive()->where('code', $data['code'])->when($existing, fn ($query) => $query->whereKeyNot($existing->id))->exists()) {
            $errors['code'] = [__('validation.unique', ['attribute' => 'code'])];
        }
        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing);
        if ($modeError) {
            $errors[! empty($data['id']) ? 'id' : 'code'] = [$modeError];
        }
        $permission = $operation === ImportOperation::Update ? 'edit' : 'create';
        if ($operation !== ImportOperation::Invalid && $operation !== ImportOperation::Skip && ! auth()->user()->can("admin.pos_registers.$permission")) {
            $errors['id'] = [__('import::imports.messages.cannot_'.$permission)];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $this->resolveBranch($data);
        $register = $targetId
            ? PosRegister::query()->withoutGlobalActive()->where('branch_id', $data['branch_id'])->findOrFail($targetId)
            : new PosRegister;
        $values = [
            'name' => $this->translations($data, 'name', $targetId ? $register : null, $import->mode),
            'code' => $data['code'],
            'is_active' => (bool) $data['is_active'],
        ];
        if (! $targetId) {
            $values['branch_id'] = $data['branch_id'];
        }
        if (! $targetId || (array_key_exists('note', $data) && ($data['note'] !== null || $import->mode === ImportMode::ReplaceExisting))) {
            $values['note'] = $this->translations($data, 'note', $targetId ? $register : null, $import->mode);
        }
        foreach (['invoice', 'bill', 'waiter', 'delivery'] as $printer) {
            $field = "{$printer}_printer_id";
            if (array_key_exists($field, $data) && ($data[$field] !== null || $import->mode === ImportMode::ReplaceExisting)) {
                $values[$field] = $data[$field];
            }
        }
        $register->fill($values)->save();
    }
}
