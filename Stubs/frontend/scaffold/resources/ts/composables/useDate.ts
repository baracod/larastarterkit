export const useDate = () => {
  const date = (_date: string | Date) => {
    return computed(() => {
      const { locale } = useI18n()
      if (locale.value === 'en')
        return useDateFormat(_date, 'MM/DD/YYYY')

      return useDateFormat(_date, 'DD/MM/YYYY')
    })
  }

  const time = (_time: string) => {
    return computed(() => {
      const { locale } = useI18n()
      if (locale.value === 'en')
        return useDateFormat(_time ?? new Date(), 'hh:mm A')

      return useDateFormat(_time ?? new Date(), 'HH:mm')
    })
  }

  const dateTime = (_dateTime: string) => {
    return computed(() => {
      const { locale } = useI18n()
      if (locale.value === 'en')
        return useDateFormat(_dateTime, 'MM/DD/YYYY hh:mm A')

      return useDateFormat(_dateTime, 'DD/MM/YYYY HH:mm')
    })
  }

  return { date, time, dateTime }
}
