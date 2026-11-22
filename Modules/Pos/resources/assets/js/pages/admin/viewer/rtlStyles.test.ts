import { readFileSync } from 'node:fs'
import { compileStyle, parse } from '@vue/compiler-sfc'
import { compileString } from 'sass-embedded'
import { afterEach, describe, expect, it } from 'vitest'

const scopeId = 'data-v-rtl-regression'
const components = [
  {
    file: 'ConfigurationErrors/Alert.vue',
    markup: `<button class="configuration-alert__button" ${scopeId}>
      <i class="configuration-alert__arrow" ${scopeId}></i>
    </button>`,
  },
  {
    file: 'Pos/Dialogs/Rewards/RewardItem.vue',
    // Vuetify renders the icon inside the scoped button component.
    markup: `<button class="reward-item__action" ${scopeId}>
      <i class="v-icon"></i>
    </button>`,
  },
]

afterEach(() => {
  document.head.querySelectorAll('style').forEach((style) => style.remove())
  document.body.innerHTML = ''
  document.documentElement.removeAttribute('dir')
})

describe.each(components)('POS RTL styles in $file', ({ file, markup }) => {
  it.each(['rtl', 'ltr'])(
    'keeps the page upright in %s and only rotates RTL arrows',
    (direction) => {
      const source = readFileSync(new URL(file, import.meta.url), 'utf8')
      const { descriptor } = parse(source)
      const style = descriptor.styles.find((block) => block.scoped)!
      const compiled = compileStyle({
        source: compileString(style.content).css,
        filename: file,
        id: scopeId,
        scoped: true,
      })
      expect(compiled.errors).toEqual([])

      document.documentElement.dir = direction
      document.body.innerHTML = `<main ${scopeId}>${markup}</main>`
      const stylesheet = document.createElement('style')
      stylesheet.textContent = compiled.code
      document.head.append(stylesheet)

      for (const element of [
        document.documentElement,
        document.body,
        document.querySelector('main')!,
        document.querySelector('button')!,
      ]) {
        expect(getComputedStyle(element).transform || 'none').toBe('none')
      }
      expect(getComputedStyle(document.querySelector('i')!).transform || 'none').toBe(
        direction === 'rtl' ? 'rotate(180deg)' : 'none',
      )
    },
  )
})
