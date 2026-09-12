import apiClient from './client'

export interface ReleaseHighlight {
  tag?: 'feature' | 'fix' | 'improvement'
  text: string
}
export interface ReleaseNote {
  id: number
  version: string | null
  title: string
  highlights: ReleaseHighlight[]
  publishedAt: string | null
}

/** Notes newer than the user's last-seen marker (drives the login panel + bell dot). */
export async function fetchUnseenReleaseNotes(): Promise<{ count: number; data: ReleaseNote[] }> {
  const { data } = await apiClient.get<{ count: number; data: ReleaseNote[] }>('/release-notes/unseen')
  return data
}

/** Full published changelog, newest first. */
export async function fetchReleaseNotes(): Promise<ReleaseNote[]> {
  const { data } = await apiClient.get<{ data: ReleaseNote[] }>('/release-notes')
  return data.data
}

/** Mark all current notes as seen for this user (clears the bell dot). */
export async function markReleaseNotesSeen(): Promise<void> {
  await apiClient.post('/release-notes/seen')
}
