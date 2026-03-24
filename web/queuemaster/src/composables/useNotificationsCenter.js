import { computed, ref } from 'vue'
import { api } from 'boot/axios'

const unreadNotifications = ref([])
const unreadCount = ref(0)
const inboxNotifications = ref([])
const inboxMeta = ref({
  page: 1,
  per_page: 20,
  total: 0,
  total_pages: 1,
})
const unreadLoading = ref(false)
const inboxLoading = ref(false)
const preferences = ref({
  push_enabled: false,
})
const previewNotification = ref(null)
const streamConnected = ref(false)
const browserPermission = ref(
  typeof window !== 'undefined' && 'Notification' in window
    ? window.Notification.permission
    : 'unsupported'
)

let stream = null
let reconnectTimer = null
let previewTimer = null

const actionableTypes = new Set(['business_invitation'])
const notificationCenterTypes = new Set([
  'business_invitation',
  'professional_request_created',
  'invitation_accepted',
  'invitation_rejected',
  'manager_role_request',
  'manager_request_created',
  'manager_request_accepted',
  'manager_request_rejected',
  'role_reverted_client',
])

const notificationTypeOptions = [
  { label: 'Todos os tipos', value: '' },
  { label: 'Convites profissionais', value: 'business_invitation' },
  { label: 'Pedidos de gerência', value: 'manager_role_request' },
  { label: 'Gerência criada', value: 'manager_request_created' },
  { label: 'Gerência aprovada', value: 'manager_request_accepted' },
  { label: 'Gerência recusada', value: 'manager_request_rejected' },
  { label: 'Solicitações criadas', value: 'professional_request_created' },
  { label: 'Solicitações aprovadas', value: 'invitation_accepted' },
  { label: 'Solicitações recusadas', value: 'invitation_rejected' },
  { label: 'Perfil cliente', value: 'role_reverted_client' },
  { label: 'Fila', value: 'queue_called' },
  { label: 'Agendamentos', value: 'appointment_reminder' },
]

function normalizeNotification(notification) {
  return {
    ...notification,
    data: notification?.data && typeof notification.data === 'object' ? notification.data : {},
    is_read: Boolean(notification?.read_at || notification?.is_read),
  }
}

function upsertById(list, notification) {
  const normalized = normalizeNotification(notification)
  const index = list.findIndex((item) => Number(item.id) === Number(normalized.id))
  if (index >= 0) {
    list.splice(index, 1, { ...list[index], ...normalized })
  } else {
    list.unshift(normalized)
  }
}

function refreshUnreadCount() {
  unreadCount.value = unreadNotifications.value.filter((item) => !item.read_at).length
}

function applyReadStateToMany(ids) {
  const targets = new Set(ids.map((id) => Number(id)))
  const stamp = new Date().toISOString()

  unreadNotifications.value = unreadNotifications.value
    .map((item) => (targets.has(Number(item.id)) ? { ...item, read_at: stamp, is_read: true } : item))
    .filter((item) => !item.read_at)

  inboxNotifications.value = inboxNotifications.value.map((item) => (
    targets.has(Number(item.id)) ? { ...item, read_at: stamp, is_read: true } : item
  ))

  refreshUnreadCount()
}

function applyReadState(id) {
  const stamp = new Date().toISOString()
  unreadNotifications.value = unreadNotifications.value.map((item) => (
    Number(item.id) === Number(id) ? { ...item, read_at: stamp, is_read: true } : item
  )).filter((item) => !item.read_at)
  inboxNotifications.value = inboxNotifications.value.map((item) => (
    Number(item.id) === Number(id) ? { ...item, read_at: stamp, is_read: true } : item
  ))
  refreshUnreadCount()
}

function removeNotificationsByIds(ids) {
  const targets = new Set(ids.map((id) => Number(id)))
  const inboxBefore = inboxNotifications.value.length

  unreadNotifications.value = unreadNotifications.value.filter((item) => !targets.has(Number(item.id)))
  inboxNotifications.value = inboxNotifications.value.filter((item) => !targets.has(Number(item.id)))

  const removedFromInbox = inboxBefore - inboxNotifications.value.length
  if (removedFromInbox > 0) {
    inboxMeta.value = {
      ...inboxMeta.value,
      total: Math.max(0, (inboxMeta.value.total || 0) - removedFromInbox),
    }
  }

  refreshUnreadCount()
}

function getNotifIcon(type) {
  const icons = {
    business_invitation: 'business',
    manager_role_request: 'admin_panel_settings',
    manager_request_created: 'assignment_ind',
    manager_request_accepted: 'verified',
    manager_request_rejected: 'gpp_bad',
    professional_request_created: 'assignment_turned_in',
    role_reverted_client: 'person_off',
    invitation_accepted: 'check_circle',
    invitation_rejected: 'cancel',
    appointment_reminder: 'event',
    queue_called: 'notifications_active',
  }
  return icons[type] || 'notifications'
}

