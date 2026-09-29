<script lang="ts" setup>
defineOptions({
  name: 'CoreAutocomplete',
  inheritAttrs: false,
})

const emit = defineEmits(['update:modelValue'])
const attrs = useAttrs()
const accessibleLabel = computed(() => attrs['aria-label'] || attrs.label || attrs.placeholder || undefined)
</script>

<template>
  <!-- 1. Le wrapper de colonne reçoit les attributs de grille (cols, md, etc.) -->
  <CoreCol v-bind="$attrs">
    <!-- 2. Le composant enfant reçoit aussi les attrs (label, items, etc.) -->
    <AppAutocomplete
      v-bind="$attrs"
      variant="outlined"
      :aria-label="accessibleLabel"
      @update:model-value="emit('update:modelValue', $event)"
    >
      <!-- 3. LA CORRECTION EST ICI : Boucle pour transmettre tous les slots -->
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </AppAutocomplete>
  </CoreCol>
</template>
