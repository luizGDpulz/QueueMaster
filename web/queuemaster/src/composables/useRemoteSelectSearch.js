import { useRemoteSearch } from 'src/composables/useRemoteSearch'

const DEFAULT_CONTEXT_KEY = 'default'

function normalizeQuery(value) {
  return String(value ?? '').trim()
}

export function useRemoteSelectSearch({
  search,
  mapOptions,
  debounceMs = 350,
  minChars = 0,
  cacheTtlMs = 30000,
}) {
  const remoteSearch = useRemoteSearch({
    search,
    mapResults: mapOptions,
    debounceMs,
    minChars,
    cacheTtlMs,
    initialResults: [],
  })

  const filter = (
    query,
    update,
    abort,
    {
      contextKey = DEFAULT_CONTEXT_KEY,
      force = false,
      onError,
    } = {}
  ) => {
    const normalizedQuery = normalizeQuery(query)

    if (normalizedQuery.length < minChars) {
      remoteSearch.clear()
      update(() => {})
      abort?.()
      return
    }

    remoteSearch.schedule(normalizedQuery, {
      contextKey,
      force,
      onSuccess: () => {
        update(() => {})
      },
      onError: (error) => {
        update(() => {})
        onError?.(error)
      },
    })
  }

  return {
    options: remoteSearch.results,
    loading: remoteSearch.loading,
    searched: remoteSearch.searched,
    load: remoteSearch.run,
    filter,
    clear: remoteSearch.clear,
  }
}
