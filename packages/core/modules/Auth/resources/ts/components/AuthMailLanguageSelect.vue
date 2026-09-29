<script setup lang="ts">
const language = defineModel<'fr' | 'en'>({ required: true })
const { t } = useI18n()
const languages = [{ title: 'Français', value: 'fr' }, { title: 'English', value: 'en' }]
</script>

<template>
  <AppSelect
    v-model="language"
    :label="t('Auth.accountMail.language')"
    :items="languages"
    class="mb-4"
  />
</template>
