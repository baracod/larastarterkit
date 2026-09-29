<script lang="ts" setup>
import { useAuthStore } from '@auth/stores'
import { themeConfig } from '@themeConfig'

// @layouts plugin
import { useConfigStore } from '@/@core/stores/config'
import { VerticalNavLayout } from '@layouts'

const { t } = useI18n({ useScope: 'global' })
const auth = useAuthStore()
const isLoggedIn = computed(() => auth.isAuthenticated)
const menu = useModuleMenu()
const route = useRoute()
const isAuthModule = computed(() => route.path === '/auth' || route.path.startsWith('/auth/'))

const configStore = useConfigStore()

// ℹ️ Provide animation name for vertical nav collapse icon.
const verticalNavHeaderActionAnimationName = ref<null | 'rotate-180' | 'rotate-back-180'>(null)

watch([
  () => configStore.isVerticalNavCollapsed,
  () => configStore.isAppRTL,
], val => {
  if (configStore.isAppRTL)
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-back-180' : 'rotate-180'
  else
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-180' : 'rotate-back-180'
}, { immediate: true })

const actionArrowInitialRotation = configStore.isVerticalNavCollapsed ? '180deg' : '0deg'
</script>

<template>
  <VerticalNavLayout
    :nav-items="menu"
    :class="{ 'layout-auth-module': isAuthModule }"
  >
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive, isOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          :aria-label="t('navigation.openSidebar')"
          aria-controls="application-sidebar"
          :aria-expanded="isOverlayNavActive"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="bx-menu"
          />
        </IconBtn>
        <NavSearchBar class="ms-lg-n3 base-nav-search" />
        <ModuleNavbarExtension />
        <VSpacer />
        <NavbarThemeSwitcher />
        <NavbarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavbarModules />
        <NavBarNotifications
          v-if="isLoggedIn"
          class="me-1"
        />
        <!-- User -->
        <UserProfile v-if="isLoggedIn" />
        <VBtn
          v-else
          :to="{ name: 'auth-login' }"
        >
          {{ t('action.login') }}
        </VBtn>
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>

<style lang="scss">
@use "@layouts/styles/mixins" as layoutsMixins;

.layout-vertical-nav {
  // ℹ️ Nav header circle on the right edge
  .nav-header {
    position: relative;
    overflow: visible !important;

    &::after {
      --diameter: 36px;

      position: absolute;
      z-index: -1;
      border: 7px solid rgba(var(--v-theme-background), 1);
      border-radius: 100%;
      aspect-ratio: 1;
      background: rgba(var(--v-theme-surface), 1);
      content: "";
      inline-size: var(--diameter);
      inset-block-start: calc(50% - var(--diameter) / 2);
      inset-inline-end: -18px;

      @at-root {
        // Change background color of nav header circle when vertical nav is in overlay mode
        .layout-overlay-nav {
          --app-header-container-bg: rgb(var(--v-theme-surface));

          // ℹ️ Only transition in overlay mode
          .nav-header::after {
            transition: opacity 0.2s ease-in-out;
          }
        }

        .layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) {
          .nav-header::after,
          .nav-header .header-action {
            opacity: 0;
          }
        }
      }
    }
  }

  // Don't show nav header circle when vertical nav is in overlay mode and not visible
  &.overlay-nav:not(.visible) .nav-header::after {
    opacity: 0;
  }
}

// ℹ️ Nav header action buttons styles
@keyframes rotate-180 {
  from {
    transform: rotate(0deg) scaleX(var(--app-header-actions-scale-x));
  }

  to {
    transform: rotate(180deg) scaleX(var(--app-header-actions-scale-x));
  }
}

@keyframes rotate-back-180 {
  from {
    transform: rotate(180deg) scaleX(var(--app-header-actions-scale-x));
  }

  to {
    transform: rotate(0deg) scaleX(var(--app-header-actions-scale-x));
  }
}

/* stylelint-disable-next-line no-duplicate-selectors */
.layout-vertical-nav {
  /* stylelint-disable-next-line no-duplicate-selectors */
  .nav-header {
    .header-action {
      // ℹ️ We need to create this CSS variable for reusing value in animation
      --app-header-actions-scale-x: 1;

      position: absolute;
      border-radius: 100%;
      animation-duration: 0.35s;
      animation-fill-mode: forwards;
      animation-name: v-bind(verticalNavHeaderActionAnimationName);
      color: white;
      inset-inline-end: 0;
      inset-inline-end: -11px;
      /* stylelint-disable-next-line value-keyword-case */
      transform: rotate(v-bind(actionArrowInitialRotation)) scaleX(var(--app-header-actions-scale-x));
      transition: opacity 0.2s ease-in-out;

      @include layoutsMixins.rtl {
        --app-header-actions-scale-x: -1;
      }

      @at-root {
        .layout-nav-type-vertical.layout-overlay-nav .layout-vertical-nav:not(.visible) .nav-header .header-action {
          opacity: 0;
        }
      }
    }
  }
}
</style>

<style lang="scss">
:is(.layout-base-module, .layout-auth-module).layout-nav-type-vertical .layout-vertical-nav .nav-items {
  padding-inline: 12px;

  > .nav-group, > .nav-link { margin-block: 6px; }

  .nav-group > .nav-group-label, .nav-link > a {
    inline-size: 100%;
    min-block-size: 44px;
    margin-inline: 0;
    padding-inline: 12px;
    border-radius: 10px;
  }

  .nav-group .nav-link .nav-item-icon {
    font-size: 20px;
    inline-size: 20px;
    block-size: 20px;
    margin-inline: 0 12px;
    transform: none;

    &::before { content: none; }
  }

  .nav-group .nav-link > .router-link-exact-active {
    background-color: rgba(var(--v-theme-primary), .08) !important;
    color: rgb(var(--v-theme-primary)) !important;
    font-weight: 600;

    .nav-item-icon { color: currentColor !important; }
  }

  .nav-group-children .nav-link > a { padding-inline-start: 20px; }
  .nav-item-icon { flex-shrink: 0; }
}
// Keep the pin control available when using keyboard navigation in the icon rail.
:is(.layout-base-module, .layout-auth-module).layout-vertical-nav-collapsed .layout-vertical-nav:focus-within .nav-header .header-action {
  opacity: 1;
}
</style>

<style lang="scss">
@media (max-width: 380px) {
  :is(.layout-base-module, .layout-auth-module) .base-nav-search {
    min-inline-size: 40px;
    padding-inline: 8px;

    .nav-search-label { display: none; }
    .v-btn__prepend { margin-inline: 0; }
  }
}
</style>
