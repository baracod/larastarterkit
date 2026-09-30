<script lang="ts" setup>
import type { Component } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VNodeRenderer } from './VNodeRenderer'
import { layoutConfig } from '@layouts'
import { VerticalNavGroup, VerticalNavLink, VerticalNavSectionTitle } from '@layouts/components'
import { useLayoutConfigStore } from '@layouts/stores/config'
import { injectionKeyIsVerticalNavHovered } from '@layouts/symbols'
import type { NavGroup, NavLink, NavSectionTitle, VerticalNavItems } from '@layouts/types'

interface Props {
  tag?: string | Component
  navItems: VerticalNavItems
  isOverlayNavActive: boolean
  toggleIsOverlayNavActive: (value: boolean) => void
}

const props = withDefaults(defineProps<Props>(), {
  tag: 'aside',
})

const { t } = useI18n()
const refNav = ref<HTMLElement | null>(null)

const isHovered = useElementHover(refNav)

provide(injectionKeyIsVerticalNavHovered, isHovered)

const configStore = useLayoutConfigStore()

const resolveNavItemComponent = (item: NavLink | NavSectionTitle | NavGroup): unknown => {
  if ('heading' in item)
    return VerticalNavSectionTitle
  if ('children' in item)
    return VerticalNavGroup

  return VerticalNavLink
}

/*
  ℹ️ Close overlay side when route is changed
  Close overlay vertical nav when link is clicked
*/
const route = useRoute()

watch(() => route.name, () => {
  props.toggleIsOverlayNavActive(false)
})

const isVerticalNavScrolled = ref(false)
const updateIsVerticalNavScrolled = (val: boolean) => isVerticalNavScrolled.value = val

const handleNavScroll = (evt: Event) => {
  isVerticalNavScrolled.value = (evt.target as HTMLElement).scrollTop > 0
}

const hideTitleAndIcon = configStore.isVerticalNavMini(isHovered)

let previousFocus: HTMLElement | null = null
watch(() => props.isOverlayNavActive, async open => {
  if (open && configStore.isLessThanOverlayNavBreakpoint) {
    previousFocus = document.activeElement as HTMLElement | null
    await nextTick()
    refNav.value?.querySelector<HTMLButtonElement>('.nav-header-control')?.focus()
  }
  else if (!open && refNav.value?.contains(document.activeElement)) {
    previousFocus?.focus()
  }
})

function handleNavKeydown(event: KeyboardEvent) {
  if (!props.isOverlayNavActive || !configStore.isLessThanOverlayNavBreakpoint)
    return
  if (event.key === 'Escape') {
    event.preventDefault()
    props.toggleIsOverlayNavActive(false)
  }
  if (event.key !== 'Tab')
    return

  const controls = Array.from(refNav.value?.querySelectorAll<HTMLElement>('a[href], button:not(:disabled), [tabindex="0"]') ?? [])
    .filter(element => element.getClientRects().length > 0)

  const first = controls[0]
  const last = controls.at(-1)
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  }
  else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}
</script>

