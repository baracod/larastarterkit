<script setup lang="ts">
import misc404 from '@images/pages/404.png'

const { t } = useI18n()
</script>

<template>
  <div class="misc-wrapper">
    <ErrorHeader
      status-code="404"
      :title="t('manualCorrections.errors.notFoundTitle')"
      :description="t('manualCorrections.errors.notFoundDescription')"
    />

    <VBtn
      to="/"
      class="mb-6"
    >
      {{ t('manualCorrections.errors.backHome') }}
    </VBtn>

    <!-- 👉 Image -->
    <div class="misc-avatar w-100 text-center">
      <VImg
        :src="misc404"
        :alt="t('manualCorrections.errors.notFoundTitle')"
        :max-width="500"
        class="mx-auto"
      />
    </div>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/misc.scss";
</style>
