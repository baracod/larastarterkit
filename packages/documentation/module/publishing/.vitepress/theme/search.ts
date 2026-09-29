export interface SearchEntry { scope: string; title: string; url: string; text: string }
export interface Part { text: string; match: boolean }

// Folds each UTF-16 unit separately so offsets in the folded text match the original text.
function normalize(value: string) {
  let folded = ''
  for (const unit of value.split('')) {
    const simple = unit.normalize('NFD').replace(/[\u0300-\u036F]/g, '').toLowerCase()

    folded += simple.length === 1 ? simple : unit
  }

  return folded
}

/** Splits text into plain and matching parts; rendered as text so no HTML can be injected. */
export function highlight(text: string, terms: string[]): Part[] {
  if (!terms.length)
    return [{ text, match: false }]
  const folded = normalize(text)
  const parts: Part[] = []
  let cursor = 0
  while (cursor < text.length) {
    let next = -1
    let length = 0
    for (const term of terms) {
      const index = folded.indexOf(term, cursor)
      if (index !== -1 && (next === -1 || index < next)) {
        next = index
        length = term.length
      }
    }
    if (next === -1)
      break
    if (next > cursor)
      parts.push({ text: text.slice(cursor, next), match: false })
    parts.push({ text: text.slice(next, next + length), match: true })
    cursor = next + length
  }
  if (cursor < text.length)
    parts.push({ text: text.slice(cursor), match: false })

  return parts
}

/** Accent-insensitive AND search; title matches rank first and excerpts center on the first hit. */
export function searchPortal(entries: SearchEntry[], query: string, scope?: string, limit = 30) {
  const terms = normalize(query).split(/\s+/).filter(Boolean)
  if (!terms.length)
    return []

  return entries
    .filter(entry => !scope || entry.scope === scope)
    .map(entry => {
      const title = normalize(entry.title)
      const text = normalize(entry.text)
      if (!terms.every(term => title.includes(term) || text.includes(term)))
        return null
      const score = terms.reduce((total, term) => total + (title.includes(term) ? 10 : 0) + (title.startsWith(term) ? 5 : 0) + (text.includes(term) ? 1 : 0), 0)
      const first = Math.min(...terms.map(term => text.indexOf(term)).filter(index => index >= 0), entry.text.length)
      const start = Math.max(0, first - 60)
      const excerpt = `${start > 0 ? '…' : ''}${entry.text.slice(start, start + 170).replace(/\s+/g, ' ').trim()}${start + 170 < entry.text.length ? '…' : ''}`

      return { url: entry.url, score, title: highlight(entry.title, terms), excerpt: highlight(excerpt, terms) }
    })
    .filter((entry): entry is NonNullable<typeof entry> => entry !== null)
    .sort((a, b) => b.score - a.score)
    .slice(0, limit)
}