<template>
  <Component
    :is="props.tag"
    id="application-sidebar"
    ref="refNav"
    :aria-label="t('navigation.sidebar')"
    :inert="configStore.isLessThanOverlayNavBreakpoint && !isOverlayNavActive"
    class="layout-vertical-nav"
    :class="[
      {
        'overlay-nav': configStore.isLessThanOverlayNavBreakpoint,
        'hovered': isHovered,
        'visible': isOverlayNavActive,
        'scrolled': isVerticalNavScrolled,
      },
    ]"
    @keydown="handleNavKeydown"
  >
    <!-- 👉 Header -->
    <div class="nav-header">
      <slot name="nav-header">
        <RouterLink
          to="/"
          class="app-logo app-title-wrapper"
        >
          <VNodeRenderer :nodes="layoutConfig.app.logo" />

          <Transition name="vertical-nav-app-title">
            <h1
              v-show="!hideTitleAndIcon"
              class="app-logo-title leading-normal"
            >
              {{ layoutConfig.app.title }}
            </h1>
          </Transition>
        </RouterLink>
        <!-- 👉 Vertical nav actions -->
        <!-- Show toggle collapsible in >md and close button in <md -->
        <div class="header-action">
          <button
            v-if="!configStore.isLessThanOverlayNavBreakpoint"
            type="button"
            class="nav-header-control"
            :aria-label="t(configStore.isVerticalNavCollapsed ? 'navigation.expandSidebar' : 'navigation.collapseSidebar')"
            :title="t(configStore.isVerticalNavCollapsed ? 'navigation.expandSidebar' : 'navigation.collapseSidebar')"
            :aria-expanded="!configStore.isVerticalNavCollapsed"
            @click="configStore.isVerticalNavCollapsed = !configStore.isVerticalNavCollapsed"
          >
            <Component
              :is="layoutConfig.app.iconRenderer || 'div'"
              v-bind="configStore.isVerticalNavCollapsed ? layoutConfig.icons.verticalNavUnPinned : layoutConfig.icons.verticalNavPinned"
            />
          </button>
          <button
            v-else
            type="button"
            class="nav-header-control"
            :aria-label="t('navigation.closeSidebar')"
            @click="toggleIsOverlayNavActive(false)"
          >
            <Component
              :is="layoutConfig.app.iconRenderer || 'div'"
              icon="mdi-close"
            />
          </button>
        </div>
      </slot>
    </div>
    <slot name="before-nav-items">
      <div class="vertical-nav-items-shadow" />
    </slot>
    <slot
      name="nav-items"
      :update-is-vertical-nav-scrolled="updateIsVerticalNavScrolled"
    >
      <PerfectScrollbar
        :key="configStore.isAppRTL"
        tag="ul"
        class="nav-items"
        :options="{ wheelPropagation: false }"
        @ps-scroll-y="handleNavScroll"
      >
        <Component
          :is="resolveNavItemComponent(item)"
          v-for="(item, index) in navItems"
          :key="index"
          :item="item"
        />
      </PerfectScrollbar>
    </slot>
    <slot name="after-nav-items" />
  </Component>
</template>

<style lang="scss" scoped>
.app-logo {
  display: flex;
  align-items: center;
  column-gap: 0.75rem;

  .app-logo-title {
    font-size: 1.25rem;
    font-weight: 500;
    line-height: 1.75rem;
  }
}
</style>

<style lang="scss">
@use "@configured-variables" as variables;
@use "@layouts/styles/mixins";

// 👉 Vertical Nav
.layout-vertical-nav {
  position: fixed;
  z-index: variables.$layout-vertical-nav-z-index;
  display: flex;
  flex-direction: column;
  block-size: 100%;
  inline-size: variables.$layout-vertical-nav-width;
  inset-block-start: 0;
  inset-inline-start: 0;
  transition: inline-size 0.25s ease-in-out, box-shadow 0.25s ease-in-out;
  will-change: transform, inline-size;

  .nav-header {
    display: flex;
    align-items: center;

    .header-action {
      cursor: pointer;

      @at-root {
        #{variables.$selector-vertical-nav-mini} .nav-header .header-action {
          &.nav-pin,
          &.nav-unpin {
            display: none !important;
          }
        }
      }
    }
  }

  .app-title-wrapper {
    margin-inline-end: auto;
  }

  .nav-items {
    block-size: 100%;

    // ℹ️ We no loner needs this overflow styles as perfect scrollbar applies it
    // overflow-x: hidden;

    // // ℹ️ We used `overflow-y` instead of `overflow` to mitigate overflow x. Revert back if any issue found.
    // overflow-y: auto;
  }

  .nav-item-title {
    overflow: hidden;
    margin-inline-end: auto;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  // 👉 Collapsed
  .layout-vertical-nav-collapsed & {
    &:not(.hovered) {
      inline-size: variables.$layout-vertical-nav-collapsed-width;
    }
  }
}

// Small screen vertical nav transition
@media (max-width: 1279px) {
  .layout-vertical-nav {
    &:not(.visible) {
      transform: translateX(-#{variables.$layout-vertical-nav-width});

      @include mixins.rtl {
        transform: translateX(variables.$layout-vertical-nav-width);
      }
    }

    transition: transform 0.25s ease-in-out;
  }
}
</style>

<style lang="scss">
// ℹ️ The button fills the circular `.header-action` exactly so the toggle stays round and centered on the nav edge.
.layout-vertical-nav .nav-header-control {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  block-size: 100%;
  color: inherit;
  cursor: pointer;
  inline-size: 100%;

  // Larger invisible hit area without changing the drawn circle.
  &::before {
    position: absolute;
    border-radius: 50%;
    content: "";
    inset: -7px;
  }
}

.layout-vertical-nav :is(a, button):focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}
</style>
