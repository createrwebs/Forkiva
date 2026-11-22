import { useToast } from 'vue-toastification'
import { QintrixApiError, QintrixClient } from '@/app/plugins/qintrix'

export function useQintrix() {
  const toast = useToast()
  let client: QintrixClient | undefined

  const initClient = (baseUrl: string, apiKey: string) => {
    client = new QintrixClient({ baseUrl, apiKey })
  }

  const isSetup = computed<boolean>(() => !!client)

  const ensureClient = (): QintrixClient => {
    if (!client) {
      throw new Error('Qintrix client is not initialized. Call initClient() first.')
    }

    return client
  }

  const health = async () => await ensureClient().health.get()
  const printers = async () => await ensureClient().printers.list()

  const jobCreator =
    (target: QintrixClient) =>
    async (
      content: string,
      printerId: string,
      paperSize: string,
      referenceId?: string,
      referenceType = 'order',
      contentType: 'image' | 'html' | 'pdf' | 'text' = 'pdf',
      showErrorMessage = true,
    ) => {
      try {
        const payload = contentType === 'image' ? { content, mimeType: 'image/png' } : { content }
        const job = await target.jobs.create({
          printerId,
          contentType,
          copies: 1,
          payload,
          options: { paperSize, silent: true },
          meta: { referenceType, referenceId, source: 'website' },
        })
        return job
      } catch (error) {
        if (error instanceof QintrixApiError) {
          if (showErrorMessage) {
            toast.error(error.message)
          }
          return
        }
        throw error
      }
    }

  const createJob = (...args: Parameters<ReturnType<typeof jobCreator>>) =>
    jobCreator(ensureClient())(...args)
  const captureJobCreator = () => jobCreator(ensureClient())

  return { initClient, isSetup, health, printers, createJob, captureJobCreator }
}
