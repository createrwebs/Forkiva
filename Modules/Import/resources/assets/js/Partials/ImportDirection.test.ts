import { compileStyle, parse } from '@vue/compiler-sfc'
import { readFileSync } from 'node:fs'
import { compileString } from 'sass-embedded'
import { describe, expect, it } from 'vitest'

const panels = ['ImportUploadPanel.vue', 'ImportColumnMapping.vue']

describe.each(panels)('%s direction styles', (panel) => {
  it.each(['rtl', 'ltr'])(
    'keeps the %s document upright while orienting its arrow',
    (direction) => {
      const filename = `Modules/Import/resources/assets/js/Partials/${panel}`
      const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
      const scope = 'data-v-import-direction-test'
      const stylesheet = document.createElement('style')
      const container = document.createElement('main')
      const arrow = document.createElement('i')
      const previousDirection = document.documentElement.getAttribute('dir')

      arrow.className = 'import-direction-icon'
      arrow.setAttribute(scope, '')
      container.setAttribute(scope, '')
      container.append(arrow)
      stylesheet.textContent = descriptor.styles
        .map((style) => {
          const result = compileStyle({
            filename,
            id: scope,
            source: compileString(style.content).css,
            scoped: style.scoped,
          })
          expect(result.errors).toEqual([])
          return result.code
        })
        .join('\n')

      try {
        document.documentElement.setAttribute('dir', direction)
        document.head.append(stylesheet)
        document.body.append(container)

        expect(getComputedStyle(document.documentElement).transform).not.toContain('rotate')
        expect(getComputedStyle(document.body).transform).not.toContain('rotate')
        expect(getComputedStyle(container).transform).not.toContain('rotate')
        expect(getComputedStyle(arrow).transform).toBe(direction === 'rtl' ? 'rotate(180deg)' : '')
      } finally {
        stylesheet.remove()
        container.remove()
        if (previousDirection === null) document.documentElement.removeAttribute('dir')
        else document.documentElement.setAttribute('dir', previousDirection)
      }
    },
  )
})
