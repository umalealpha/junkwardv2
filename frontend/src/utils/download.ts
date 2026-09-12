/**
 * Download a file the API generates, through the API client.
 *
 * WHY THIS EXISTS. A plain <a href="/api/v1/..."> looks like it works and does not:
 *
 *  · the href is root-relative, so the browser resolves it against the FRONTEND
 *    host. The API lives somewhere else — every working call goes through axios,
 *    which prepends VITE_API_URL. So the request never reaches the backend; the
 *    SPA's catch-all route answers it with "Page Not Found" instead.
 *  · a browser navigation carries no Authorization header. This app holds a Sanctum
 *    bearer token in localStorage and the axios interceptor attaches it, so even
 *    pointed at the right host a bare link would come back 401.
 *
 * Fixing only the host turns "Page Not Found" into 401, which is why both have to
 * be handled in one place. Going through apiClient gets the configured base URL,
 * the token, and the 401-refresh-and-retry behaviour for free.
 */
import apiClient from '../api/client'

/** Filename from Content-Disposition, so the server keeps naming its own files. */
function filenameFrom(headers: unknown, fallback: string): string {
  const cd = (headers as Record<string, string> | undefined)?.['content-disposition'] ?? ''
  const match = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)

  return match ? decodeURIComponent(match[1]).trim() : fallback
}

/**
 * An error response arrives as a Blob because responseType is 'blob', so the
 * message the API took the trouble to write is unreadable unless it is unpacked.
 * Without this the user gets "Request failed with status code 422" instead of the
 * reason.
 */
async function messageFromBlob(data: unknown, fallback: string): Promise<string> {
  if (!(data instanceof Blob)) return fallback

  try {
    const parsed = JSON.parse(await data.text())
    return parsed?.message || fallback
  } catch {
    return fallback
  }
}

/**
 * Fetch a generated file and hand it to the browser.
 *
 * @param path     API path WITHOUT the /api/v1 prefix — apiClient adds it.
 * @param params   Query string values. Empty and null values are dropped.
 * @param fallback Filename to use if the response does not name one.
 * @throws Error carrying the API's own message, so a caller can toast it.
 */
export async function downloadFromApi(
  path: string,
  params: Record<string, unknown> = {},
  fallback = 'download',
): Promise<void> {
  const clean = Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''),
  )

  let res
  try {
    res = await apiClient.get(path, { params: clean, responseType: 'blob' })
  } catch (err: any) {
    throw new Error(await messageFromBlob(
      err?.response?.data,
      err?.message || 'The file could not be downloaded.',
    ))
  }

  const url = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filenameFrom(res.headers, fallback)

  // Appended before clicking, and revoked on the next tick rather than
  // immediately — a detached anchor and a revoked URL both make the click a
  // no-op in some browsers, which reads to the user as "nothing happened".
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(url), 0)
}
