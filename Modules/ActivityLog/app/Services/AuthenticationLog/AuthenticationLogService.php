<?php

namespace Modules\ActivityLog\Services\AuthenticationLog;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\ActivityLog\Models\AuthenticationLog;

class AuthenticationLogService implements AuthenticationLogServiceInterface
{
    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return AuthenticationLog::query()
            ->filters($filters)
            ->sortBy($sorts)
            ->with(['authenticatable']);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'authentication_logs';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.authentication_logs.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return AuthenticationLog::class;
    }

    /** {@inheritDoc} */
    public function get(array $filters, ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate());
    }

    /** {@inheritDoc} */
    public function show(int $id): AuthenticationLog
    {
        return AuthenticationLog::query()->with(['authenticatable'])->findOrFail($id);
    }
}
