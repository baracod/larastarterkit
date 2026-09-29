export const csrfToken = (): string | undefined => {
  const cookie = document.cookie
    .split('; ')
    .find(item => item.startsWith('XSRF-TOKEN='))

  return cookie ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length)) : undefined
}

export const initializeCsrf = async (): Promise<void> => {
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}
