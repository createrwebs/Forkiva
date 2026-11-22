<?php

namespace Modules\Core\Contracts;

interface FinancialPostingStatusReaderInterface
{
    /**
     * Read the latest optional Accounting posting state for a source event.
     *
     * Operational modules use this contract only for audit presentation. They
     * must not depend on Accounting models or use the result to mutate source
     * records.
     *
     * @param  string  $sourceType  Stable source type.
     * @param  int  $sourceId  Operational source identifier.
     * @param  string  $event  Stable source event.
     * @return array{id: string, name: string, color: string|null, journal_entry_id: int|null}|null
     */
    public function latest(string $sourceType, int $sourceId, string $event): ?array;

    /**
     * Read the latest posting states for multiple source events in one query.
     *
     * Results are keyed as "{source_type}:{source_id}:{event}".
     *
     * @param  array<int, array{source_type: string, source_id: int, event: string}>  $lookups
     * @return array<string, array{id: string, name: string, color: string|null, journal_entry_id: int|null}>
     */
    public function latestMany(array $lookups): array;
}
