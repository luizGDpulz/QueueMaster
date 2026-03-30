import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'

const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api/v1'

const api = axios.create({
  baseURL: apiBaseUrl,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: true,
})

let isRefreshing = false
let failedQueue = []

const inflightGetRequests = new Map()

const processQueue = (error) => {
  failedQueue.forEach((promiseHandlers) => {
    if (error) {
      promiseHandlers.reject(error)
    } else {
      promiseHandlers.resolve()
    }
  })

  failedQueue = []
}

const redirectToLogin = () => {
  localStorage.removeItem('user')

  if (window.location.pathname !== '/login') {
    window.location.href = '/login'
  }
}

const isPlainObject = (value) => (
  value !== null
  && typeof value === 'object'
  && !Array.isArray(value)
  && Object.prototype.toString.call(value) === '[object Object]'
)

const stableSerialize = (value) => {
  if (Array.isArray(value)) {
    return value.map(stableSerialize)
  }

  if (isPlainObject(value)) {
    return Object.keys(value)
      .sort()
      .reduce((result, key) => {
        const serializedValue = stableSerialize(value[key])
        if (serializedValue !== undefined) {
          result[key] = serializedValue
        }
        return result
      }, {})
  }

  return value === undefined ? undefined : value
}

const buildGetRequestKey = (url, config = {}) => {
  if (!url || config.signal || config.meta?.dedupe === false) {
    return null
  }

  return JSON.stringify(stableSerialize({
    baseURL: config.baseURL || api.defaults.baseURL || '',
    url,
    params: config.params || {},
    responseType: config.responseType || 'json',
  }))
}

const wrapGetWithDedupe = () => {
  const originalGet = api.get.bind(api)

  api.get = (url, config = {}) => {
    const requestKey = buildGetRequestKey(url, config)

    if (!requestKey) {
      return originalGet(url, config)
    }

    const pendingRequest = inflightGetRequests.get(requestKey)
    if (pendingRequest) {
      return pendingRequest
    }

    const requestPromise = originalGet(url, config).finally(() => {
      if (inflightGetRequests.get(requestKey) === requestPromise) {
        inflightGetRequests.delete(requestKey)
      }
    })

    inflightGetRequests.set(requestKey, requestPromise)
    return requestPromise
  }
}

const parseInteger = (value) => {
  const parsed = Number.parseInt(String(value ?? ''), 10)
  return Number.isFinite(parsed) ? parsed : null
}

const formatDurationPtBr = (seconds) => {
  const safeSeconds = Math.max(0, Number(seconds) || 0)

  if (safeSeconds < 60) {
    return `${safeSeconds} ${safeSeconds === 1 ? 'segundo' : 'segundos'}`
  }

  const minutes = Math.ceil(safeSeconds / 60)
  if (minutes < 60) {
    return `${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`
  }

  const hours = Math.ceil(minutes / 60)
  return `${hours} ${hours === 1 ? 'hora' : 'horas'}`
}

const getRetryAfterSeconds = (response) => {
  const details = response?.data?.error?.details || {}
  const fromBody = parseInteger(details.retry_after_seconds)
  if (fromBody !== null) return fromBody

  const fromHeader = parseInteger(response?.headers?.['retry-after'])
  if (fromHeader !== null) return fromHeader

  const resetAt = parseInteger(response?.headers?.['x-ratelimit-reset'])
  if (resetAt !== null) {
    return Math.max(0, resetAt - Math.floor(Date.now() / 1000))
  }

  return null
}

const buildRateLimitMessage = (response) => {
  const currentMessage = response?.data?.error?.message

  if (typeof currentMessage === 'string') {
    const trimmedMessage = currentMessage.trim()
    if (trimmedMessage && trimmedMessage.toLowerCase() !== 'too many requests') {
      return trimmedMessage
    }
  }

  const details = response?.data?.error?.details || {}
  const limit = parseInteger(details.limit ?? response?.headers?.['x-ratelimit-limit'])
  const windowSeconds = parseInteger(details.window_seconds ?? response?.headers?.['x-ratelimit-window'])
  const retryAfterSeconds = getRetryAfterSeconds(response)

  const messageParts = []

  if (limit !== null && windowSeconds !== null) {
    messageParts.push(`Você excedeu o limite de ${limit} requisições em ${formatDurationPtBr(windowSeconds)}.`)
  } else {
    messageParts.push('Você excedeu o limite de requisições da API.')
  }

  if (retryAfterSeconds !== null) {
    messageParts.push(`Tente novamente em ${formatDurationPtBr(retryAfterSeconds)}.`)
  }

  return messageParts.join(' ')
}

const normalizeRateLimitError = (error) => {
  if (error.response?.status !== 429) {
    return
  }

  const message = buildRateLimitMessage(error.response)

  if (isPlainObject(error.response.data)) {
    if (isPlainObject(error.response.data.error)) {
      error.response.data.error.message = message
    } else {
      error.response.data.message = message
    }
  }
}

wrapGetWithDedupe()

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    normalizeRateLimitError(error)

    const originalRequest = error.config || {}

    if (error.response?.status === 401 && !originalRequest._retry) {
      const skipRefreshRoutes = ['/auth/google', '/auth/refresh', '/auth/logout']
      if (skipRefreshRoutes.some((route) => originalRequest.url?.includes(route))) {
        return Promise.reject(error)
      }

      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        })
          .then(() => api(originalRequest))
          .catch((refreshError) => Promise.reject(refreshError))
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        const response = await axios.post(
          `${apiBaseUrl}/auth/refresh`,
          {},
          { withCredentials: true }
        )

        if (response.data?.success) {
          const { user } = response.data.data

          if (user) {
            localStorage.setItem('user', JSON.stringify(user))
          }

          processQueue(null)
          return api(originalRequest)
        }

        processQueue(error)
        redirectToLogin()
        return Promise.reject(error)
      } catch (refreshError) {
        processQueue(refreshError)
        redirectToLogin()
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    return Promise.reject(error)
  }
)

export default defineBoot(({ app }) => {
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
