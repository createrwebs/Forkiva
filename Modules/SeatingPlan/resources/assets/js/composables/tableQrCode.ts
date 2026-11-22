import { nextTick, type Ref, unref } from 'vue'

type MaybeRef<T> = T | Ref<T>

interface TableQrCodeActionsOptions {
  element: Ref<HTMLElement | null>
  fileName: MaybeRef<string>
  title: MaybeRef<string>
  subtitle?: MaybeRef<string | null | undefined>
  link: MaybeRef<string>
}

function escapeHtml(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function normalizeFileName(value: string): string {
  const name = value
    .trim()
    .replace(/[\\/:*?"<>|]+/g, '-')
    .replace(/\s+/g, '-')

  return name || 'table-qr-code'
}

function dataUrlToObjectUrl(source: string): string | null {
  const match = source.match(/^data:([^;,]+)(;base64)?,(.*)$/)

  if (!match) {
    return null
  }

  const mimeType = match[1] || 'image/png'
  const isBase64 = Boolean(match[2])
  const encodedPayload = match[3] || ''
  const payload = isBase64 ? window.atob(encodedPayload) : window.decodeURIComponent(encodedPayload)
  const bytes = new Uint8Array(payload.length)

  for (let index = 0; index < payload.length; index += 1) {
    bytes[index] = payload.charCodeAt(index)
  }

  return window.URL.createObjectURL(new Blob([bytes], { type: mimeType }))
}

function printDocument(source: string, options: TableQrCodeActionsOptions): string {
  return `
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(unref(options.title))}</title>
  <style>
    @page {
      margin: 14mm;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      color: #172033;
      font-family: Inter, Arial, sans-serif;
    }

    body {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .qr-print {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 8mm 0;
      text-align: center;
      page-break-inside: avoid;
    }

    .qr-print__title {
      margin: 0;
      font-size: 22px;
      font-weight: 800;
      line-height: 1.2;
    }

    .qr-print__image {
      display: block;
      width: 250px;
      height: 250px;
      margin-block: 4px;
    }
  </style>
</head>
<body>
  <main class="qr-print">
    <h1 class="qr-print__title">${escapeHtml(unref(options.title))}</h1>
    <img class="qr-print__image" data-print-qr alt="${escapeHtml(unref(options.title))}" src="${escapeHtml(source)}">
  </main>
</body>
</html>`
}

async function waitForRenderedQrImage(element: HTMLElement | null): Promise<HTMLImageElement> {
  for (let attempt = 0; attempt < 20; attempt += 1) {
    await nextTick()

    const image = element?.querySelector<HTMLImageElement>('img[src]')

    if (image?.src) {
      return image
    }

    await new Promise((resolve) => window.setTimeout(resolve, 50))
  }

  throw new Error('qr_image_unavailable')
}

function getRenderedQrImageSource(element: HTMLElement | null): string {
  const image = element?.querySelector<HTMLImageElement>('img[src]')
  const source = image?.currentSrc || image?.src

  if (!source) {
    throw new Error('qr_image_unavailable')
  }

  return source
}

async function waitForImageLoad(image: HTMLImageElement): Promise<void> {
  if (image.complete && image.naturalWidth > 0) {
    return
  }

  await new Promise<void>((resolve, reject) => {
    let timeout = 0

    const cleanup = () => {
      window.clearTimeout(timeout)
      image.removeEventListener('load', onLoad)
      image.removeEventListener('error', onError)
    }

    const onLoad = () => {
      cleanup()
      resolve()
    }

    const onError = () => {
      cleanup()
      reject(new Error('qr_image_load_failed'))
    }

    image.addEventListener('load', onLoad, { once: true })
    image.addEventListener('error', onError, { once: true })

    timeout = window.setTimeout(() => {
      cleanup()
      reject(new Error('qr_image_load_timeout'))
    }, 3000)
  })
}

export function useTableQrCodeActions(options: TableQrCodeActionsOptions) {
  const getQrImageSource = async () => {
    const image = await waitForRenderedQrImage(options.element.value)

    await waitForImageLoad(image)

    return image.currentSrc || image.src
  }

  const downloadQrCode = () => {
    const source = getRenderedQrImageSource(options.element.value)
    const anchor = document.createElement('a')
    const objectUrl = dataUrlToObjectUrl(source)

    anchor.href = objectUrl || source
    anchor.download = `${normalizeFileName(unref(options.fileName))}.png`
    document.body.append(anchor)
    anchor.click()
    anchor.remove()

    if (objectUrl) {
      window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 30_000)
    }
  }

  const printQrCode = async () => {
    const source = await getQrImageSource()
    const frame = document.createElement('iframe')

    frame.setAttribute('aria-hidden', 'true')
    frame.style.position = 'fixed'
    frame.style.insetBlockStart = '-10000px'
    frame.style.insetInlineStart = '0'
    frame.style.inlineSize = '420px'
    frame.style.blockSize = '640px'
    frame.style.opacity = '0'
    frame.style.pointerEvents = 'none'
    document.body.append(frame)

    const frameDocument = frame.contentDocument
    const frameWindow = frame.contentWindow

    if (!frameDocument || !frameWindow) {
      frame.remove()
      throw new Error('qr_print_frame_unavailable')
    }

    frameDocument.open()
    frameDocument.write(printDocument(source, options))
    frameDocument.close()

    const printImage = frameDocument.querySelector<HTMLImageElement>('[data-print-qr]')

    if (printImage) {
      await waitForImageLoad(printImage)
    }

    frameWindow.focus()
    frameWindow.addEventListener('afterprint', () => frame.remove(), { once: true })
    frameWindow.print()
    window.setTimeout(() => frame.remove(), 60_000)
  }

  return {
    downloadQrCode,
    printQrCode,
  }
}
