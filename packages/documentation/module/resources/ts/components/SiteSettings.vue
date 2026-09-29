<script setup lang="ts">
import type { CmsImage } from '../types/cms'
import { useDocumentationContext } from '../stores/context'
import { cmsUrl, imageUrl, publicSiteUrl, useCms } from '../composables/useCms'
import { brandPalette, safeColor } from '../../../publishing/.vitepress/theme/palette'
import CmsSegmented from './ui/CmsSegmented.vue'

interface Link { text: string; link: string; theme?: string }
interface Feature { icon: string; title: string; details: string; link: string | null }
interface Theme { brand_color: string; hero_from: string; hero_to: string; appearance: string; code_theme: string; layout: string }
interface Settings {
  title: string
  hero_name: string
  hero_text: string
  tagline: string
  description: string
  locale: string
  logo_image_id: number | null
  hero_image_id: number | null
  actions: Link[]
  features: Feature[]
  nav: Link[]
  github_url: string | null
  footer: string
  theme: Theme
}

const { t, api, busy, can, confirm, run, fieldErrors } = useCms()
const context = useDocumentationContext()
const { collectionId } = storeToRefs(context)
const data = ref<{ content: Settings; version: number }>()
const images = ref<CmsImage[]>([])
const saved = ref('')
const tab = useLocalStorage('documentation-settings-tab', 'identity')
const previewMode = ref<'light' | 'dark'>('light')
const previewDark = computed(() => previewMode.value === 'dark')
const form = computed(() => data.value?.content)
const dirty = computed(() => !!form.value && JSON.stringify(form.value) !== saved.value)
const canEdit = computed(() => can('edit'))

const presets = [
  { name: 'Sneat', brand: '#696cff', from: '#50bdff', to: '#b932fa' },
  { name: 'Océan', brand: '#0ea5e9', from: '#22d3ee', to: '#3b82f6' },
  { name: 'Forêt', brand: '#16a34a', from: '#84cc16', to: '#0d9488' },
  { name: 'Aurore', brand: '#f97316', from: '#facc15', to: '#ef4444' },
  { name: 'Rubis', brand: '#e11d48', from: '#fb7185', to: '#9333ea' },
  { name: 'Ardoise', brand: '#475569', from: '#64748b', to: '#0f172a' },
]

const imageOptions = computed(() => images.value.filter(image => !image.archived_at).map(image => ({ title: image.name, value: image.id })))
const palette = computed(() => brandPalette(safeColor(form.value?.theme.brand_color, '#696cff')))

const previewStyle = computed(() => {
  const theme = form.value?.theme
  const shades = previewDark.value ? palette.value.dark : palette.value.light

  return { '--brand': shades[1], '--brand-soft': shades.soft, '--hero-from': safeColor(theme?.hero_from, '#50bdff'), '--hero-to': safeColor(theme?.hero_to, '#b932fa') }
})

const errorsFor = (key: string) => fieldErrors.value[`content.${key}`] || []
const tabErrors = (prefixes: string[]) => Object.keys(fieldErrors.value).some(key => prefixes.some(prefix => key.startsWith(`content.${prefix}`)))

async function load() {
  const [settings, library] = await Promise.all([
    api<{ content: Settings; version: number }>(cmsUrl('site-settings')),
    collectionId.value ? api<CmsImage[]>(cmsUrl(`collections/${collectionId.value}/images`)) : Promise.resolve([]),
  ])

  data.value = settings
  images.value = library
  saved.value = JSON.stringify(settings.content)
}
async function reload() {
  if (!dirty.value || await confirm(t('Documentation.unsaved'), 'warning'))
    await run(load)
}
async function save() {
  const result = await run(() => api<{ content: Settings; version: number }>(cmsUrl('site-settings'), { method: 'PUT', body: { content: form.value, version: data.value!.version } }), t('Documentation.siteSaved'))
  if (result) {
    data.value = result
    saved.value = JSON.stringify(result.content)
  }
}
function applyPreset(preset: typeof presets[number]) {
  Object.assign(form.value!.theme, { brand_color: preset.brand, hero_from: preset.from, hero_to: preset.to })
}
function moveItem<T>(list: T[], index: number, step: number) {
  const target = index + step
  if (target >= 0 && target < list.length)
    [list[index], list[target]] = [list[target], list[index]]
}

