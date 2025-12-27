<script lang="ts" setup>
defineOptions({
  name: 'ResponsiveCol',
  inheritAttrs: false, // Contrôle des attributs manuellement
})

const attrs = useAttrs()
const childRef = ref()

// Gestion des tailles responsive avec valeurs par défaut

const xsSize = computed(() => attrs.xs ?? 12)
const smSize = computed(() => attrs.sm ?? undefined)
const mdSize = computed(() => attrs.md ?? 6)
const lgSize = computed(() => attrs.lg ?? undefined)
const xlSize = computed(() => attrs.xl ?? undefined)
const xxlSize = computed(() => attrs.xxl ?? undefined)

// Expose l'enfant pour que le parent puisse l'utiliser
defineExpose({
  childRef,
})

onMounted(() => {
  // console.log(sizeValues.value)
})
</script>

<template>
  <VCol
    :xs="xsSize"
    :sm="smSize"
    :md="mdSize"
    :lg="lgSize"
    :xl="xlSize"
    :xxl="xxlSize"
    :class="attrs.class"
    cols="12"
  >
    <slot
      v-bind="attrs"
      ref="childRef"
    />
  </VCol>
</template>
