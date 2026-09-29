export interface Edition { id: number; collection_id: number; title: string; slug: string; published_at: string | null; archived_at: string | null; created_at?: string; updated_at?: string }
export interface Collection { id: number; title: string; slug: string; description: string; default_edition_id: number | null; published_at: string | null; archived_at: string | null; editions: Edition[]; updated_at?: string }
export interface Author { id: number; name: string }
export interface Revision { id: number; page_id: number; title: string; path: string; markdown: string; parent_id: number | null; position: number; author_id: number | null; author?: Author | null; note?: string | null; created_at: string }
export interface Page { id: number; edition_id: number; current_revision_id: number; archived_at: string | null; revision: Revision; updated_at?: string }
export interface ImageUsage { page_id: number; edition_id: number; title: string; edition: string | null }
export interface CmsImage { id: number; name: string; alt?: string | null; mime: string; width: number; height: number; size?: number; archived_at: string | null; created_at?: string; usage?: ImageUsage[] }
export interface Publication {
  id: number
  edition_id: number | null
  kind: string
  status: 'queued' | 'building' | 'succeeded' | 'failed'
  log: string | null
  created_at: string
  finished_at: string | null
  author?: Author | null
  edition?: (Edition & { collection?: Pick<Collection, 'id' | 'title' | 'slug'> }) | null
}
