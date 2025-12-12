<script setup lang="ts">
import { defineProps } from 'vue'
import type { HeaderVTable } from '@/composables/translate/useTranslater'

defineOptions({
  name: 'CoreDataTable',
  inheritRef: true, // On veut que le composant soit référencé
  inheritAttrs: false, // On veut contrôler ce qui est passé
})

const props = defineProps<{
  headers?: any[]
  loading?: boolean
  searchKey?: string
  entity?: string
  module?: string
  selectedItems?: number[]
  items?: any[]
}>()

const { toCamelCase } = useCamelCase()
const { t } = useI18n({ useScope: 'global' })
const { formatErrorMessages, tField, tHeaderCols } = useTranslater()

const _headers = ref<any[] | undefined>(undefined)

watchEffect(() => {
  if (props.entity && props.headers?.length)
    _headers.value = tHeaderCols({ entity: props.entity, module: props.module, headers: props.headers as HeaderVTable[] })
  _headers.value = _headers.value || []
})
</script>

<template>
  <VDataTable
    :value="props.selectedItems"
    :headers="_headers"
    :items="props.items"
    density="compact"
    :items-per-page="15"
    :disabled="props.loading"
    :loading="props.loading"
    show-select
    fixed-header
    item-value="id"
    :search="props.searchKey"
  >
    <template #loading>
      <VSkeletonLoader type="table-row@10" />
    </template>
    <template
      v-for="(_, scopedSlotName) in $slots"
      #[scopedSlotName]="slotData"
    >
      <slot
        :name="scopedSlotName"
        v-bind="slotData"
      />
    </template>
  </VDataTable>
</template>
