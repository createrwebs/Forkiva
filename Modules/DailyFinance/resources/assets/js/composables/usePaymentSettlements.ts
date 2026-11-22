import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/modules/core/api/errors'
import { settlementHistory, settlementMeta } from '../api/paymentSettlement.api'
import type {
  ElectronicMethod,
  PaymentSettlement,
  SettlementMeta,
  SettlementPage,
  SettlementStatusFilter,
} from '../contracts/paymentSettlement'

export function usePaymentSettlements(
  branchId: () => number,
  initialStatus: SettlementStatusFilter | null = null,
) {
  const { t } = useI18n()
  const meta = ref<SettlementMeta | null>(null)
  const history = ref<SettlementPage<PaymentSettlement> | null>(null)
  const status = ref<SettlementStatusFilter | null>(initialStatus)
  const method = ref<ElectronicMethod | null>(null)
  const loading = ref(false)
  const error = ref('')
  let revision = 0
  let controller: AbortController | null = null

  async function load(page = 1) {
    const current = ++revision
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    error.value = ''
    history.value = null
    if (meta.value?.branch_id !== branchId()) meta.value = null
    try {
      const [choices, records] = await Promise.all([
        settlementMeta(branchId(), signal),
        settlementHistory(
          { branch_id: branchId(), method: method.value, status: status.value, page },
          signal,
        ),
      ])
      if (current !== revision) return
      meta.value = choices.data.body
      history.value = records.data.body
    } catch (failure: unknown) {
      if (current === revision && !signal.aborted)
        error.value =
          getApiErrorMessage(failure) || t('dailyfinance::payment_settlements.load_error')
    } finally {
      if (current === revision) loading.value = false
    }
  }

  watch(
    [branchId, status, method],
    () => {
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })

  return { meta, history, status, method, loading, error, load }
}
