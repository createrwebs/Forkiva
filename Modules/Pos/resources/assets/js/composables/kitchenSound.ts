import type { ComputedRef } from 'vue'
import { onMounted, onUnmounted } from 'vue'

export interface KitchenSoundSettings {
  enabled?: boolean
  new_order?: string | null
  order_updated?: string | null
  volume?: number | null
}

interface ToneStep {
  frequency: number
  duration: number
  gap?: number
}

type AudioWindow = Window &
  typeof globalThis & {
    webkitAudioContext?: typeof AudioContext
  }

const SOUND_PATTERNS: Record<string, ToneStep[]> = {
  chime: [
    { frequency: 880, duration: 0.09, gap: 0.03 },
    { frequency: 1175, duration: 0.14 },
  ],
  bell: [{ frequency: 988, duration: 0.2 }],
  double_beep: [
    { frequency: 720, duration: 0.08, gap: 0.06 },
    { frequency: 720, duration: 0.08 },
  ],
  soft_alert: [
    { frequency: 523, duration: 0.11, gap: 0.04 },
    { frequency: 659, duration: 0.11 },
  ],
}

export function useKitchenSound(settings: ComputedRef<KitchenSoundSettings | null>) {
  let audioContext: AudioContext | null = null

  function getAudioContext(): AudioContext | null {
    if (audioContext) {
      return audioContext
    }

    const AudioContextConstructor =
      window.AudioContext || (window as AudioWindow).webkitAudioContext

    if (!AudioContextConstructor) {
      return null
    }

    audioContext = new AudioContextConstructor()

    return audioContext
  }

  async function unlockAudio(): Promise<void> {
    const context = getAudioContext()

    if (context?.state === 'suspended') {
      await context.resume().catch(() => undefined)
    }
  }

  function playPattern(name: string | null | undefined): void {
    const config = settings.value

    if (!config?.enabled) {
      return
    }

    const context = getAudioContext()

    if (!context) {
      return
    }

    const pattern = SOUND_PATTERNS[name || ''] ?? SOUND_PATTERNS.chime ?? []

    if (pattern.length === 0) {
      return
    }

    const volume = Math.min(1, Math.max(0, Number(config.volume ?? 70) / 100))

    unlockAudio()
      .then(() => {
        let cursor = context.currentTime

        pattern.forEach((step) => {
          const oscillator = context.createOscillator()
          const gain = context.createGain()
          const start = cursor
          const end = start + step.duration

          oscillator.type = 'sine'
          oscillator.frequency.setValueAtTime(step.frequency, start)
          gain.gain.setValueAtTime(0.0001, start)
          gain.gain.exponentialRampToValueAtTime(Math.max(0.0001, volume * 0.18), start + 0.01)
          gain.gain.exponentialRampToValueAtTime(0.0001, end)

          oscillator.connect(gain)
          gain.connect(context.destination)
          oscillator.start(start)
          oscillator.stop(end + 0.02)

          cursor = end + (step.gap ?? 0.04)
        })
      })
      .catch(() => undefined)
  }

  function playNewOrder(): void {
    playPattern(settings.value?.new_order || 'chime')
  }

  function playOrderUpdated(): void {
    playPattern(settings.value?.order_updated || 'double_beep')
  }

  onMounted(() => {
    window.addEventListener('pointerdown', unlockAudio, { once: true })
    window.addEventListener('keydown', unlockAudio, { once: true })
  })

  onUnmounted(() => {
    window.removeEventListener('pointerdown', unlockAudio)
    window.removeEventListener('keydown', unlockAudio)
    audioContext?.close().catch(() => undefined)
    audioContext = null
  })

  return {
    playNewOrder,
    playOrderUpdated,
  }
}
