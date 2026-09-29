import { Prism, grammarFor, lineSet } from './prism'

interface CodeLabels { copy: string; copied: string; copyAria: string; selectCode: string }
const defaults: CodeLabels = { copy: 'Copier', copied: 'Copié !', copyAria: 'Copier le code', selectCode: 'Sélectionnez le code' }

export function enhanceCode(root: Element | null, labels: CodeLabels = defaults) {
  root?.querySelectorAll<HTMLElement>('pre > code').forEach(code => {
    const pre = code.parentElement!
    if (pre.dataset.enhanced || code.classList.contains('language-mermaid'))
      return
    pre.dataset.enhanced = 'true'
    pre.classList.add('cms-code')

    const language = code.className.match(/language-([\w-]+)/)?.[1] || 'text'
    const header = document.createElement('div')
    const label = document.createElement('span')

    header.className = 'cms-code-header'
    label.className = code.dataset.title ? 'cms-code-title' : 'cms-code-language'
    label.textContent = code.dataset.title || language
    header.append(label)

    const copy = document.createElement('button')

    copy.type = 'button'
    copy.className = 'cms-code-copy'
    copy.textContent = labels.copy
    copy.setAttribute('aria-label', labels.copyAria)
    copy.onclick = async () => {
      try {
        await navigator.clipboard.writeText(code.textContent || '')
        copy.textContent = labels.copied
        copy.classList.add('is-copied')
      }
      catch {
        copy.textContent = labels.selectCode
      }
      setTimeout(() => {
        copy.textContent = labels.copy
        copy.classList.remove('is-copied')
      }, 2000)
    }
    header.append(copy)
    pre.before(header)

    const wrapper = document.createElement('div')

    wrapper.className = 'cms-code-block'
    header.before(wrapper)
    wrapper.append(header, pre)

    const found = grammarFor(language)
    if (found) {
      code.classList.add(`language-${found.name}`)
      Prism.highlightElement(code)
    }

    const highlighted = lineSet(code.dataset.lines || '')
    if (highlighted.size) {
      pre.classList.add('has-highlighted-lines')
      for (const line of highlighted) {
        const mark = document.createElement('span')

        mark.className = 'cms-line-highlight'
        mark.setAttribute('aria-hidden', 'true')
        mark.style.setProperty('--line', String(line - 1))
        pre.prepend(mark)
      }
    }
  })
}
