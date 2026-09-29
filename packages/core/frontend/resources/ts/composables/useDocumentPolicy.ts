/**
 * Modes documentaires configurés par l'administrateur (import, génération ou les deux).
 *
 * Une étape peut être réglée sur « Import uniquement », « Génération uniquement » ou
 * « Import et génération », avec des exceptions facultatives par document. L'interface
 * ne doit proposer que les actions autorisées, au même titre que le contrôle serveur
 * (`DocumentWorkflowService::assertAllowed`).
 *
 * Les politiques sont lues une fois par chargement d'application, puis mises en cache.
 * En cas d'échec de lecture, les actions restent proposées : le serveur refuse celles
 * qui ne sont pas autorisées.
 */
import { $api } from '@/utils/api'

export type DocumentMode = 'upload' | 'generate' | 'both'

export interface DocumentPolicy {
  mode: DocumentMode
  can_upload: boolean
  can_generate: boolean
}

export interface StepPolicy extends DocumentPolicy {
  documents: Record<string, DocumentPolicy>
}

const permissions = { can_upload: true, can_generate: true } as const

const steps = ref<Record<string, StepPolicy>>({})
const ready = ref(false)
let pending: Promise<void> | null = null

async function loadPolicies(): Promise<void> {
  try {
    const result = await $api<{ steps: Record<string, StepPolicy> }>('documents/policies')

    steps.value = result?.steps ?? {}
    ready.value = true
  }
  catch {
    steps.value = {}
  }
}

export function useDocumentPolicy() {
  /**
   * Charge les politiques si nécessaire. Les échecs sont silencieux : l'interface
   * reste permissive et le serveur arbitre.
   */
  async function ensureLoaded(): Promise<void> {
    if (ready.value)
      return

    pending ??= loadPolicies().finally(() => {
      pending = null
    })

    await pending
  }

  /**
   * Politique d'un document ; à défaut celle de son étape, puis les actions autorisées.
   */
  function policyFor(step: string, key?: string | null): DocumentPolicy {
    const stepPolicy = steps.value[step]

    if (!stepPolicy)
      return { mode: 'both', ...permissions }

    const documentPolicy = key ? stepPolicy.documents?.[key] : undefined

    return documentPolicy ?? { mode: stepPolicy.mode, can_upload: stepPolicy.can_upload, can_generate: stepPolicy.can_generate }
  }

  return {
    ensureLoaded,
    policyFor,
    canUpload: (step: string, key?: string | null): boolean => policyFor(step, key).can_upload,
    canGenerate: (step: string, key?: string | null): boolean => policyFor(step, key).can_generate,
  }
}
