export function formatPersonName(person: { first_name?: string | null; last_name?: string | null }): string {
  return [person.first_name, person.last_name].filter(Boolean).join(' ')
}
