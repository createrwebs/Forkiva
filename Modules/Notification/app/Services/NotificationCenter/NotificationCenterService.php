<?php

namespace Modules\Notification\Services\NotificationCenter;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Notification\Support\NotificationSearch;

class NotificationCenterService implements NotificationCenterServiceInterface
{
    /** {@inheritDoc} */
    public function find(object $notifiable, string $id, array $filters = []): ?DatabaseNotification
    {
        return $this->query($notifiable, $filters)
            ->whereKey($id)
            ->first();
    }

    /**
     * Build the current-user notification query.
     */
    protected function query(object $notifiable, array $filters = []): Builder
    {
        $query = DatabaseNotification::query()
            ->select([
                'id',
                'type',
                'notifiable_type',
                'notifiable_id',
                'data',
                'read_at',
                'created_at',
                'updated_at',
            ])
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', $notifiable->getKey());

        $this->applyAppContext($query, (string) ($filters['app_context'] ?? ''));

        return $query;
    }

    /**
     * Apply optional app-context filtering to notification data.
     */
    protected function applyAppContext(Builder $query, string $appContext): void
    {
        if (blank($appContext)) {
            return;
        }

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereRaw('json_extract(data, ?) = ?', ['$.app_context', $appContext]);

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw("data::jsonb #>> '{app_context}' = ?", [$appContext]);

            return;
        }

        $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) = ?', ['$.app_context', $appContext]);
    }

    /** {@inheritDoc} */
    public function unreadCount(object $notifiable, array $filters = []): int
    {
        return $this->query($notifiable, $filters)
            ->whereNull('read_at')
            ->count();
    }

    /** {@inheritDoc} */
    public function recent(object $notifiable, int $limit = 5, array $filters = []): array
    {
        return $this->query($notifiable, $filters)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /** {@inheritDoc} */
    public function get(object $notifiable, array $filters = []): LengthAwarePaginator
    {
        $query = $this->query($notifiable, $filters);

        match ($filters['status'] ?? 'all') {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? Forkiva::paginate()))
            ->withQueryString();
    }

    /**
     * Apply localized notification search.
     */
    protected function applySearch(Builder $query, string $search): void
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $fullTextQuery = NotificationSearch::booleanQuery($search);

            if ($fullTextQuery === '') {
                $query->whereRaw('0 = 1');

                return;
            }

            $query->whereFullText('search_text', $fullTextQuery, ['mode' => 'boolean']);

            return;
        }

        $like = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $builder) use ($driver, $like) {
            foreach ($this->searchLocales() as $locale) {
                foreach (['title', 'subject', 'content'] as $field) {
                    $this->applyLocalizedSearchCondition(
                        builder: $builder,
                        driver: $driver,
                        locale: $locale,
                        field: $field,
                        like: $like,
                    );
                }
            }
        });
    }

    /**
     * Get notification locale search fallbacks.
     *
     * @return array<int, string>
     */
    protected function searchLocales(): array
    {
        return array_values(array_unique(array_filter([
            locale(),
            setting('default_locale', 'en'),
            'en',
            ...supportedLocaleKeys(),
        ])));
    }

    /**
     * Apply a single localized JSON search condition for the active database driver.
     */
    protected function applyLocalizedSearchCondition(
        Builder $builder,
        string $driver,
        string $locale,
        string $field,
        string $like
    ): void {
        if ($driver === 'sqlite') {
            $builder->orWhereRaw(
                'LOWER(json_extract(data, ?)) LIKE ?',
                ["$.translations.$locale.$field", $like]
            );

            return;
        }

        if ($driver === 'pgsql') {
            $builder->orWhereRaw(
                "LOWER(data::jsonb #>> '{translations,$locale,$field}') LIKE ?",
                [$like]
            );

            return;
        }

        $builder->orWhereRaw(
            'LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, ?))) LIKE ?',
            ["$.translations.$locale.$field", $like]
        );
    }

    /** {@inheritDoc} */
    public function markRead(object $notifiable, string $id, array $filters = []): DatabaseNotification
    {
        $notification = $this->findOrFail($notifiable, $id, $filters);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification->refresh();
    }

    /** {@inheritDoc} */
    public function findOrFail(object $notifiable, string $id, array $filters = []): DatabaseNotification
    {
        return $this->query($notifiable, $filters)
            ->whereKey($id)
            ->firstOrFail();
    }

    /** {@inheritDoc} */
    public function markAllRead(object $notifiable, array $filters = []): int
    {
        return $this->query($notifiable, $filters)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
