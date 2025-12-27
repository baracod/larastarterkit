import { ref } from 'vue'
import User from '../api/User'
import type { IBioEditable, IUser } from '../types/entities'

export function useUser(userId: number) {
  // State
  const userData = ref<IUser | null>(null)
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)
  const errorMessages = ref<Record<string, string>>({})

  // Functions
  const fetchUser = async () => {
    isLoading.value = true
    error.value = null
    try {
      userData.value = await User.getById(userId)
    }
    catch (e: any) {
      error.value = e.message
    }
    finally {
      isLoading.value = false
    }
  }

  const updateProfile = async (payload: { data: IBioEditable; avatarFile?: File }) => {
    isLoading.value = true
    error.value = null
    errorMessages.value = {}
    try {
      await User.updateProfile(userId, payload.data, payload.avatarFile)
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
  fetchUser()

  return {
    userData,
    isLoading,
    error,
    errorMessages,
    fetchUser,
    updateProfile,
  }
}
