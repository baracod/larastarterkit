<script setup lang="ts">
import { themeConfig } from '@themeConfig'

defineProps<{ title: string; description: string; icon: string }>()

const { t } = useI18n()
const logo = JSON.parse(document.getElementById('starter-config')?.textContent || '{}').logo || '/images/logos/starter.svg'
</script>

<template>
  <main class="auth-access">
    <div class="auth-access-layout">
      <aside class="auth-access-brand">
        <img
          :src="logo"
          :alt="themeConfig.app.title"
          width="160"
          height="88"
          class="auth-access-logo"
        >
        <div class="auth-access-introduction">
          <p class="auth-access-eyebrow">
            {{ themeConfig.app.title }}
          </p>
          <h2>{{ t('Auth.access.brandTitle') }}</h2>
          <p>{{ t('Auth.access.brandDescription') }}</p>
        </div>
        <ul class="auth-access-benefits">
          <li
            v-for="[key, symbol] in [['origin', 'mdi-account-key'], ['operations', 'mdi-view-module'], ['verification', 'mdi-shield-check']]"
            :key="key"
          >
            <VIcon
              :icon="symbol"
              aria-hidden="true"
            />
            <span>{{ t(`Auth.access.${key}`) }}</span>
          </li>
        </ul>
      </aside>
      <section
        class="auth-access-card"
        aria-labelledby="auth-access-title"
      >
        <header class="mb-6">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
            size="48"
            class="mb-4"
          >
            <VIcon
              :icon="icon"
              aria-hidden="true"
            />
          </VAvatar>
          <h1 id="auth-access-title">
            {{ title }}
          </h1>
          <p class="text-medium-emphasis mt-2 mb-0">
            {{ description }}
          </p>
        </header>
        <slot />
      </section>
    </div>
  </main>
</template>

<style lang="scss">
.auth-access {

  min-block-size: 100svh;

  padding: clamp(16px, 4vw, 56px);

  display: grid;

  align-items: center;

  background: rgb(var(--v-theme-background));

}

.auth-access-layout {
  inline-size: 100%;
  max-inline-size: 1120px;
  margin-inline: auto;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 480px);
  gap: clamp(24px, 6vw, 80px);
  align-items: center;
}

.auth-access-brand, .auth-access-card {
  min-inline-size: 0;
}

.auth-access-logo {
  object-fit: contain;
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  max-inline-size: 100%;
}

.auth-access-introduction {
  margin-block: 32px;
}

.auth-access-eyebrow {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.auth-access-introduction h2 {
  font-size: clamp(1.7rem, 3vw, 2.5rem);
  line-height: 1.25;
  margin-block: 12px 20px;
}

.auth-access-introduction > p:last-child {
  color: rgba(var(--v-theme-on-surface), .75);
  line-height: 1.75;
}

.auth-access-benefits {
  list-style: none;
  padding: 0;
  display: grid;
  gap: 20px;
}

.auth-access-benefits li {
  display: flex;
  gap: 14px;
  align-items: center;
}

.auth-access-benefits .v-icon {
  color: rgb(var(--v-theme-primary));
  flex-shrink: 0;
}

.auth-access-card {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 20px;
  padding: clamp(20px, 4vw, 36px);
  box-shadow: 0 12px 40px rgba(20, 30, 60, .04);
}

.auth-access-card h1 {
  font-size: 1.5rem;
  line-height: 1.35;
}

.auth-access-card .v-form {
  display: grid;
  gap: 20px;
}

.auth-access-card .v-input, .auth-access-card .v-field {
  min-inline-size: 0;
}

.auth-access-card .v-list-item-title {
  white-space: normal;
}

.auth-access-card .v-btn {
  min-block-size: 44px;
}

.auth-access-links {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.auth-access a {
  display: inline-flex;
  align-items: center;
  min-block-size: 44px;
  gap: 6px;
}

.auth-access :is(a, button):focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

@media (max-width: 959px) {
  .auth-access-layout {
    max-inline-size: 560px;
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
  }

  .auth-access-introduction, .auth-access-benefits {
    display: none;
  }

  .auth-access-brand {
    text-align: center;
  }

  .auth-access-logo {
    inline-size: 128px;
    block-size: 70px;
  }

}
</style>
