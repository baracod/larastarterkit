<route lang="json">
{
  "name": "admin-connections",
  "meta": { "action": "administrator" }
}
</route>

<script setup lang="ts">
import { ConnectionAPI } from '../../api/ConnectionAPI'
import type { ConnectionCatalog, ConnectionResult, ConnectionService } from '../../api/ConnectionAPI'

const { t, locale } = useI18n()
const catalog = ref<ConnectionCatalog>()
const disk = ref('')
const loadingCatalog = ref(false)
const catalogError = ref(false)
const results = reactive<Partial<Record<ConnectionService, ConnectionResult>>>({})
const errors = reactive<Partial<Record<ConnectionService, boolean>>>({})
const loading = reactive<Record<ConnectionService, boolean>>({ database: false, storage: false, mail: false, push: false, horizon: false, scheduler: false })
const pushClient = ref<'connected' | 'disconnected' | 'missing'>('missing')

const services: { id: ConnectionService; icon: string }[] = [
  { id: 'database', icon: 'mdi-database-outline' },
  { id: 'storage', icon: 'mdi-cloud-outline' },
  { id: 'mail', icon: 'mdi-email-outline' },
  { id: 'push', icon: 'mdi-bell-ring-outline' },
  { id: 'scheduler', icon: 'mdi-clock-outline' },
  { id: 'horizon', icon: 'mdi-chart-timeline-variant' },
]

const busy = computed(() => Object.values(loading).some(Boolean))

function state(service: ConnectionService) {
  if (loading[service])
    return 'checking'
  if (errors[service] || results[service]?.status === 'error')
    return 'error'
  if (results[service]?.status === 'not_configured' || (service === 'push' && results.push?.status === 'ok' && pushClient.value !== 'connected'))
    return 'warning'

  return results[service]?.status === 'ok' ? 'ok' : 'pending'
}

const overview = computed(() => [
  { key: 'total', value: services.length, icon: 'mdi-server-outline', tone: 'primary' },
  { key: 'operational', value: services.filter(item => state(item.id) === 'ok').length, icon: 'mdi-check-circle-outline', tone: 'success' },
  { key: 'attention', value: services.filter(item => ['warning', 'error'].includes(state(item.id))).length, icon: 'mdi-alert-circle-outline', tone: 'warning' },
  { key: 'pending', value: services.filter(item => ['pending', 'checking'].includes(state(item.id))).length, icon: 'mdi-clock-outline', tone: 'secondary' },
])

function badgeColor(service: ConnectionService) {
  return ({ ok: 'success', error: 'error', warning: 'warning', checking: 'primary', pending: 'secondary' })[state(service)]
}

const disks = computed(() => catalog.value?.storage.disks.map(item => ({
  title: `${item.name} (${item.driver})`, value: item.name,
})) ?? [])

function color(service: ConnectionService) {
  const status = results[service]?.status

  return status === 'ok' ? 'success' : status === 'not_configured' ? 'warning' : 'error'
}

async function loadCatalog() {
  loadingCatalog.value = true
  catalogError.value = false
  try {
    catalog.value = await ConnectionAPI.catalog()
    disk.value = catalog.value.storage.defaultDisk
  }
  catch {
    catalogError.value = true
  }
  finally {
    loadingCatalog.value = false
  }
}

async function check(service: ConnectionService) {
  if (service === 'push') {
    const echo = window.Echo

    pushClient.value = !echo ? 'missing' : echo.connector?.pusher?.connection?.state === 'connected' || echo.connector?.socket?.connected === true ? 'connected' : 'disconnected'
  }
  loading[service] = true
  errors[service] = false
  delete results[service]
  try {
    results[service] = await ConnectionAPI.check(service, disk.value)
  }
  catch {
    errors[service] = true
  }
  finally {
    loading[service] = false
  }
}

async function checkAll() {
  await Promise.allSettled(services.map(service => check(service.id)))
}

watch(disk, () => {
  delete results.storage
  errors.storage = false
})
onMounted(loadCatalog)
</script>

