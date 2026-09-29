<script setup lang="ts">
defineOptions({
  name: 'CoreServerAutocomplete',
  inheritAttrs: false,
})

const props = withDefaults(defineProps<{
  endpoint: string
  queryParam?: string
  minSearchLength?: number
  debounceMs?: number
  params?: Record<string, string | number | boolean | null | undefined>
  preload?: boolean
}>(), {
  queryParam: 'search',
  minSearchLength: 2,
  debounceMs: 350,
  params: () => ({}),
  preload: false,
})

const emit = defineEmits(['update:modelValue'])

interface ServerAutocompleteOption {
  [key: string]: unknown
}

interface ServerAutocompleteResponse {
  data?: ServerAutocompleteOption[]
  items?: ServerAutocompleteOption[]
}

const items = ref<ServerAutocompleteOption[]>([])
const loading = ref(false)
const search = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const normalizedParams = computed(() => Object.fromEntries(
  Object.entries(props.params).filter(([, value]) => value !== null && value !== undefined && value !== ''),
))

async function fetchItems(term = ''): Promise<void> {
  if (!props.preload && term.length < props.minSearchLength) {
    items.value = []

    return
  }

  loading.value = true

  try {
    const response = await $api<ServerAutocompleteResponse | ServerAutocompleteOption[]>(props.endpoint, {
      query: {
        ...normalizedParams.value,
        [props.queryParam]: term,
      },
    })

    if (Array.isArray(response))
      items.value = response

    else
      items.value = response.data ?? response.items ?? []
  }
  finally {
    loading.value = false
  }
}

function onSearch(term: string): void {
  search.value = term

  if (searchTimeout)
    clearTimeout(searchTimeout)

  searchTimeout = setTimeout(() => {
    fetchItems(term)
  }, props.debounceMs)
}

watch(() => props.endpoint, () => {
  items.value = []
  search.value = ''

  if (props.preload)
    fetchItems()
})

onMounted(() => {
  if (props.preload)
    fetchItems()
})

onBeforeUnmount(() => {
  if (searchTimeout)
    clearTimeout(searchTimeout)
})
</script>

<template>
  <CoreCol v-bind="$attrs">
    <AppAutocomplete
      v-bind="$attrs"
      v-model:search="search"
      :items="items"
      :loading="loading"
      no-filter
      variant="outlined"
      @update:search="onSearch"
      @update:model-value="emit('update:modelValue', $event)"
    >
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </AppAutocomplete>
  </CoreCol>
</template>
