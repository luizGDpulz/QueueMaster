import { computed, ref } from 'vue'

export function useSingleFlightAction() {
  const pendingMap = ref({})

  const normalizeKey = (key) => String(key ?? 'default')

  const isRunning = (key = 'default') => Boolean(pendingMap.value[normalizeKey(key)])

  const run = async (key, action) => {
    const normalizedKey = normalizeKey(key)

    if (isRunning(normalizedKey)) {
      return null
    }

    pendingMap.value = {
      ...pendingMap.value,
      [normalizedKey]: true,
    }

    try {
      return await action()
    } finally {
      const nextPendingMap = { ...pendingMap.value }
      delete nextPendingMap[normalizedKey]
      pendingMap.value = nextPendingMap
    }
  }

  return {
    run,
    isRunning,
    hasPending: computed(() => Object.keys(pendingMap.value).length > 0),
  }
}