function getNotificationTypeLabel(type) {
  return notificationTypeOptions.find((item) => item.value === type)?.label || 'Notificação'
}

function formatNotifTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'agora'
  if (diffMins < 60) return `${diffMins} min`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours} h`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays} d`
}

function buildStreamUrl() {
  const rawBase = api?.defaults?.baseURL || '/api/v1'
  const base = rawBase.endsWith('/') ? rawBase.slice(0, -1) : rawBase
  return `${base}/streams/notifications`
}

function maybeShowBrowserNotification(notification) {
  if (!preferences.value.push_enabled) return
  if (browserPermission.value !== 'granted') return
  if (typeof window === 'undefined' || !('Notification' in window)) return

  const nativeNotification = new window.Notification(notification.title || 'Nova notificação', {
    body: notification.body || '',
    tag: `qm-notification-${notification.id}`,
  })

  nativeNotification.onclick = () => {
    window.focus()
    nativeNotification.close()
  }
}

function showPreview(notification) {
  previewNotification.value = {
    id: notification.id,
    title: notification.title,
  }

  if (previewTimer) window.clearTimeout(previewTimer)
  previewTimer = window.setTimeout(() => {
    previewNotification.value = null
  }, 3000)
}

function handleIncomingNotification(notification) {
  const normalized = normalizeNotification(notification)
  if (!normalized.read_at) {
    upsertById(unreadNotifications.value, normalized)
    refreshUnreadCount()
  }
  upsertById(inboxNotifications.value, normalized)
  showPreview(normalized)
  maybeShowBrowserNotification(normalized)
}

async function fetchPreferences() {
  const response = await api.get('/notifications/preferences')
  preferences.value = response.data?.data?.preferences || { push_enabled: false }
  return preferences.value
}

async function setPushEnabled(enabled) {
  if (enabled) {
    if (typeof window === 'undefined' || !('Notification' in window)) {
      throw new Error('Notificações do navegador não são suportadas neste dispositivo.')
    }

    if (window.Notification.permission === 'default') {
      const permission = await window.Notification.requestPermission()
      browserPermission.value = permission
      if (permission !== 'granted') {
        await api.put('/notifications/preferences', { push_enabled: false })
        preferences.value = { ...preferences.value, push_enabled: false }
        throw new Error('Permissão de notificação não concedida.')
      }
    } else if (window.Notification.permission !== 'granted') {
      browserPermission.value = window.Notification.permission
      throw new Error('Permissão de notificação bloqueada no navegador.')
    }
  }

  const response = await api.put('/notifications/preferences', { push_enabled: enabled })
  preferences.value = response.data?.data?.preferences || { push_enabled: enabled }
  return preferences.value
}

async function fetchUnreadNotifications(limit = 10) {
  unreadLoading.value = true
  try {
    const [listRes, countRes] = await Promise.all([
      api.get('/notifications', { params: { unread: true, per_page: limit } }),
      api.get('/notifications/unread-count'),
    ])

    unreadNotifications.value = (listRes.data?.data || []).map(normalizeNotification)
    unreadCount.value = countRes.data?.data?.unread_count ?? unreadNotifications.value.length
  } finally {
    unreadLoading.value = false
  }
}

async function fetchInbox(filters = {}) {
  inboxLoading.value = true
  try {
    const params = {
      page: filters.page || 1,
      per_page: filters.per_page || inboxMeta.value.per_page || 20,
      type: filters.type || undefined,
      search: filters.search || undefined,
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined,
    }
    const response = await api.get('/notifications', { params })
    inboxNotifications.value = (response.data?.data || []).map(normalizeNotification)
    inboxMeta.value = {
      page: response.data?.meta?.pagination?.page ?? params.page,
      per_page: response.data?.meta?.pagination?.per_page ?? params.per_page,
      total: response.data?.meta?.pagination?.total ?? inboxNotifications.value.length,
      total_pages: response.data?.meta?.pagination?.total_pages ?? 1,
    }
  } finally {
    inboxLoading.value = false
  }
}

async function markNotificationRead(notification) {
  const normalized = typeof notification === 'object'
    ? notification
    : unreadNotifications.value.find((item) => Number(item.id) === Number(notification))
      || inboxNotifications.value.find((item) => Number(item.id) === Number(notification))

  if (!normalized || normalized.read_at) {
    return
  }

  await api.post(`/notifications/${normalized.id}/read`)
  applyReadState(normalized.id)
}

