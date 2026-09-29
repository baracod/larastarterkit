<script lang="ts" setup>
defineOptions({
  name: 'ResponsiveCol',
  inheritAttrs: false, // Contrôle des attributs manuellement
})

const attrs = useAttrs()
const childRef = ref()

// Gestion des tailles responsive avec valeurs par défaut

const xsSize = computed(() => (attrs.xs as string | number | boolean | undefined) ?? 12)
const smSize = computed(() => (attrs.sm as string | number | boolean | undefined) ?? undefined)
const mdSize = computed(() => (attrs.md as string | number | boolean | undefined) ?? 6)
const lgSize = computed(() => (attrs.lg as string | number | boolean | undefined) ?? undefined)
const xlSize = computed(() => (attrs.xl as string | number | boolean | undefined) ?? undefined)
const xxlSize = computed(() => (attrs.xxl as string | number | boolean | undefined) ?? undefined)

// Expose l'enfant pour que le parent puisse l'utiliser
defineExpose({
  childRef,
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
