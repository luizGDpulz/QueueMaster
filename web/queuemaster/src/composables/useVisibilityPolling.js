import { onBeforeUnmount } from 'vue'

export function useVisibilityPolling(
  callback,
  {
    intervalMs = 30000,
    enabled = true,
    runWhenHidden = false,
    runOnResume = true,
  } = {}
) {
  let timer = null
  let pollingInFlight = false

  const isEnabled = () => (
    typeof enabled === 'function' ? Boolean(enabled()) : Boolean(enabled)
  )

  const stop = () => {
    if (timer && typeof window !== 'undefined') {
      window.clearInterval(timer)
      timer = null
    }
  }

  const execute = async () => {
    if (pollingInFlight || !isEnabled()) {
      return
    }

    if (!runWhenHidden && typeof document !== 'undefined' && document.hidden) {
      return
    }

    pollingInFlight = true
    try {
      await callback()
    } finally {
      pollingInFlight = false
    }
  }

  const start = ({ immediate = false } = {}) => {
    if (typeof window === 'undefined') {
      return
    }

    stop()

    if (!isEnabled()) {
      return
    }

    if (immediate) {
      execute()
    }

    timer = window.setInterval(() => {
      execute()
    }, intervalMs)
  }

  const handleVisibilityChange = () => {
    if (typeof document === 'undefined') {
      return
    }

    if (document.hidden) {
      stop()
      return
    }

    start({ immediate: runOnResume })
  }

  const handleOnline = () => {
    start({ immediate: runOnResume })
  }

  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', handleVisibilityChange)
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('online', handleOnline)
  }

  onBeforeUnmount(() => {
    stop()

    if (typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }

    if (typeof window !== 'undefined') {
      window.removeEventListener('online', handleOnline)
    }
  })

  return {
    start,
    stop,
    execute,
  }
}
