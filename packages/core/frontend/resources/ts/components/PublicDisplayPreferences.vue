<script setup lang="ts">
import NavbarI18n from '@/components/NavbarI18n.vue'
import NavbarThemeSwitcher from '@/components/NavbarThemeSwitcher.vue'
import { themeConfig } from '@themeConfig'

const { t } = useI18n()

const languages = computed(() => themeConfig.app.i18n.langConfig.map(language => ({
  ...language,
  label: t(`displayPreferences.languages.${language.i18nLang}`),
})))
</script>

<template>
  <div
    class="d-flex align-center"
    role="group"
    :aria-label="t('displayPreferences.title')"
  >
    <NavbarThemeSwitcher />
    <NavbarI18n :languages="languages" />
  </div>
</template>
