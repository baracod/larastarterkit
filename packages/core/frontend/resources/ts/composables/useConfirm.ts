import { ref } from 'vue'

type TColorConfirm = string | null | 'error' | 'success' | 'secondary' | 'primary' | 'warning' | 'info' | 'dark' | 'light' | 'white' | 'black'

interface TConfirmArg {
  title?: string | null
  message?: string | null
  persistent?: boolean | null
  color?: TColorConfirm
}

const isOpen = ref(false)
const title = ref<string | null>('')
const message = ref<string | null>('')
const persistent = ref<boolean | null>(true)
const color = ref<TColorConfirm>('')
let resolveFn: ((value: boolean) => void) | null = null

export function useConfirm() {
  const confirmDialog = (argConfirm?: TConfirmArg): Promise<boolean> => {
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

  return { isOpen, title, message, confirmDialog, handleConfirm, handleCancel, persistent, color }
}
