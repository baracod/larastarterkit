export type ConnectionService = 'database' | 'storage' | 'mail' | 'push' | 'horizon' | 'scheduler'

export interface ConnectionCatalog {
  database: { connection: string; driver: string }
  storage: { defaultDisk: string; disks: { name: string; driver: string }[] }
  mail: { mailer: string; transport: string | null }
  push: { connection: string; driver: string }
  scheduler: { cache: string }
  horizon: { connection: string; queue: string }
}

export interface ConnectionResult {
  service: ConnectionService
  status: 'ok' | 'error' | 'not_configured'
  code: string
  durationMs: number
  checkedAt: string
  chainId?: number
  workers?: number
}

export const ConnectionAPI = {
  catalog: () => $api<ConnectionCatalog>('/admin/connections'),
  check: (service: ConnectionService, disk?: string) => $api<ConnectionResult>('/admin/connections/check', {
    method: 'POST',
    body: { service, ...(service === 'storage' ? { disk } : {}) },
    retry: 0,
    timeout: 30000,
  }),
}
