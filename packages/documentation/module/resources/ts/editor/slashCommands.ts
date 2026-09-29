export interface SlashCommand {
  key: string
  icon: string
  group: 'text' | 'lists' | 'blocks' | 'media' | 'callouts'
  keywords: string
}

// Labels come from i18n ("Documentation.blocks.<key>"); keywords help both FR and EN queries.
export const slashCommands: SlashCommand[] = [
  { key: 'paragraph', icon: 'tabler-pilcrow', group: 'text', keywords: 'paragraphe texte text paragraph' },
  { key: 'heading2', icon: 'tabler-h-2', group: 'text', keywords: 'titre heading h2 section' },
  { key: 'heading3', icon: 'tabler-h-3', group: 'text', keywords: 'sous-titre sous-section heading h3 subsection' },
  { key: 'heading4', icon: 'tabler-h-4', group: 'text', keywords: 'heading h4' },
  { key: 'bulletList', icon: 'tabler-list', group: 'lists', keywords: 'liste puces bullet list ul' },
  { key: 'orderedList', icon: 'tabler-list-numbers', group: 'lists', keywords: 'liste numérotée étapes ordered steps ol' },
  { key: 'taskList', icon: 'tabler-list-check', group: 'lists', keywords: 'tâches todo checklist task' },
  { key: 'codeBlock', icon: 'tabler-code', group: 'blocks', keywords: 'code bloc snippet programme' },
  { key: 'terminal', icon: 'tabler-terminal-2', group: 'blocks', keywords: 'terminal shell bash commande command console' },
  { key: 'mermaid', icon: 'tabler-schema', group: 'blocks', keywords: 'diagramme mermaid schéma flowchart diagram' },
  { key: 'table', icon: 'tabler-table', group: 'blocks', keywords: 'tableau table grille grid' },
  { key: 'quote', icon: 'tabler-blockquote', group: 'blocks', keywords: 'citation quote' },
  { key: 'divider', icon: 'tabler-separator-horizontal', group: 'blocks', keywords: 'séparateur ligne divider hr rule' },
  { key: 'image', icon: 'tabler-photo', group: 'media', keywords: 'image photo capture media screenshot' },
  { key: 'note', icon: 'tabler-info-circle', group: 'callouts', keywords: 'remarque note info encadré callout' },
  { key: 'tip', icon: 'tabler-bulb', group: 'callouts', keywords: 'astuce conseil tip hint encadré callout' },
  { key: 'important', icon: 'tabler-star', group: 'callouts', keywords: 'important encadré callout' },
  { key: 'warning', icon: 'tabler-alert-triangle', group: 'callouts', keywords: 'attention avertissement warning encadré callout' },
  { key: 'caution', icon: 'tabler-alert-octagon', group: 'callouts', keywords: 'danger caution risque encadré callout' },
]

const fold = (value: string) => value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()

/** Filters commands by label or keyword; every word of the query must match. */
export function filterSlashCommands(query: string, label: (key: string) => string = key => key) {
  const terms = fold(query).split(/\s+/).filter(Boolean)

  return slashCommands.filter(command => {
    const haystack = fold(`${label(command.key)} ${command.keywords} ${command.key}`)

    return terms.every(term => haystack.includes(term))
  })
}
