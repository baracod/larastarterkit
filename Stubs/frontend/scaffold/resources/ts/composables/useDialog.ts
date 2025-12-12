import { ref } from 'vue'

type TColorConfirm = string | null | 'error' | 'success' | 'secondary' | 'primary' | 'warning' | 'info' | 'dark' | 'light' | 'white' | 'black'
type TTypeConfirm = 'alert' | 'confirm' | 'prompt' | 'info' | null

interface TConfirmArg {
  title?: string | null
  message?: string | null
  persistent?: boolean | null
  color?: TColorConfirm
  type?: 'alert' | 'confirm' | 'prompt' | 'info' | null
}

const isOpen = ref(false)
const title = ref<string | null>('')
const message = ref<string | null>('')
const persistent = ref<boolean | null>(true)
const color = ref<TColorConfirm>('')
let resolveFn: ((value: boolean) => void) | null = null
const type = ref<TTypeConfirm>(null)
export function useDialog() {
  const confirmDialog = (argConfirm?: TConfirmArg): Promise<boolean> => {
    type.value = argConfirm?.type ?? 'confirm'
    color.value = argConfirm?.color ?? 'primary'
    if (argConfirm) {
      title.value = argConfirm.title ?? ''
      message.value = argConfirm.message ?? ''
    }
    isOpen.value = true

    return new Promise(resolve => {
      resolveFn = resolve
    })
  }

  const handleConfirm = () => {
    isOpen.value = false
    if (resolveFn)
      resolveFn(true)
  }

  const handleCancel = () => {
    isOpen.value = false
    if (resolveFn)
      resolveFn(false)
  }

  return { isOpen, title, message, confirmDialog, handleConfirm, handleCancel, persistent, color, type }
}
