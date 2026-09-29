<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, withBase } from 'vitepress'
import portal from '../portal.json'
import { labelsFor } from './i18n'
import { searchPortal } from './search'

const labels = labelsFor((portal.site as { locale?: string }).locale)
const route = useRoute()
const query = ref('')
const active = ref(0)
const dialog = ref<HTMLDialogElement>()
const input = ref<HTMLInputElement>()
const trigger = ref<HTMLButtonElement>()
const list = ref<HTMLUListElement>()
const current = computed(() => portal.editions.find(edition => route.path.startsWith(withBase(`/${edition.prefix}/`))))
const editions = computed(() => portal.editions.filter(edition => edition.collection === current.value?.collection))
const results = computed(() => searchPortal(portal.search, query.value, current.value?.prefix))

watch(query, () => {
  active.value = 0
})

async function openSearch() {
  dialog.value?.showModal()
  await nextTick()
  input.value?.focus()
  input.value?.select()
}
function shortcut(event: KeyboardEvent) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    openSearch()
  }
}
function close() {
  dialog.value?.close()
}
async function move(step: number) {
  if (!results.value.length)
    return
  active.value = (active.value + step + results.value.length) % results.value.length
  await nextTick()
  list.value?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' })
}
function openActive() {
  const target = results.value[active.value]
  if (target) {
    close()
    window.location.href = target.url
  }
}
function selectEdition(event: Event) {
  window.location.href = withBase(`/${(event.target as HTMLSelectElement).value}/index.html`)
}
onMounted(() => window.addEventListener('keydown', shortcut))
onBeforeUnmount(() => window.removeEventListener('keydown', shortcut))
</script>

<template>
  <div class="cms-nav-tools">
    <button
      ref="trigger"
      class="cms-search-trigger"
      :aria-label="labels.searchAria"
      @click="openSearch"
    >
      <svg
        width="15"
        height="15"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.2"
        aria-hidden="true"
      ><circle
        cx="11"
        cy="11"
        r="7"
      /><path d="m20 20-3.5-3.5" /></svg>
      <span class="cms-search-label">{{ labels.search }}</span> <kbd>Ctrl K</kbd>
    </button>
    <label
      v-if="current && editions.length > 1"
      class="cms-edition-label"
    >
      <span class="cms-sr-only">{{ labels.edition }}</span>
      <select
        :value="current.prefix"
        :aria-label="labels.edition"
        @change="selectEdition"
      >
        <option
          v-for="edition in editions"
          :key="edition.prefix"
          :value="edition.prefix"
        >{{ edition.title }}</option>
      </select>
    </label>
    <Teleport to="body">
      <dialog
        ref="dialog"
        class="cms-search-dialog"
        :aria-label="labels.searchDialog"
        @click="($event.target === dialog) && close()"
        @close="trigger?.focus()"
      >
        <form
          method="dialog"
          @submit.prevent="openActive"
        >
          <label for="cms-search">{{ current ? `${current.collectionTitle} · ${current.title}` : labels.allDocs }}</label>
          <div class="cms-search-row">
            <input
              id="cms-search"
              ref="input"
              v-model="query"
              type="search"
              :placeholder="labels.searchPlaceholder"
              autocomplete="off"
              role="combobox"
              aria-controls="cms-search-results"
              :aria-expanded="!!results.length"
              :aria-activedescendant="results.length ? `cms-result-${active}` : undefined"
              @keydown.down.prevent="move(1)"
              @keydown.up.prevent="move(-1)"
            >
            <button
              type="button"
              :aria-label="labels.closeSearch"
              @click="close"
            >
              Esc
            </button>
          </div>
        </form>
        <ul
          v-if="query.trim()"
          id="cms-search-results"
          ref="list"
          class="cms-search-results"
          role="listbox"
          aria-live="polite"
        >
          <li
            v-for="(result, index) in results"
            :id="`cms-result-${index}`"
            :key="result.url"
            role="option"
            :aria-selected="index === active"
            :class="{ 'is-active': index === active }"
            @mouseenter="active = index"
          >
            <a
              :href="result.url"
              @click="close"
            >
              <strong>
                <template
                  v-for="(part, key) in result.title"
                  :key="key"
                ><mark v-if="part.match">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template>
              </strong>
              <small>
                <template
                  v-for="(part, key) in result.excerpt"
                  :key="key"
                ><mark v-if="part.match">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template>
              </small>
            </a>
          </li>
          <li
            v-if="!results.length"
            class="cms-search-empty"
          >
            {{ labels.noResults }}
          </li>
        </ul>
        <p
          v-else
          class="cms-search-empty"
        >
          {{ current ? labels.searchIn : labels.searchPortal }}
        </p>
        <div class="cms-search-footer">
          <span><kbd>↑</kbd><kbd>↓</kbd>{{ labels.navigate }}</span>
          <span><kbd>↵</kbd>{{ labels.open }}</span>
          <span><kbd>Esc</kbd>{{ labels.closeSearch }}</span>
        </div>
      </dialog>
    </Teleport>
  </div>
</template>
