<script lang="ts" setup>
import type { VDateInput } from 'vuetify/labs/VDateInput'

defineOptions({
  name: 'AppDateField',
  inheritAttrs: false,
})

const props = defineProps<{ modelValue?: string | Date | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
const slots = useSlots()
const forwardedSlotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VDateInput>['$slots']>)

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-date-field-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const calendarDate = computed(() => {
  if (!props.modelValue)
    return null
  if (props.modelValue instanceof Date)
    return props.modelValue
  const [year, month, day] = String(props.modelValue).slice(0, 10).split('-').map(Number)

  return year && month && day ? new Date(year, month - 1, day) : null
})

function updateDate(value: unknown) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
    emit('update:modelValue', null)

    return
  }
  emit('update:modelValue', `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`)
}

const label = computed(() => useAttrs().label as string | undefined)
</script>

<template>
  <div
    class="app-text-field flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2 text-wrap"
      style="line-height: 15px;"
      :text="label"
    />
    <VDateInput
      :model-value="calendarDate"
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        variant: 'outlined',
        id: elementId,
      }"
      @update:model-value="updateDate"
    >
      <template
        v-for="name in forwardedSlotNames"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </VDateInput>
  </div>
</template>
