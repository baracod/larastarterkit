<script setup lang="ts">
import type { VDataTableServer } from 'vuetify/components/VDataTable'
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

const { tHeaderCols } = useTranslater()

const _headers = ref<any[] | undefined>(undefined)

watchEffect(() => {
  if (props.entity && props.headers?.length)
    _headers.value = tHeaderCols({ entity: props.entity, module: props.module, headers: props.headers as HeaderVTable[] })
  _headers.value = _headers.value || []
})

const slots = useSlots()
const slotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VDataTableServer>['$slots']>)
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
      v-for="scopedSlotName in slotNames"
      #[scopedSlotName]="slotData"
    >
      <slot
        :name="scopedSlotName"
        v-bind="slotData"
      />
    </template>
  </VDataTable>
</template>