onBeforeRouteLeave(async () => !dirty.value || await confirm(t('Documentation.unsaved'), 'warning'))
useEventListener(window, 'beforeunload', (event: BeforeUnloadEvent) => {
  if (dirty.value)
    event.preventDefault()
})
watch(collectionId, () => run(async () => {
  images.value = collectionId.value ? await api<CmsImage[]>(cmsUrl(`collections/${collectionId.value}/images`)) : []
}))
onMounted(() => run(async () => {
  await context.load()
  await load()
}))
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center gap-3 mb-6">
      <div class="page-title">
        <h1 class="text-h4 font-weight-bold">
          {{ t('Documentation.configuration') }}
        </h1>
        <p class="text-medium-emphasis mb-0">
          {{ t('Documentation.siteHint') }}
        </p>
      </div>
      <VSpacer />
      <VChip
        v-if="dirty"
        color="warning"
        variant="tonal"
        prepend-icon="tabler-point-filled"
      >
        {{ t('Documentation.unsavedLabel') }}
      </VChip>
      <VBtn
        variant="text"
        prepend-icon="tabler-refresh"
        :disabled="busy"
        @click="reload"
      >
        {{ t('Documentation.refresh') }}
      </VBtn>
      <VBtn
        v-if="canEdit"
        prepend-icon="tabler-device-floppy"
        :disabled="busy || !dirty"
        data-test="save-settings"
        @click="save"
      >
        {{ t('Documentation.save') }}
      </VBtn>
    </div>
    <VProgressLinear
      :active="busy"
      indeterminate
      class="mb-3"
    />

    <VRow v-if="form">
      <VCol
        cols="12"
        lg="6"
      >
        <VCard>
          <VTabs
            v-model="tab"
            grow
            density="compact"
          >
            <VTab value="identity">
              {{ t('Documentation.tabs.identity') }}
              <VBadge
                v-if="tabErrors(['title', 'description', 'locale', 'logo'])"
                dot
                color="error"
                inline
              />
            </VTab>
            <VTab value="home">
              {{ t('Documentation.tabs.home') }}
              <VBadge
                v-if="tabErrors(['hero', 'tagline', 'actions', 'features'])"
                dot
                color="error"
                inline
              />
            </VTab>
            <VTab value="navigation">
              {{ t('Documentation.tabs.navigation') }}
              <VBadge
                v-if="tabErrors(['nav', 'github', 'footer'])"
                dot
                color="error"
                inline
              />
            </VTab>
            <VTab
              value="theme"
              data-test="theme-tab"
            >
              {{ t('Documentation.tabs.theme') }}
            </VTab>
          </VTabs>
          <VDivider />
          <fieldset
            :disabled="!canEdit || busy"
            class="settings-fields"
          >
            <VWindow v-model="tab">
              <VWindowItem value="identity">
                <VCardText class="d-flex flex-column gap-4">
                  <VTextField
                    v-model="form.title"
                    :label="t('Documentation.siteTitle')"
                    :error-messages="errorsFor('title')"
                    counter="100"
                    data-test="site-title"
                  />
                  <VTextarea
                    v-model="form.description"
                    :label="t('Documentation.seoDescription')"
                    :hint="t('Documentation.seoHint')"
                    persistent-hint
                    rows="2"
                    auto-grow
                    counter="300"
                  />
                  <VSelect
                    v-model="form.locale"
                    :label="t('Documentation.siteLocale')"
                    :hint="t('Documentation.siteLocaleHint')"
                    persistent-hint
                    :items="[{ title: 'Français', value: 'fr' }, { title: 'English', value: 'en' }]"
                  />
                  <VSelect
                    v-model="form.logo_image_id"
                    :items="imageOptions"
                    clearable
                    :label="t('Documentation.logoImage')"
                    :hint="t('Documentation.siteImageHint')"
                    persistent-hint
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem v-bind="itemProps">
                        <template #prepend>
                          <img
                            :src="imageUrl(item.value)"
                            alt=""
                            class="option-thumb"
                          >
                        </template>
                      </VListItem>
                    </template>
                  </VSelect>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="home">
                <VCardText class="d-flex flex-column gap-4">
                  <VTextField
                    v-model="form.hero_name"
                    :label="t('Documentation.heroName')"
                    :error-messages="errorsFor('hero_name')"
                    counter="100"
                  />
                  <VTextField
                    v-model="form.hero_text"
                    :label="t('Documentation.heroText')"
                    :error-messages="errorsFor('hero_text')"
                    counter="200"
                  />
                  <VTextarea
                    v-model="form.tagline"
                    :label="t('Documentation.tagline')"
                    rows="2"
                    auto-grow
                    counter="500"
                  />
                  <VSelect
                    v-model="form.hero_image_id"
                    :items="imageOptions"
                    clearable
                    :label="t('Documentation.heroImage')"
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem v-bind="itemProps">
                        <template #prepend>
                          <img
                            :src="imageUrl(item.value)"
                            alt=""
                            class="option-thumb"
                          >
                        </template>
                      </VListItem>
                    </template>
                  </VSelect>

                  <div class="d-flex align-center">
                    <h3 class="text-subtitle-1 font-weight-medium">
                      {{ t('Documentation.actions') }}
                    </h3>
                    <VSpacer />
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-plus"
                      :disabled="!canEdit || form.actions.length >= 4"
                      @click="form.actions.push({ text: '', link: '', theme: form.actions.length ? 'alt' : 'brand' })"
                    >
                      {{ t('Documentation.addAction') }}
                    </VBtn>
                  </div>
                  <div
                    v-for="(action, index) in form.actions"
                    :key="`action-${index}`"
                    class="repeat-row"
                  >
                    <VTextField
                      v-model="action.text"
                      density="compact"
                      :label="t('Documentation.label')"
                      :error-messages="errorsFor(`actions.${index}.text`)"
                    />
                    <VTextField
                      v-model="action.link"
                      density="compact"
                      :label="t('Documentation.linkUrl')"
                      placeholder="/guide/v1/introduction.html"
                      :error-messages="errorsFor(`actions.${index}.link`)"
                    />
                    <VSelect
                      v-model="action.theme"
                      density="compact"
                      :label="t('Documentation.buttonStyle')"
                      :items="[{ title: t('Documentation.primary'), value: 'brand' }, { title: t('Documentation.secondary'), value: 'alt' }]"
                    />
                    <div class="repeat-actions">
                      <VBtn
                        icon="tabler-arrow-up"
                        size="x-small"
                        variant="text"
                        :aria-label="t('Documentation.moves.up')"
                        @click="moveItem(form.actions, index, -1)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        size="x-small"
                        variant="text"
                        color="error"
                        :aria-label="t('Documentation.remove')"
                        @click="form.actions.splice(index, 1)"
                      />
                    </div>
                  </div>

                  <div class="d-flex align-center mt-2">
                    <h3 class="text-subtitle-1 font-weight-medium">
                      {{ t('Documentation.features') }}
                    </h3>
                    <VSpacer />
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-plus"
                      :disabled="!canEdit || form.features.length >= 12"
                      @click="form.features.push({ icon: '✨', title: '', details: '', link: null })"
                    >
                      {{ t('Documentation.addFeature') }}
                    </VBtn>
                  </div>
                  <div
                    v-for="(feature, index) in form.features"
                    :key="`feature-${index}`"
                    class="repeat-row repeat-row--feature"
                  >
                    <VTextField
                      v-model="feature.icon"
                      density="compact"
                      :label="t('Documentation.featureIcon')"
                      maxlength="12"
                      class="feature-icon"
                    />
                    <VTextField
                      v-model="feature.title"
                      density="compact"
                      :label="t('Documentation.pageTitle')"
                      :error-messages="errorsFor(`features.${index}.title`)"
                    />
                    <VTextarea
                      v-model="feature.details"
                      density="compact"
                      rows="2"
                      auto-grow
                      :label="t('Documentation.description')"
                      :error-messages="errorsFor(`features.${index}.details`)"
                      class="feature-wide"
                    />
                    <VTextField
                      v-model="feature.link"
                      density="compact"
                      :label="t('Documentation.optionalLink')"
                      :error-messages="errorsFor(`features.${index}.link`)"
                      class="feature-wide"
                    />
                    <div class="repeat-actions">
                      <VBtn
                        icon="tabler-arrow-up"
                        size="x-small"
                        variant="text"
                        :aria-label="t('Documentation.moves.up')"
                        @click="moveItem(form.features, index, -1)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        size="x-small"
                        variant="text"
                        color="error"
                        :aria-label="t('Documentation.remove')"
                        @click="form.features.splice(index, 1)"
                      />
                    </div>
                  </div>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="navigation">
                <VCardText class="d-flex flex-column gap-4">
                  <div class="d-flex align-center">
                    <h3 class="text-subtitle-1 font-weight-medium">
                      {{ t('Documentation.navigation') }}
                    </h3>
                    <VSpacer />
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-plus"
                      :disabled="!canEdit || form.nav.length >= 6"
                      @click="form.nav.push({ text: '', link: '' })"
                    >
                      {{ t('Documentation.addNav') }}
                    </VBtn>
                  </div>
                  <p
                    v-if="!form.nav.length"
                    class="text-body-2 text-medium-emphasis mb-0"
                  >
                    {{ t('Documentation.navDefault') }}
                  </p>
                  <div
                    v-for="(link, index) in form.nav"
                    :key="`nav-${index}`"
                    class="repeat-row repeat-row--nav"
                  >
                    <VTextField
                      v-model="link.text"
                      density="compact"
                      :label="t('Documentation.label')"
                      :error-messages="errorsFor(`nav.${index}.text`)"
                    />
                    <VTextField
                      v-model="link.link"
                      density="compact"
                      :label="t('Documentation.linkUrl')"
                      :error-messages="errorsFor(`nav.${index}.link`)"
                    />
                    <div class="repeat-actions">
                      <VBtn
                        icon="tabler-arrow-up"
                        size="x-small"
                        variant="text"
                        :aria-label="t('Documentation.moves.up')"
                        @click="moveItem(form.nav, index, -1)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        size="x-small"
                        variant="text"
                        color="error"
                        :aria-label="t('Documentation.remove')"
                        @click="form.nav.splice(index, 1)"
                      />
                    </div>
                  </div>
                  <VTextField
                    v-model="form.github_url"
                    label="GitHub"
                    placeholder="https://github.com/…"
                    prepend-inner-icon="tabler-brand-github"
                    :error-messages="errorsFor('github_url')"
                  />
                  <VTextarea
                    v-model="form.footer"
                    :label="t('Documentation.footer')"
                    rows="2"
                    auto-grow
                    counter="500"
                  />
                </VCardText>
              </VWindowItem>

              <VWindowItem value="theme">
                <VCardText class="d-flex flex-column gap-5">
                  <div>
                    <h3 class="text-subtitle-1 font-weight-medium mb-2">
                      {{ t('Documentation.presets') }}
                    </h3>
                    <div class="presets">
                      <button
                        v-for="preset in presets"
                        :key="preset.name"
                        type="button"
                        class="preset"
                        :class="{ 'is-active': form.theme.brand_color === preset.brand }"
                        :disabled="!canEdit"
                        @click="applyPreset(preset)"
                      >
                        <span
                          class="preset-swatch"
                          :style="{ background: `linear-gradient(120deg, ${preset.from}, ${preset.brand} 55%, ${preset.to})` }"
                        />
                        {{ preset.name }}
                      </button>
                    </div>
                  </div>
                  <div class="color-grid">
                    <label
                      v-for="key in (['brand_color', 'hero_from', 'hero_to'] as const)"
                      :key="key"
                      class="color-field"
                    >
                      <input
                        v-model="form.theme[key]"
                        type="color"
                        :aria-label="t(`Documentation.colors.${key}`)"
                        :data-test="`color-${key}`"
                      >
                      <span>
                        <strong>{{ t(`Documentation.colors.${key}`) }}</strong>
                        <code>{{ form.theme[key] }}</code>
                      </span>
                    </label>
                  </div>
                  <VSelect
                    v-model="form.theme.appearance"
                    data-test="appearance-select"
                    :label="t('Documentation.appearance')"
                    :hint="t('Documentation.appearanceHint')"
                    persistent-hint
                    :items="['auto', 'light', 'dark', 'force-light', 'force-dark'].map(value => ({ title: t(`Documentation.appearances.${value}`), value }))"
                  />
                  <VSelect
                    v-model="form.theme.code_theme"
                    :label="t('Documentation.codeTheme')"
                    :items="['auto', 'dark', 'light'].map(value => ({ title: t(`Documentation.codeThemes.${value}`), value }))"
                  />
                  <VSelect
                    v-model="form.theme.layout"
                    :label="t('Documentation.layout')"
                    :items="['default', 'wide'].map(value => ({ title: t(`Documentation.layouts.${value}`), value }))"
                  />
                </VCardText>
              </VWindowItem>
            </VWindow>
          </fieldset>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="6"
      >
        <div class="preview-sticky">
          <div class="d-flex align-center mb-3">
            <h2 class="text-h6">
              {{ t('Documentation.homePreview') }}
            </h2>
            <VSpacer />
            <CmsSegmented
              v-model="previewMode"
              icon-only
              :label="t('Documentation.homePreview')"
              :items="[{ value: 'light', icon: 'tabler-sun', title: t('Documentation.previewLight') }, { value: 'dark', icon: 'tabler-moon', title: t('Documentation.previewDark'), testId: 'preview-dark' }]"
            />
            <VBtn
              :href="publicSiteUrl()"
              target="_blank"
              rel="noopener noreferrer"
              icon="tabler-external-link"
              variant="text"
              size="small"
              class="ms-1"
              :aria-label="t('Documentation.publicSite')"
            />
          </div>
          <div
            class="portal-preview"
            :class="{ 'is-dark': previewDark }"
            :style="previewStyle"
          >
            <div class="portal-nav">
              <span class="portal-brand">
                <img
                  v-if="form.logo_image_id"
                  :src="imageUrl(form.logo_image_id)"
                  alt=""
                >
                <span
                  v-else
                  class="portal-logo"
                />
                {{ form.title }}
              </span>
              <span class="portal-search">⌕ {{ form.locale === 'en' ? 'Search' : 'Rechercher' }}</span>
              <span class="portal-links">
                <span
                  v-for="(link, index) in (form.nav.length ? form.nav : [{ text: form.locale === 'en' ? 'Documentation' : 'Documentations', link: '' }])"
                  :key="index"
                >{{ link.text }}</span>
              </span>
            </div>
            <div class="portal-hero">
              <div>
                <p class="portal-name">
                  {{ form.hero_name }}
                </p>
                <p class="portal-text">
                  {{ form.hero_text }}
                </p>
                <p class="portal-tagline">
                  {{ form.tagline }}
                </p>
                <div class="portal-actions">
                  <span
                    v-for="(action, index) in (form.actions.length ? form.actions : [{ text: form.locale === 'en' ? 'Explore the guides' : 'Explorer les guides', theme: 'brand', link: '' }])"
                    :key="index"
                    :class="action.theme === 'alt' ? 'is-alt' : 'is-brand'"
                  >{{ action.text || '…' }}</span>
                </div>
              </div>
              <div class="portal-image">
                <img
                  v-if="form.hero_image_id"
                  :src="imageUrl(form.hero_image_id)"
                  alt=""
                >
                <VIcon
                  v-else
                  icon="tabler-book-2"
                  size="64"
                />
              </div>
            </div>
            <div class="portal-features">
              <div
                v-for="(feature, index) in form.features"
                :key="index"
                class="portal-feature"
              >
                <span class="portal-feature-icon">{{ feature.icon }}</span>
                <strong>{{ feature.title }}</strong>
                <p>{{ feature.details }}</p>
              </div>
            </div>
            <div
              v-if="form.footer"
              class="portal-footer"
            >
              {{ form.footer }}
            </div>
          </div>
        </div>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.page-title { flex: 1 1 320px; min-width: 0; }
