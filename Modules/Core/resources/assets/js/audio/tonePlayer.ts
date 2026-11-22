export interface ToneStep {
  frequency: number
  duration: number
  gap?: number
  type?: OscillatorType
  volume?: number
}

type AudioWindow = Window &
  typeof globalThis & {
    webkitAudioContext?: typeof AudioContext
  }

export const TONE_PATTERNS: Record<string, ToneStep[]> = {
  tap: [{ frequency: 520, duration: 0.035, type: 'sine', volume: 0.45 }],
  pop: [
    { frequency: 540, duration: 0.045, gap: 0.015, type: 'sine', volume: 0.65 },
    { frequency: 760, duration: 0.065, type: 'sine', volume: 0.55 },
  ],
  tick: [{ frequency: 690, duration: 0.045, type: 'triangle', volume: 0.5 }],
  remove: [
    { frequency: 520, duration: 0.055, gap: 0.01, type: 'triangle', volume: 0.5 },
    { frequency: 360, duration: 0.075, type: 'triangle', volume: 0.45 },
  ],
  open: [
    { frequency: 420, duration: 0.04, gap: 0.01, volume: 0.4 },
    { frequency: 620, duration: 0.055, volume: 0.45 },
  ],
  success: [
    { frequency: 523, duration: 0.07, gap: 0.025, volume: 0.65 },
    { frequency: 659, duration: 0.07, gap: 0.025, volume: 0.6 },
    { frequency: 784, duration: 0.12, volume: 0.55 },
  ],
  cash: [
    { frequency: 880, duration: 0.045, gap: 0.02, type: 'triangle', volume: 0.6 },
    { frequency: 1175, duration: 0.055, gap: 0.02, type: 'triangle', volume: 0.55 },
    { frequency: 1568, duration: 0.1, type: 'sine', volume: 0.45 },
  ],
  warning: [
    { frequency: 440, duration: 0.08, gap: 0.04, type: 'triangle', volume: 0.55 },
    { frequency: 440, duration: 0.08, type: 'triangle', volume: 0.55 },
  ],
  error: [
    { frequency: 330, duration: 0.09, gap: 0.025, type: 'sawtooth', volume: 0.35 },
    { frequency: 247, duration: 0.12, type: 'sawtooth', volume: 0.3 },
  ],
}

export function createTonePlayer() {
  let context: AudioContext | null = null

  function getContext(): AudioContext | null {
    if (context) return context
    if (typeof window === 'undefined') return null

    const AudioContextConstructor =
      window.AudioContext || (window as AudioWindow).webkitAudioContext
    if (!AudioContextConstructor) return null

    context = new AudioContextConstructor()
    return context
  }

  async function unlock(): Promise<void> {
    const audio = getContext()
    if (audio?.state === 'suspended') await audio.resume().catch(() => undefined)
  }

  function play(name: string | null | undefined, volumePercent = 65): void {
    const audio = getContext()
    const pattern = TONE_PATTERNS[name || '']
    if (!audio || !pattern?.length) return

    const masterVolume = Math.min(1, Math.max(0, Number(volumePercent) / 100))
    void unlock().then(() => {
      let cursor = audio.currentTime
      pattern.forEach((step) => {
        const oscillator = audio.createOscillator()
        const gain = audio.createGain()
        const start = cursor
        const end = start + step.duration

        oscillator.type = step.type ?? 'sine'
        oscillator.frequency.setValueAtTime(step.frequency, start)
        gain.gain.setValueAtTime(0.0001, start)
        gain.gain.exponentialRampToValueAtTime(
          Math.max(0.0001, masterVolume * 0.12 * (step.volume ?? 1)),
          start + 0.008,
        )
        gain.gain.exponentialRampToValueAtTime(0.0001, end)
        oscillator.connect(gain)
        gain.connect(audio.destination)
        oscillator.start(start)
        oscillator.stop(end + 0.015)
        cursor = end + (step.gap ?? 0.025)
      })
    })
  }

  function close(): void {
    context?.close().catch(() => undefined)
    context = null
  }

  return { close, play, unlock }
}
