<!-- ❗Errors in the form are set on line 60 -->
<script setup lang="ts">
import { useStorage } from '@vueuse/core'
import { useLocale } from 'vuetify'
import { VForm } from 'vuetify/components/VForm'
import AuthAPI from '@auth/api/Auth'
import AuthDialogSuspend from '@auth/components/AuthDialogSuspend.vue'
import { useAuthStore } from '@auth/stores'
import type { IAbilityRuler } from '@auth/types/auth'
import type { IUser } from '@auth/types/entities'
import authV2LoginIllustration from '@images/pages/auth-v2-login-illustration.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)
const { t, locale, messages } = useI18n({ useScope: 'global' })

const route = useRoute()
const router = useRouter()

const ability = useAbility()

const errors = ref<Record<string, string | undefined>>({
  email: undefined,
  password: undefined,
})

const isSuspendUser = ref(false)
const refVForm = ref<VForm>()

onMounted(() => {
  const { current } = useLocale()

  const browserLocale = navigator.language.split('-')[0]?.toLowerCase() || 'en'

  const supportedLocales = ['fr', 'en']

  const selectedLocale = supportedLocales.includes(browserLocale) ? browserLocale : 'en'

  current.value = selectedLocale

  locale.value = selectedLocale
})

const credentials = ref({
  email: 'admin@admin.com',
  password: '12345678',

  // password: 'Khalncdjbp123',
})

const rememberMe = ref(false)

const login = async () => {
  try {
    const res = await AuthAPI.login({
      email: credentials.value.email,
      password: credentials.value.password,
    })

    if (!res.data) {
      errors.value.password = 'Invalid credentials'

      return
    }

    const { user, accessToken, abilityRules, permissions, roles } = res.data

    useStorage<IAbilityRuler[]>('userAbilityRules', []).value = abilityRules
    ability.update(abilityRules ?? [])

    useCookie<IUser>('userData').value = user
    useCookie('accessToken').value = accessToken

    const { hydrate } = useAuthStore()

    hydrate({
      user,
      token: accessToken,
      roles,
      permissions: permissions ?? [],
    })

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : '/')
    })
  }
  catch (err) {
    // console.error(err)
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid: isValid }) => {
      if (isValid)
        login()
    })
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-2">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 pa-8">
        <div class="d-flex align-center justify-center w-100 h-100">
          <VImg
            max-width="700"
            :src="authV2LoginIllustration"
            class="auth-illustration"
          />
        </div>
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ t('Auth.pages.login.welcome').replace(':app', themeConfig.app.title) }}!
          </h4>
          <p class="mb-0">
            {{ t('Auth.pages.login.subtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.email"
                  :label="t('Auth.pages.login.email')"
                  placeholder="johndoe@email.com"
                  type="email"
                  autofocus
                  :rules="[requiredValidator, emailValidator]"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.password"
                  :label="t('Auth.pages.login.password')"
                  placeholder="············"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox
                    v-model="rememberMe"
                    :label="t('Auth.pages.login.rememberMe')"
                  />
                  <RouterLink
                    class="text-primary"
                    :to="{ name: 'auth-forgot-password' }"
                  >
                    {{ t('Auth.pages.login.forgotPassword') }}
                  </RouterLink>
                </div>

                <VBtn
                  block
                  type="submit"
                >
                  {{ t('Auth.pages.login.action.signIn') }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <AuthDialogSuspend v-model="isSuspendUser" />
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";
</style>
