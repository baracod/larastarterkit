<script setup lang="ts">
import type { CmsImage } from '../types/cms'
import { imageUrl } from '../composables/useCms'

const props = defineProps<{ images: CmsImage[]; canUpload: boolean; uploading?: boolean }>()
const emit = defineEmits<{ select: [image: CmsImage]; upload: [files: File[]] }>()
const open = defineModel<boolean>({ default: false })
const { t } = useI18n()
const search = ref('')
const input = ref<HTMLInputElement>()
const drop = ref<HTMLElement>()
const { isOverDropZone } = useDropZone(drop, files => files?.length && props.canUpload && emit('upload', files))
const visible = computed(() => props.images.filter(image => !image.archived_at && `${image.name} ${image.alt || ''}`.toLowerCase().includes(search.value.toLowerCase())))
</script>

<template>
  <VDialog
    v-model="open"
    max-width="960"
    scrollable
  >
    <VCard>
      <VCardItem>
        <VCardTitle>{{ t('Documentation.insertImage') }}</VCardTitle>
        <template #append>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            :aria-label="t('Documentation.close')"
            @click="open = false"
          />
        </template>
      </VCardItem>
      <VCardText>
        <div
          v-if="canUpload"
          ref="drop"
          class="picker-drop mb-4"
          :class="{ 'is-over': isOverDropZone }"
          role="button"
          tabindex="0"
          @click="input?.click()"
          @keydown.enter="input?.click()"
        >
          <VProgressCircular
            v-if="uploading"
            indeterminate
            size="24"
          />
          <VIcon
            v-else
            icon="tabler-cloud-upload"
            size="28"
          />
          <span>{{ t('Documentation.dropImages') }}</span>
          <input
            ref="input"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif"
            multiple
            hidden
            @change="emit('upload', Array.from(($event.target as HTMLInputElement).files || [])); ($event.target as HTMLInputElement).value = ''"
          >
        </div>
        <VTextField
          v-model="search"
          density="compact"
          prepend-inner-icon="tabler-search"
          :placeholder="t('Documentation.searchMedia')"
          clearable
          hide-details
          class="mb-4"
        />
        <div class="picker-grid">
          <button
            v-for="image in visible"
            :key="image.id"
            type="button"
            class="picker-item"
            :title="image.name"
            @click="emit('select', image); open = false"
          >
            <img
              :src="imageUrl(image.id)"
              :alt="image.alt || image.name"
              loading="lazy"
            >
            <span>{{ image.name }}</span>
          </button>
        </div>
        <p
          v-if="!visible.length"
          class="text-medium-emphasis text-center py-6"
        >
          {{ t('Documentation.noMedia') }}
        </p>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.picker-drop { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px; border: 2px dashed rgba(var(--v-border-color), .3); border-radius: 12px; color: rgba(var(--v-theme-on-surface), .7); cursor: pointer; transition: border-color .2s, background .2s; }
.picker-drop:hover, .picker-drop.is-over, .picker-drop:focus-visible { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), .05); outline: none; }
.picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.picker-item { display: flex; flex-direction: column; gap: 6px; padding: 6px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 10px; text-align: start; transition: border-color .15s, transform .15s; }
.picker-item:hover, .picker-item:focus-visible { border-color: rgb(var(--v-theme-primary)); transform: translateY(-2px); outline: none; }
.picker-item img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 6px; background: repeating-conic-gradient(rgba(var(--v-theme-on-surface), .06) 0% 25%, transparent 0% 50%) 50% / 16px 16px; }
.picker-item span { overflow: hidden; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
</style>
