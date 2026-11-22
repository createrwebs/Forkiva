export interface DatabaseCapabilities {
  driver: 'mysql' | 'mariadb' | 'pgsql' | 'sqlite' | string
  supported: boolean
  backup_available: boolean
  restore_available: boolean
  missing_tools: string[]
  format: 'sql' | 'sqlite' | string
  max_upload_mb: number
  safety_backup_required: boolean
  maintenance_guard: boolean
  queue_guard: boolean
  lock_scope: 'distributed' | 'single_node'
  retention_count: number
  preflight_ready: boolean
  readiness_blockers: Array<
    | 'database_tools'
    | 'distributed_lock'
    | 'database_cache'
    | 'backup_storage'
    | 'maintenance_guard'
    | 'queue_guard'
    | string
  >
}

export interface DatabaseBackup {
  name: string
  size: number
  size_human: string
  created_at: string
  driver: string | null
  format: string
  checksum_sha256: string | null
  engine: string | null
}

export interface DatabaseToolIndex {
  database: DatabaseCapabilities
  backups: DatabaseBackup[]
}
