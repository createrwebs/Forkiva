import type { ImportResourceKey } from '@/modules/import/contracts/import.ts'
import { watch } from 'vue'
import { useImportProcessing } from '@/modules/import/stores/importProcessing.ts'

export function useImportRefresh(
  resource: ImportResourceKey,
  refresh: () => void,
  context?: () => Record<string, number | null | undefined>,
) {
  const processing = useImportProcessing()

  watch(
    () => [...processing.completions.values()],
    (items, previous = []) => {
      const previousCounts = new Map(previous.map((item) => [item.id, item.imported_rows]))
      if (
        items.some(
          (item) =>
            item.imported_rows > (previousCounts.get(item.id) ?? 0) &&
            item.resource === resource &&
            Object.entries(context?.() ?? {}).every(
              ([key, value]) => value != null && item.context[key] === value,
            ),
        )
      ) {
        refresh()
      }
    },
    { flush: 'sync' },
  )
}