async function markAllNotificationsRead() {
  await api.post('/notifications/mark-all-read')
  const stamp = new Date().toISOString()
  unreadNotifications.value = []
  unreadCount.value = 0
  inboxNotifications.value = inboxNotifications.value.map((item) => ({
    ...item,
    read_at: item.read_at || stamp,
    is_read: true,
  }))
}

async function markNotificationsRead(ids = []) {
  const normalizedIds = Array.from(new Set((ids || []).map((id) => Number(id)).filter((id) => id > 0)))
  if (normalizedIds.length === 0) return

  await api.post('/notifications/batch-read', { ids: normalizedIds })
  applyReadStateToMany(normalizedIds)
}

async function deleteNotifications(ids = []) {
  const normalizedIds = Array.from(new Set((ids || []).map((id) => Number(id)).filter((id) => id > 0)))
  if (normalizedIds.length === 0) return

  await api.post('/notifications/batch-delete', { ids: normalizedIds })
  removeNotificationsByIds(normalizedIds)
}

async function fetchNotificationById(id) {
  if (!id) return null

  const response = await api.get(`/notifications/${id}`)
  const notification = response.data?.data?.notification || null
  return notification ? normalizeNotification(notification) : null
}

function resolveNotificationRoute(notification) {
  if (notificationCenterTypes.has(notification.type)) {
    return `/app/settings?tab=notifications&notification=${notification.id}`
  }

  if (notification?.data?.deep_link) return notification.data.deep_link

  if (notification.type === 'business_invitation' || notification.type === 'invitation_accepted' || notification.type === 'invitation_rejected') {
    return '/app/businesses'
  }
  if (notification.type === 'professional_request_created') {
    return '/app/settings?tab=roles'
  }
  if (notification.type === 'manager_role_request' || notification.type === 'manager_request_created' || notification.type === 'manager_request_accepted' || notification.type === 'manager_request_rejected' || notification.type === 'role_reverted_client') {
    return '/app/settings?tab=roles'
  }
  if (notification.type === 'appointment_reminder') {
    return '/app/appointments'
  }
  if (notification.type === 'queue_called') {
    return '/app/queues'
  }
  return null
}

async function openNotification(router, notification) {
  if (!notification.read_at) {
    await markNotificationRead(notification)
  }

  const target = resolveNotificationRoute(notification)
  if (target) {
    router.push(target)
  }
}

async function acceptInvitation(notification, payload = {}) {
  const invitationId = notification?.data?.invitation_id
  if (!invitationId) return

  await api.post(`/invitations/${invitationId}/accept`, payload)
  await markNotificationRead(notification)
  await Promise.all([fetchUnreadNotifications(), fetchInbox({ page: inboxMeta.value.page, per_page: inboxMeta.value.per_page })])
}

async function rejectInvitation(notification, payload = {}) {
  const invitationId = notification?.data?.invitation_id
  if (!invitationId) return

  await api.post(`/invitations/${invitationId}/reject`, payload)
  await markNotificationRead(notification)
  await Promise.all([fetchUnreadNotifications(), fetchInbox({ page: inboxMeta.value.page, per_page: inboxMeta.value.per_page })])
}

function connectStream() {
  if (typeof window === 'undefined' || stream) return

  stream = new window.EventSource(buildStreamUrl(), { withCredentials: true })

  stream.addEventListener('open', () => {
    streamConnected.value = true
  })

  stream.addEventListener('notification', (event) => {
    try {
      const payload = JSON.parse(event.data)
      handleIncomingNotification(payload)
    } catch {
      // ignore malformed SSE payloads
    }
  })

  stream.addEventListener('error', () => {
    streamConnected.value = false
    if (stream) {
      stream.close()
      stream = null
    }

    if (reconnectTimer) window.clearTimeout(reconnectTimer)
    reconnectTimer = window.setTimeout(() => {
      connectStream()
    }, 5000)
  })
}

function disconnectStream() {
  if (reconnectTimer) {
    window.clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
  if (stream) {
    stream.close()
    stream = null
  }
  streamConnected.value = false
}

const hasUnreadNotifications = computed(() => unreadCount.value > 0)

export function useNotificationsCenter() {
  return {
    unreadNotifications,
    unreadCount,
    unreadLoading,
    inboxNotifications,
    inboxMeta,
    inboxLoading,
    preferences,
    previewNotification,
    streamConnected,
    browserPermission,
    notificationTypeOptions,
    actionableTypes,
    hasUnreadNotifications,
    getNotifIcon,
    getNotificationTypeLabel,
    formatNotifTime,
    fetchPreferences,
    setPushEnabled,
    fetchUnreadNotifications,
    fetchInbox,
    fetchNotificationById,
    markNotificationRead,
    markAllNotificationsRead,
    markNotificationsRead,
    deleteNotifications,
    openNotification,
    acceptInvitation,
    rejectInvitation,
    connectStream,
    disconnectStream,
  }
}
