import type { ComputedRef, InjectionKey } from 'vue'
import { inject, onMounted, onUnmounted } from 'vue'
import { createTonePlayer } from '@/modules/core/audio/tonePlayer'
import { PosSoundAction } from '@/modules/pos/enums/posSound'
import type { PosSound } from '@/modules/pos/enums/posSound'

export interface PosSoundSettings {
  enabled: boolean
  volume: number
  actions: Record<PosSoundAction, { enabled: boolean; sound: PosSound }>
}

export interface PosSoundController {
  play: (action: PosSoundAction) => void
}

export const posSoundKey: InjectionKey<PosSoundController> = Symbol('pos-sound')

const silentController: PosSoundController = { play: () => undefined }

export function createPosSoundController(settings: ComputedRef<PosSoundSettings | undefined>) {
  const player = createTonePlayer()

  function play(action: PosSoundAction): void {
    const config = settings.value
    const actionConfig = config?.actions?.[action]
    if (!config?.enabled || !actionConfig?.enabled) return
    player.play(actionConfig.sound, config.volume)
  }

  function handleInteraction(event: Event): void {
    const target = event.target instanceof Element ? event.target : null
    const control = target?.closest<HTMLElement>(
      'button, [role="button"], [role="option"], .v-list-item, input[type="checkbox"], input[type="radio"]',
    )
    if (!control || control.matches(':disabled, [aria-disabled="true"]')) return
    const action = control.dataset.posSoundAction as PosSoundAction | undefined
    play(action || PosSoundAction.Interaction)
  }

  onMounted(() => {
    document.addEventListener('click', handleInteraction, true)
    window.addEventListener('keydown', player.unlock, { once: true })
  })

  onUnmounted(() => {
    document.removeEventListener('click', handleInteraction, true)
    window.removeEventListener('keydown', player.unlock)
    player.close()
  })

  return { play }
}

export function usePosSound(): PosSoundController {
  return inject(posSoundKey, silentController)
}
