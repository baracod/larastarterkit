<script setup lang="ts">
import SignaturePad from 'signature_pad'
import { onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps<{
  label?: string
  modelValue?: string | null // Base64 image data
  readonly?: boolean
}>()

const emit = defineEmits(['update:modelValue'])

const canvasRef = ref<HTMLCanvasElement | null>(null)

// On type en 'any' car cela peut être un ComponentPublicInstance ou un HTMLElement
const containerRef = ref<any>(null)

let signaturePad: SignaturePad | null = null
let resizeObserver: ResizeObserver | null = null

// --- FONCTION UTILITAIRE POUR RECUPERER L'ELEMENT DOM ---
const getContainerElement = (): HTMLElement | null => {
  if (!containerRef.value)
    return null

  // Si c'est un composant Vuetify, on prend $el, sinon on prend l'élément direct
  return containerRef.value.$el ?? containerRef.value
}

// Fonction critique : Ajuster le canvas aux pixels réels de l'écran
const resizeCanvas = () => {
  const canvas = canvasRef.value
  const container = getContainerElement() // <--- CORRECTION ICI

  if (!canvas || !container)
    return

  // Sauvegarder la signature actuelle
  const data = signaturePad?.toDataURL()

  // Calcul du ratio pour écrans haute densité (Retina)
  const ratio = Math.max(window.devicePixelRatio || 1, 1)

  // On force la taille interne via le conteneur DOM réel
  canvas.width = container.offsetWidth * ratio
  canvas.height = 200 * ratio

  const ctx = canvas.getContext('2d')

  ctx?.scale(ratio, ratio)

  signaturePad?.clear()

  // Restaurer la signature
  if (data && data !== 'data:,')
    signaturePad?.fromDataURL(data, { ratio })
  else if (props.modelValue)
    signaturePad?.fromDataURL(props.modelValue, { ratio })
}

// Initialisation
onMounted(() => {
  const containerEl = getContainerElement() // <--- CORRECTION ICI

  if (canvasRef.value && containerEl) {
    // 1. Init Pad
    signaturePad = new SignaturePad(canvasRef.value, {
      backgroundColor: 'rgba(255, 255, 255, 0)',
      penColor: 'rgb(0, 0, 0)',
      velocityFilterWeight: 0.7,
    })

    if (props.readonly)
      signaturePad.off()

    // 2. Gestionnaire d'événements
    signaturePad.addEventListener('endStroke', () => {
      if (signaturePad && !signaturePad.isEmpty())
        emit('update:modelValue', signaturePad.toDataURL())
    })

    // 3. Observer les changements de taille
    resizeObserver = new ResizeObserver(() => {
      resizeCanvas()
    })

    // On observe l'élément DOM réel, pas le composant Vue
    resizeObserver.observe(containerEl) // <--- CORRECTION ICI

    // Premier ajustement immédiat
    resizeCanvas()
  }
})

// Nettoyage
onUnmounted(() => {
  resizeObserver?.disconnect()
  signaturePad?.off()
  signaturePad = null
})

// Actions externes
const clear = () => {
  if (!props.readonly) {
    signaturePad?.clear()
    emit('update:modelValue', null)
  }
}

watch(() => props.modelValue, newVal => {
  if (!newVal)
    signaturePad?.clear()
})
</script>

<template>
  <div class="signature-wrapper">
    <div class="d-flex justify-space-between align-center mb-1">
      <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase ps-1">
        {{ label || 'Signature' }}
      </span>
      <VBtn
        v-if="!readonly && modelValue"
        size="x-small"
        variant="text"
        color="error"
        prepend-icon="mdi-eraser"
        @click="clear"
      >
        Effacer
      </VBtn>
    </div>

    <!-- Le ref est ici sur le composant VSheet -->
    <VSheet
      ref="containerRef"
      border
      rounded="lg"
      class="signature-pad-container"
      :class="{ 'readonly-pad': readonly, 'active-pad': !readonly }"
      elevation="0"
    >
      <canvas
        ref="canvasRef"
        style="width: 100%; height: 200px; display: block; touch-action: none;"
      />

      <div
        v-if="!modelValue && !readonly"
        class="placeholder-text d-flex align-center"
      >
        <VIcon
          icon="mdi-draw"
          class="me-2"
          size="small"
        />
        Signez ici
      </div>
    </VSheet>
  </div>
</template>

<style scoped>
.signature-wrapper {
  width: 100%;
}

.signature-pad-container {
  position: relative;
  background-color: #fff;
  overflow: hidden;
  background-image: radial-gradient(#ddd 1px, transparent 1px);
  background-size: 20px 20px;
}

.active-pad {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: crosshair;
}

.readonly-pad {
  background-color: #f5f5f5;
  background-image: none;
  cursor: not-allowed;
  opacity: 0.8;
}

.placeholder-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #aaa;
  pointer-events: none;
  font-size: 0.85rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 1px;
}
</style>
