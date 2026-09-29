export interface TreePage { id: number; parent_id: number | null; position: number; archived?: boolean }
export interface TreeNode<T> { item: T; depth: number; children: number }
export type TreeMove = 'up' | 'down' | 'indent' | 'outdent'
export interface TreeChange { id: number; parent_id: number | null; position: number }

function siblings<T extends TreePage>(pages: T[], parent: number | null) {
  return pages.filter(page => page.parent_id === parent).sort((a, b) => a.position - b.position || a.id - b.id)
}

/** Depth-first order; orphans (missing or archived parent) are appended at the root. */
export function flattenTree<T extends TreePage>(pages: T[]): TreeNode<T>[] {
  const result: TreeNode<T>[] = []
  const visited = new Set<number>()

  const append = (parent: number | null, depth: number) => {
    for (const page of siblings(pages, parent)) {
      if (visited.has(page.id))
        continue
      visited.add(page.id)
      result.push({ item: page, depth, children: pages.filter(child => child.parent_id === page.id).length })
      append(page.id, depth + 1)
    }
  }

  append(null, 0)
  for (const page of pages.filter(item => !visited.has(item.id)))
    result.push({ item: page, depth: 0, children: 0 })

  return result
}

export function canMove<T extends TreePage>(pages: T[], id: number, move: TreeMove) {
  const page = pages.find(item => item.id === id)
  if (!page)
    return false
  const list = siblings(pages, page.parent_id)
  const index = list.findIndex(item => item.id === id)

  return { up: index > 0, down: index < list.length - 1, indent: index > 0, outdent: page.parent_id !== null && pages.some(item => item.id === page.parent_id) }[move]
}

/** Computes the renumbered sibling lists after a move; only changed pages are returned. */
export function movePage<T extends TreePage>(pages: T[], id: number, move: TreeMove): TreeChange[] {
  if (!canMove(pages, id, move))
    return []
  const state = new Map(pages.map(page => [page.id, { id: page.id, parent_id: page.parent_id, position: page.position }]))
  const page = state.get(id)!
  const oldParent = page.parent_id
  const list = siblings([...state.values()], oldParent).map(item => item.id)
  const index = list.indexOf(id)
  const lists = new Map<number | null, number[]>()

  if (move === 'up' || move === 'down') {
    const target = index + (move === 'up' ? -1 : 1)

    ;[list[index], list[target]] = [list[target], list[index]]
    lists.set(oldParent, list)
  }
  else if (move === 'indent') {
    const newParent = list[index - 1]

    list.splice(index, 1)
    lists.set(oldParent, list)
    lists.set(newParent, [...siblings([...state.values()], newParent).map(item => item.id), id])
    page.parent_id = newParent
  }
  else {
    const parent = state.get(oldParent!)!
    const upper = siblings([...state.values()], parent.parent_id).map(item => item.id)

    list.splice(index, 1)
    upper.splice(upper.indexOf(parent.id) + 1, 0, id)
    lists.set(oldParent, list)
    lists.set(parent.parent_id, upper)
    page.parent_id = parent.parent_id
  }

  const changes: TreeChange[] = []
  for (const [parent, ids] of lists) {
    ids.forEach((pageId, position) => {
      const original = pages.find(item => item.id === pageId)!
      const next = { id: pageId, parent_id: pageId === id ? page.parent_id : parent, position }
      if (original.parent_id !== next.parent_id || original.position !== next.position)
        changes.push(next)
    })
  }

  return changes
}
