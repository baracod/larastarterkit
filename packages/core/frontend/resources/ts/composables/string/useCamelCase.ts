export function useCamelCase() {
  const toCamelCase = (str: string): string => {
    return str
      .replace(/[-_\s]+(.)?/g, (_, chr: string) => (chr ? chr.toUpperCase() : ''))
      .replace(/^(.)/, m => m.toLowerCase()) // minuscule la première lettre seulement
  }

  return { toCamelCase }
}
