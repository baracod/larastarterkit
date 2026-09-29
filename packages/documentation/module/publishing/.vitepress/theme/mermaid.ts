export async function renderDiagrams(root: Element | null, dark = false) {
  if (!root)
    return
  const blocks = Array.from(root.querySelectorAll('pre > code.language-mermaid'))
  if (!blocks.length)
    return
  const { default: mermaid } = await import('mermaid')

  mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', suppressErrorRendering: true, theme: dark ? 'dark' : 'default' })
  for (const [index, code] of blocks.entries()) {
    const source = code.textContent || ''

    // Do not allow editorial directives to override the renderer configuration.
    if (source.includes('%%{') || source.trimStart().startsWith('---'))
      continue
    try {
      const { svg } = await mermaid.render(`documentation-diagram-${Date.now()}-${index}`, source)
      if (code.isConnected && code.parentElement) {
        code.parentElement.classList.add('cms-diagram')
        code.parentElement.innerHTML = svg
      }
    }
    catch { /* Invalid diagrams remain readable code blocks. */ }
  }
}
