/** Apply preferences before the first paint, without waiting for Vue. */
(() => {
  const root = document.documentElement

  const cookie = key => {
    try {
      const entry = document.cookie.split(/;\s*/).find(value => value.startsWith(`${key}=`))

      return entry ? decodeURIComponent(entry.slice(key.length + 1)) : null
    }
    catch {
      return null
    }
  }

  const preference = cookie('STARTER-theme')

  const theme = preference === 'light' || preference === 'dark'
    ? preference
    : window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'

  const storedColor = (key, fallback) => {
    try {
      const value = localStorage.getItem(`STARTER-initial-loader-${theme}-${key}`)

      return /^#[\da-f]{6}$/i.test(value || '') ? value : fallback
    }
    catch {
      return fallback
    }
  }

  root.style.setProperty('--initial-loader-bg', storedColor('bg', theme === 'dark' ? '#232333' : '#f5f5f9'))
  root.style.setProperty('--initial-loader-color', storedColor('color', theme === 'dark' ? '#E0B04A' : '#94670A'))
  root.style.colorScheme = theme
  root.lang = cookie('STARTER-language') === 'en' ? 'en' : 'fr'
})()