.settings-fields { min-width: 0; border: 0; }
.option-thumb { width: 32px; height: 32px; margin-inline-end: 12px; object-fit: cover; border-radius: 6px; }
.repeat-row { display: grid; grid-template-columns: 1fr 1.4fr 150px auto; gap: 10px; align-items: start; padding: 12px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; }
.repeat-row--nav { grid-template-columns: 1fr 1.6fr auto; }
.repeat-row--feature { grid-template-columns: 90px 1fr auto; }
.repeat-row--feature .feature-wide { grid-column: 1 / 3; }
.repeat-actions { display: flex; gap: 2px; }
.presets { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
.preset { display: flex; flex-direction: column; gap: 6px; padding: 8px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 10px; font-size: 13px; text-align: start; }
.preset.is-active, .preset:hover { border-color: rgb(var(--v-theme-primary)); }
.preset-swatch { height: 30px; border-radius: 6px; }
.color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; }
.color-field { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 10px; cursor: pointer; }
.color-field input { width: 36px; height: 36px; padding: 0; border: 0; border-radius: 8px; background: none; cursor: pointer; }
.color-field span { display: flex; flex-direction: column; font-size: 13px; }
.color-field code { font-size: 12px; color: rgba(var(--v-theme-on-surface), .6); }
.preview-sticky { position: sticky; top: 80px; }
.portal-preview { --bg: #fff; --bg-soft: #f6f6f7; --text: #3c3c43; --text-2: #67676c; --divider: #e2e2e3; overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 16px; background: var(--bg); color: var(--text); font-family: Inter, ui-sans-serif, system-ui, sans-serif; box-shadow: 0 10px 40px -20px rgba(0, 0, 0, .35); transition: background .3s, color .3s; }
.portal-preview.is-dark { --bg: #1b1b1f; --bg-soft: #202127; --text: #dfdfd6; --text-2: #98989f; --divider: #2e2e32; }
.portal-nav { display: flex; align-items: center; gap: 14px; padding: 12px 18px; border-bottom: 1px solid var(--divider); font-size: 12px; }
.portal-brand { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13px; }
.portal-brand img, .portal-logo { width: 22px; height: 22px; border-radius: 6px; object-fit: contain; }
.portal-logo { background: linear-gradient(135deg, var(--hero-from), var(--hero-to)); }
.portal-search { padding: 3px 10px; border: 1px solid var(--divider); border-radius: 8px; color: var(--text-2); }
.portal-links { display: flex; gap: 12px; margin-inline-start: auto; font-weight: 500; }
.portal-hero { display: grid; grid-template-columns: 1.4fr 1fr; gap: 16px; align-items: center; padding: 34px 26px 20px; }
.portal-name { margin: 0; font-size: 30px; font-weight: 800; line-height: 1.15; letter-spacing: -.02em; background: linear-gradient(110deg, var(--hero-from) 15%, var(--hero-to) 85%); background-clip: text; -webkit-background-clip: text; color: transparent; }
.portal-text { margin: 4px 0 0; font-size: 24px; font-weight: 700; line-height: 1.2; }
.portal-tagline { margin: 10px 0 16px; font-size: 13px; color: var(--text-2); }
.portal-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.portal-actions span { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.portal-actions .is-brand { background: var(--brand); color: #fff; }
.portal-actions .is-alt { background: var(--bg-soft); color: var(--text); border: 1px solid var(--divider); }
.portal-image { position: relative; display: grid; place-items: center; min-height: 140px; color: var(--brand); }
.portal-image::before { content: ''; position: absolute; inset: 15%; border-radius: 50%; background: linear-gradient(135deg, var(--hero-from), var(--hero-to)); filter: blur(38px); opacity: .45; }
.portal-image img, .portal-image .v-icon { position: relative; max-width: 100%; max-height: 160px; border-radius: 14px; object-fit: contain; }
.portal-features { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; padding: 8px 26px 26px; }
.portal-feature { padding: 14px; border: 1px solid var(--divider); border-radius: 12px; background: var(--bg-soft); font-size: 12px; }
.portal-feature strong { display: block; margin: 6px 0 4px; font-size: 13px; }
.portal-feature p { margin: 0; color: var(--text-2); }
.portal-feature-icon { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 8px; background: var(--brand-soft); }
.portal-footer { padding: 14px; border-top: 1px solid var(--divider); text-align: center; font-size: 12px; color: var(--text-2); }
@media (max-width: 700px) { .repeat-row, .repeat-row--nav, .repeat-row--feature { grid-template-columns: 1fr; } .repeat-row--feature .feature-wide { grid-column: auto; } .portal-hero { grid-template-columns: 1fr; } }
</style>
