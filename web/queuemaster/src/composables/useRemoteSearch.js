import axios from 'axios'
import { ref } from 'vue'

const DEFAULT_CONTEXT_KEY = 'default'

function normalizeQuery(value) {
  return String(value ?? '').trim()
}

function isCanceled(error) {
  return axios.isCancel?.(error) || error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError'
}

function resolveEmptyValue(initialResults) {
  return Array.isArray(initialResults) ? [] : initialResults
}

export function useRemoteSearch({
  search,
  mapResults = (response) => response,
  debounceMs = 350,
  minChars = 0,
  cacheTtlMs = 30000,
  initialResults = [],
}) {
  const results = ref(resolveEmptyValue(initialResults))
  const loading = ref(false)
  const searched = ref(false)

  const cache = new Map()

  let debounceTimer = null
  let currentController = null
  let currentRequest = null
  let currentRequestKey = ''
  let latestRequestId = 0

  const clearDebounce = () => {
    if (debounceTimer && typeof window !== 'undefined') {
      window.clearTimeout(debounceTimer)
      debounceTimer = null
    }
  }

  const abortCurrentRequest = () => {
    if (currentController) {
      currentController.abort()
      currentController = null
    }
  }

  const buildRequestKey = (query, contextKey = DEFAULT_CONTEXT_KEY) => (
    `${contextKey}::${normalizeQuery(query).toLowerCase()}`
  )

  const getCachedResults = (requestKey) => {
    const entry = cache.get(requestKey)
    if (!entry) return null

    if (Date.now() - entry.createdAt > cacheTtlMs) {
      cache.delete(requestKey)
      return null
    }

    return entry.results
  }

  const storeCache = (requestKey, nextResults) => {
    cache.set(requestKey, {
      results: nextResults,
      createdAt: Date.now(),
    })
  }

  const setResults = (nextResults) => {
    results.value = nextResults
  }

  const clear = ({ resetCache = false, keepSearched = false, value = resolveEmptyValue(initialResults) } = {}) => {
    latestRequestId += 1
    clearDebounce()
    abortCurrentRequest()
    currentRequest = null
    currentRequestKey = ''
    loading.value = false
    setResults(value)

    if (!keepSearched) {
      searched.value = false
    }

    if (resetCache) {
      cache.clear()
    }
  }

  const run = async (
    query = '',
    {
      contextKey = DEFAULT_CONTEXT_KEY,
      force = false,
      markSearched = true,
      clearOnMinQuery = true,
    } = {}
  ) => {
    clearDebounce()

    const normalizedQuery = normalizeQuery(query)

    if (normalizedQuery.length < minChars) {
      latestRequestId += 1
      abortCurrentRequest()
      currentRequest = null
      currentRequestKey = ''
      loading.value = false

      if (clearOnMinQuery) {
        setResults(resolveEmptyValue(initialResults))
      }

      if (markSearched) {
        searched.value = false
      }

      return results.value
    }

    const requestKey = buildRequestKey(normalizedQuery, contextKey)

    if (currentRequest && currentRequestKey !== requestKey) {
      latestRequestId += 1
      abortCurrentRequest()
      currentRequest = null
      currentRequestKey = ''
    }

    if (!force && currentRequest && currentRequestKey === requestKey) {
      return currentRequest
    }

    if (!force) {
      const cachedResults = getCachedResults(requestKey)
      if (cachedResults) {
        setResults(cachedResults)
        loading.value = false
        if (markSearched) {
          searched.value = true
        }
        return cachedResults
      }
    }

    abortCurrentRequest()

    const controller = new AbortController()
    currentController = controller
    currentRequestKey = requestKey
    const requestId = ++latestRequestId

    if (markSearched) {
      searched.value = true
    }
    loading.value = true

    const request = search({
      query: normalizedQuery,
      signal: controller.signal,
      contextKey,
    })
      .then((response) => {
        const nextResults = mapResults(response)
        storeCache(requestKey, nextResults)
        if (requestId === latestRequestId) {
          setResults(nextResults)
        }
        return nextResults
      })
      .catch((error) => {
        if (isCanceled(error)) {
          return requestId === latestRequestId ? results.value : resolveEmptyValue(initialResults)
        }

        if (requestId === latestRequestId) {
          setResults(resolveEmptyValue(initialResults))
        }
        throw error
      })
      .finally(() => {
        if (currentController === controller) {
          currentController = null
        }
        if (currentRequest === request) {
          currentRequest = null
          currentRequestKey = ''
        }
        if (requestId === latestRequestId) {
          loading.value = false
        }
      })

    currentRequest = request
    return request
  }

  const schedule = (
    query,
    {
      contextKey = DEFAULT_CONTEXT_KEY,
      force = false,
      markSearched = true,
      clearOnMinQuery = true,
      onSuccess,
      onError,
    } = {}
  ) => {
    const normalizedQuery = normalizeQuery(query)

    if (normalizedQuery.length < minChars) {
      clear({
        keepSearched: !markSearched,
        value: clearOnMinQuery ? resolveEmptyValue(initialResults) : results.value,
      })
      onSuccess?.(results.value)
      return
    }

    clearDebounce()

    if (typeof window === 'undefined') {
      run(normalizedQuery, { contextKey, force, markSearched, clearOnMinQuery })
        .then((nextResults) => onSuccess?.(nextResults))
        .catch((error) => onError?.(error))
      return
    }

    debounceTimer = window.setTimeout(async () => {
      try {
        const nextResults = await run(normalizedQuery, {
          contextKey,
          force,
          markSearched,
          clearOnMinQuery,
        })
        onSuccess?.(nextResults)
      } catch (error) {
        onError?.(error)
      }
    }, debounceMs)
  }

  return {
    results,
    loading,
    searched,
    run,
    schedule,
    clear,
    setResults,
  }
}
