import { useI18n } from 'vue-i18n'

const { toCamelCase } = useCamelCase()

interface HeaderVTable {
  title: string
  key: string
  sortable?: boolean
  translate?: boolean

}

export type { HeaderVTable }

export function useTranslater() {
  const { t, d } = useI18n()

  /**
   * Formats error messages for a specific entity.
   * @param errors - The error messages to format.
   * @param entity - The entity the errors are related to.
   * @returns A record of formatted error messages.
   */
  const formatErrorMessages = (
    errors: Record<string, { key: string; message: string }>,
    entity: string,
  ): Record<string, string> => {
    const formatted: Record<string, string> = {}

    for (const [field, value] of Object.entries(errors)) {
      const fieldTranslationKey = `${entity}.field.${toCamelCase(field)}`
      const label = t(fieldTranslationKey)
      const template = t(`message.validation.${value.key}`)

      formatted[field] = template.replace(':field', label)
    }

    return formatted
  }

  interface TFieldArgs {
    field: string
    entity: string
    module?: string
  }

  /**
   * Translates a field name for a specific entity and module.
   * @param param0 - The parameters containing field, entity, and module information.
   * @returns The translated field name.
   */
  const tField = ({ field, entity, module }: TFieldArgs) => {
    if (module)
      return t(`${module}.${entity}.field.${toCamelCase(field)}`)

    return t(`${entity}.field.${toCamelCase(field)}`)
  }

  /**
   * Translates header column names for a specific entity and module.
   * @param param0 - The parameters containing entity, module, and headers information.
   * @returns The translated header column names.
   */
  const tHeaderCols = ({ entity, module, headers }: { entity: string; module?: string; headers: Array<HeaderVTable> }) => {
    if (module) {
      return headers.map(header => {
        if (header.translate === false) {
          header.title = header.title ? header.title : header.key

          return header
        }
        header.title = t(`${module}.${entity}.field.${toCamelCase(header.title ?? header.key)}`)

        return header
      })
    }

    return headers.map(header => {
      const key = header.key

      return [key, t(`${entity}.field.${toCamelCase(key)}`)]
    })
  }

  const dt = (dateValue: string | Date | number | null | undefined,
    fallbackText = 'N/A') => {
    if (!dateValue)
      return fallbackText
    try {
      const date = new Date(dateValue)

      if (Number.isNaN(date.getTime()))
        return fallbackText

      return d(date)
    }
    catch (error) {
      console.error('Error formatting date:', error)

      return fallbackText
    }
  }

  return { formatErrorMessages, tField, tHeaderCols, t, dt, d }
}
