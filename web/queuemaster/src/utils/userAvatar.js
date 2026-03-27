const DEFAULT_API_URL = 'http://localhost/api/v1'
const ABSOLUTE_URL_PATTERN = /^https?:\/\//i

const getApiBaseUrl = () => String(import.meta.env.VITE_API_URL || DEFAULT_API_URL).replace(/\/$/, '')

const buildApiUrl = (path) => {
  if (!path) return ''
  if (ABSOLUTE_URL_PATTERN.test(path)) return path
  return `${getApiBaseUrl()}${path.startsWith('/') ? '' : '/'}${path}`
}

export function resolveUserAvatarUrl(user) {
  if (!user?.id || user?.has_avatar === false) {
    return ''
  }

  const avatarPath = user.avatar_path || `/users/${user.id}/avatar`
  const avatarUrl = buildApiUrl(avatarPath)
  const cacheKey = user.avatar_cache_key || user.updated_at || user.last_login_at || user.created_at || ''

  if (!cacheKey) {
    return avatarUrl
  }

  const separator = avatarUrl.includes('?') ? '&' : '?'
  return `${avatarUrl}${separator}v=${encodeURIComponent(String(cacheKey))}`
}
