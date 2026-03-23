import { reactive, readonly } from 'vue'

/**
 * useBreadcrumb — shared reactive state for dynamic breadcrumb labels.
 *
 * Child pages call `setDetail(label)` to add a detail segment to the breadcrumb.
 * MainLayout reads `state.detail` and appends it after the section title.
 * Call `clearDetail()` in onUnmounted to clean up.
 */
const state = reactive({
  detail: '',
})

export function useBreadcrumb() {
  const setDetail = (label) => {
    state.detail = label || ''
  }

  const clearDetail = () => {
    state.detail = ''
  }

  return {
    state: readonly(state),
    setDetail,
    clearDetail,
  }
}