<template>
  <section class="connections-page">
    <div class="connections-header d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div class="header-copy">
        <span class="page-eyebrow">{{ t('Admin.connections.overviewLabel') }}</span>
        <h1 class="page-title mb-2">
          {{ t('Admin.connections.title') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ t('Admin.connections.description') }}
        </p>
      </div>
      <VBtn
        class="check-all"
        prepend-icon="mdi-refresh"
        :loading="busy"
        :disabled="!catalog || loadingCatalog"
        @click="checkAll"
      >
        {{ t('Admin.connections.checkAll') }}
      </VBtn>
    </div>

    <div
      v-if="catalog"
      class="overview-grid"
      aria-live="polite"
    >
      <div
        v-for="item in overview"
        :key="item.key"
        class="overview-item"
      >
        <VAvatar
          :color="item.tone"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon
            :icon="item.icon"
            size="22"
          />
        </VAvatar>
        <div>
          <div class="overview-value">
            {{ item.value }}
          </div>
          <div class="overview-label">
            {{ t(`Admin.connections.summary.${item.key}`) }}
          </div>
        </div>
      </div>
    </div>

    <VAlert
      v-if="catalogError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ t('Admin.connections.requestFailed') }}
      <VBtn
        variant="text"
        @click="loadCatalog"
      >
        {{ t('Admin.connections.retry') }}
      </VBtn>
    </VAlert>
    <VProgressLinear
      v-if="loadingCatalog"
      indeterminate
      :aria-label="t('Admin.connections.loading')"
    />

    <VRow v-if="catalog">
      <VCol
        v-for="service in services"
        :key="service.id"
        cols="12"
        lg="6"
      >
        <VCard
          class="connection-card h-100 d-flex flex-column"
          :class="`state-${state(service.id)}`"
          variant="flat"
        >
          <VProgressLinear
            v-if="loading[service.id]"
            indeterminate
            color="primary"
            height="2"
            class="card-progress"
          />
          <VCardItem class="service-heading">
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
              >
                <VIcon :icon="service.icon" />
              </VAvatar>
            </template>
            <VCardTitle>{{ t(`Admin.connections.${service.id}`) }}</VCardTitle>
            <template #append>
              <VChip
                :color="badgeColor(service.id)"
                size="small"
                variant="tonal"
                class="state-chip"
              >
                <span
                  class="status-dot"
                  aria-hidden="true"
                />
                {{ t(`Admin.connections.badges.${state(service.id)}`) }}
              </VChip>
            </template>
          </VCardItem>

          <VCardText class="flex-grow-1">
            <p class="service-description">
              {{ t(`Admin.connections.${service.id}Description`) }}
            </p>
            <div class="service-details">
              <p v-if="service.id === 'database'">
                {{ t('Admin.connections.connection') }} : <strong>{{ catalog.database.connection }} ({{ catalog.database.driver }})</strong>
              </p>
              <p v-if="service.id === 'mail'">
                {{ t('Admin.connections.mailer') }} : <strong>{{ catalog.mail.mailer }} ({{ catalog.mail.transport || t('Admin.connections.notConfigured') }})</strong>
              </p>
              <template v-if="service.id === 'push'">
                <p>{{ t('Admin.connections.connection') }} : <strong>{{ catalog.push.connection }} ({{ catalog.push.driver }})</strong></p>
                <VAlert
                  v-if="results.push"
                  :type="pushClient === 'connected' ? 'info' : 'warning'"
                  variant="tonal"
                  class="mb-4"
                >
                  {{ t(`Admin.connections.pushClient.${pushClient}`) }}
                </VAlert>
              </template>
              <template v-if="service.id === 'horizon'">
                <p>{{ t('Admin.connections.connection') }} : <strong>{{ catalog.horizon.connection }}</strong></p>
                <p>{{ t('Admin.connections.queue') }} : <strong>{{ catalog.horizon.queue }}</strong></p>
                <p v-if="results.horizon?.workers !== undefined">
                  {{ t('Admin.connections.workers', { count: results.horizon.workers }) }}
                </p>
              </template>

              <VSelect
                v-if="service.id === 'storage'"
                v-model="disk"
                :label="t('Admin.connections.disk')"
                :items="disks"
                :disabled="loading.storage"
                class="mb-4"
              />

              <p v-if="service.id === 'scheduler'">
                {{ t('Admin.connections.sharedCache') }} : <strong>{{ catalog.scheduler.cache }}</strong>
              </p>
            </div>

            <div
              class="diagnostic-result"
              aria-live="polite"
            >
              <VAlert
                v-if="results[service.id]"
                :type="color(service.id)"
                variant="tonal"
              >
                <div class="font-weight-medium mb-1">
                  {{ t(`Admin.connections.status.${results[service.id]!.status}`) }}
                </div>
                {{ t(`Admin.connections.codes.${results[service.id]!.code}`) }}
              </VAlert>
              <VAlert
                v-else-if="errors[service.id]"
                type="error"
                variant="tonal"
              >
                {{ t('Admin.connections.requestFailed') }}
              </VAlert>
              <p
                v-else
                class="text-medium-emphasis"
              >
                {{ t(loading[service.id] ? 'Admin.connections.checking' : 'Admin.connections.notTested') }}
              </p>
            </div>
            <div
              v-if="results[service.id]"
              class="check-metadata text-caption text-medium-emphasis mt-4"
            >
              <div>{{ t('Admin.connections.duration', { ms: results[service.id]!.durationMs }) }}</div>
              <div>{{ t('Admin.connections.checkedAt', { date: new Date(results[service.id]!.checkedAt).toLocaleString(locale) }) }}</div>
            </div>
          </VCardText>

          <VCardActions class="card-footer">
            <VBtn
              variant="text"
              prepend-icon="mdi-refresh"
              :loading="loading[service.id]"
              :disabled="service.id === 'storage' && !disk"
              @click="check(service.id)"
            >
              {{ t('Admin.connections.check') }}
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
  </section>
