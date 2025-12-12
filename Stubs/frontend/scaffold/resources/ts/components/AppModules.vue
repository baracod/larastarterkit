<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import type { Module } from '@/types/module'

interface Props {
  togglerIcon?: string
  modules: Module[]
}

const props = withDefaults(defineProps<Props>(), {
  togglerIcon: 'bx-grid-alt',
})

const router = useRouter()
</script>

<template>
  <IconBtn>
    <VIcon
      size="22"
      :icon="props.togglerIcon"
    />
    <VMenu
      activator="parent"
      offset="21px"
      location="bottom end"
      width="340"
    >
      <VCard
        :width="$vuetify.display.smAndDown ? 330 : 380"
        max-height="560"
        class="d-flex flex-column"
      >
        <VCardItem class="py-3">
          <h6 class="text-base font-weight-medium">
            Modules
          </h6>

          <template #append>
            <IconBtn
              size="small"
              color="high-emphasis"
            >
              <VIcon
                size="40"
                icon="mdi-chevron-right"
              />
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <PerfectScrollbar :options="{ wheelPropagation: false }">
          <VRow class="ma-0 mt-n1">
            <VCol
              v-for="(module, index) in props.modules"
              :key="module.title"
              cols="4"
              class="text-center border-t border-e cursor-pointer pa-3 module-icon"
              @click="router.push(module.to)"
            >
              <VAvatar
                variant="tonal"
                size="60"
              >
                <VIcon
                  size="50"
                  color="high-emphasis"
                  :icon="module.icon"
                />
              </VAvatar>

              <h6 class="text-base font-weight-medium mt-3 mb-0">
                {{ module.title }}
              </h6>
              <!--
                <p class="text-sm mb-0">
                {{ module.subtitle }}
                </p>
              -->
            </VCol>
          </VRow>
        </PerfectScrollbar>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.module-icon:hover {
  background-color: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));
}
</style>
