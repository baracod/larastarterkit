<script setup lang="ts">
import notAuthorizedIllustration from '@images/pages/auth-v2-register-illustration.png'

const { t } = useI18n()

definePage({
  alias: '/pages/misc/not-authorized',
  meta: {
    layout: 'blank',
    public: true,
  },
})
</script>

<template>
  <div class="misc-wrapper">
    <ErrorHeader
      status-code="401"
      :title="t('manualCorrections.errors.forbiddenTitle')"
      :description="t('manualCorrections.errors.forbiddenDescription')"
    />

    <VBtn
      class="mb-6"
      to="/"
    >
      {{ t('manualCorrections.errors.backHome') }}
    </VBtn>

    <!-- 👉 Image -->
    <div class="misc-avatar w-100 text-center">
      <VImg
        :src="notAuthorizedIllustration"
        :alt="t('manualCorrections.errors.forbiddenTitle')"
        :max-width="500"
        class="mx-auto"
      />
    </div>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/misc.scss";
</style>
