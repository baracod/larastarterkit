import type { MaybeRefOrGetter } from 'vue'
import User from '../api/User'
import type { IBioEditable, IUser } from '../types/entities'

export function useUser(userId: MaybeRefOrGetter<number>) {
  // State
  const userData = ref<IUser | null>(null)
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)
  const errorMessages = ref<Record<string, string>>({})

  let latestRequest = 0

  // Functions
  const fetchUser = async () => {
    const request = ++latestRequest

    isLoading.value = true
    error.value = null
    try {
      const id = toValue(userId)
      const result = await User.getById(id)
      if (request === latestRequest && id === toValue(userId))
        userData.value = result
    }
    catch (e: any) {
      if (request === latestRequest)
        error.value = e.message
    }
    finally {
      if (request === latestRequest)
        isLoading.value = false
    }
  }

  const updateProfile = async (payload: { data: IBioEditable; avatarFile?: File }) => {
    isLoading.value = true
    error.value = null
    errorMessages.value = {}
    try {
      await User.updateProfile(toValue(userId), payload.data, payload.avatarFile)
      await fetchUser() // Refresh user data
    }
    catch (e: any) {
      if (e.response && e.response.status === 422) {
        const errors = e.response.data.errors
        const formattedErrors: Record<string, string> = {}
        for (const field in errors) {
          if (Object.prototype.hasOwnProperty.call(errors, field))
            formattedErrors[field] = errors[field].join(', ')
        }
        errorMessages.value = formattedErrors
      }
      else {
        error.value = e.message
      }
    }
    finally {
      isLoading.value = false
    }
  }

  // Initial fetch
  watch(() => toValue(userId), () => {
    userData.value = null
    fetchUser()
  }, { immediate: true })

  return {
    userData,
    isLoading,
    error,
    errorMessages,
    fetchUser,
    updateProfile,
  }
}
