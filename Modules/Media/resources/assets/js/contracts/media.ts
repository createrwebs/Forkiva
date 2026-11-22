export interface MediaSummary {
  id: number
  name: string
  download_name: string
  extension: string | null
  mime: string | null
  type: string | false
  download_url: string | null
  url: string | null
  preview_image_url: string
}

export interface MediaItem extends MediaSummary {
  is_file: boolean
  human_size: string
  size: number | null
  uploaded_at: string | null
}