</template>

<style scoped>
.connections-page {
  min-inline-size: 0;
  padding-block: 8px 24px;
}

.connections-header {
  padding-block: 8px 12px;
}

.header-copy {
  min-inline-size: 0;
  max-inline-size: 700px;
}

.page-eyebrow {
  display: block;
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  margin-block-end: 10px;
  text-transform: uppercase;
}

.page-title {
  font-size: clamp(1.5rem, 2.5vw, 2rem);
  font-weight: 700;
  letter-spacing: -0.035em;
  line-height: 1.2;
}

.check-all {
  border-radius: 10px;
  min-block-size: 44px;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-block-end: 28px;
}

.overview-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
}

.overview-value {
  font-size: 1.6rem;
  font-weight: 700;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
}

.overview-label {
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8rem;
  margin-block-start: 4px;
}

.connection-card {
  position: relative;
  min-inline-size: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 16px;
}

.connection-card.state-error {
  border-color: rgba(var(--v-theme-error), 0.35);
}

.card-progress {
  position: absolute;
  inset-block-start: 0;
}

.service-heading {
  padding: 20px 20px 16px;
}

.state-chip {
  font-weight: 600;
}

.status-dot {
  inline-size: 6px;
  block-size: 6px;
  border-radius: 50%;
  background: currentcolor;
  margin-inline-end: 6px;
}

.service-description {
  color: rgba(var(--v-theme-on-surface), 0.7);
  line-height: 1.65;
}

.service-details {
  padding: 14px 16px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  margin-block: 18px;
  font-size: 0.8rem;
}

.service-details p {
  margin-block-end: 8px;
}

.service-details > :last-child {
  margin-block-end: 0 !important;
}

.diagnostic-result :deep(.v-alert) {
  border-radius: 10px;
  font-size: 0.8rem;
  line-height: 1.6;
}

.check-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 16px;
  font-variant-numeric: tabular-nums;
}

.card-footer {
  justify-content: flex-end;
  padding: 10px 16px;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.connection-card :deep(.v-card-text),
.connection-card :deep(.v-alert__content) {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.connection-card :deep(.v-card-title) {
  font-size: 1rem;
  font-weight: 600;
  white-space: normal;
  overflow-wrap: anywhere;
}

.connection-card :deep(.v-btn__content) {
  white-space: normal;
}

@media (max-width: 959px) {
  .overview-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 599px) {
  .connections-header {
    align-items: stretch !important;
    flex-direction: column;
  }

  .check-all {
    inline-size: 100%;
  }

  .overview-grid {
    gap: 10px;
  }

  .overview-item {
    align-items: flex-start;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
  }

  .service-heading :deep(.v-card-item__append) {
    grid-column: 2;
    padding-inline-start: 0;
    padding-block-start: 8px;
  }

  .connection-card :deep(.v-card-item),
  .connection-card :deep(.v-card-text) {
    padding-inline: 16px;
  }

  .card-footer :deep(.v-btn) {
    inline-size: 100%;
    min-block-size: 44px;
  }
}
</style>
