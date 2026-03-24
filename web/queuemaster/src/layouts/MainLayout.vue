<template>
  <q-layout view="lHh LpR lff" class="main-layout">
    <q-drawer
      v-model="sidebarOpen"
      :width="260"
      :breakpoint="1024"
      show-if-above
      :bordered="false"
      :elevated="false"
      class="sidebar"
    >
      <AppSidebar
        :user-name="userName"
        :user-role="userRole"
        :user-role-raw="userRoleRaw"
        :user-initials="userInitials"
        :user-avatar="userAvatar"
        :is-dark="isDark"
        @logout="handleLogout"
        @toggle-theme="toggleTheme"
      />
    </q-drawer>

    <q-page-container class="page-container">
      <header class="main-header">
        <div class="header-left">
          <q-btn
            flat
            round
            dense
            icon="menu"
            class="menu-btn"
            @click="sidebarOpen = !sidebarOpen"
          />

          <nav class="breadcrumbs">
            <span class="breadcrumb-item">Páginas</span>
            <q-icon name="chevron_right" size="18px" class="breadcrumb-separator" />
            <span
              :class="breadcrumbDetail ? 'breadcrumb-item breadcrumb-link' : 'breadcrumb-current'"
              @click="breadcrumbDetail ? $router.push(breadcrumbParentPath) : null"
            >
              {{ currentPageTitle }}
            </span>
            <template v-if="breadcrumbDetail">
              <q-icon name="chevron_right" size="18px" class="breadcrumb-separator" />
              <span class="breadcrumb-current">{{ breadcrumbDetail }}</span>
            </template>
          </nav>
        </div>

        <div class="header-right">
          <div ref="notificationsWrapper" class="notifications-wrapper">
            <transition name="notif-pill">
              <div v-if="previewNotification" class="notification-preview-pill">
                <q-icon name="notifications_active" size="16px" />
                <span>{{ previewNotification.title }}</span>
              </div>
            </transition>

            <q-btn
              flat
              round
              dense
              icon="notifications"
              class="notification-btn"
              @click="toggleNotifications"
            >
              <q-badge v-if="unreadCount > 0" color="red" floating rounded>
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </q-badge>
            </q-btn>

            <div v-if="showNotifications" class="notifications-dropdown">
              <div class="notifications-header">
                <div>
                  <div class="notifications-title">Não lidas</div>
                  <div class="notifications-subtitle">
                    {{ streamConnected ? 'Tempo real ativo' : 'Atualização automática' }}
                  </div>
                </div>
                <div class="notifications-header-actions">
                  <q-btn flat dense no-caps size="sm" label="Inbox" @click="openInbox" />
                  <q-btn
                    v-if="unreadCount > 0"
                    flat
                    dense
                    no-caps
                    size="sm"
                    label="Marcar todas"
                    @click="handleMarkAllRead"
                  />
                </div>
              </div>

              <div class="notifications-list">
                <div v-if="unreadLoading" class="notifications-empty">
                  <q-spinner-dots color="primary" size="28px" />
                </div>

                <div v-else-if="unreadNotifications.length === 0" class="notifications-empty">
                  <q-icon name="notifications_none" size="32px" color="grey-5" />
                  <span>Nenhuma notificação não lida</span>
                </div>

                <div
                  v-for="notif in unreadNotifications"
                  :key="notif.id"
                  class="notification-item unread"
                  @click="handleNotificationClick(notif)"
                >
                  <div class="notif-icon">
                    <q-icon :name="getNotifIcon(notif.type)" size="20px" />
                  </div>
                  <div class="notif-content">
                    <span class="notif-title">{{ notif.title }}</span>
                    <span class="notif-body">{{ notif.body }}</span>
                    <span class="notif-time">{{ formatNotifTime(notif.sent_at || notif.created_at) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div class="page-spacer"></div>

      <router-view v-slot="{ Component }">
        <transition name="page-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </q-page-container>
  </q-layout>
</template>

<script>
import { computed, defineComponent, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { loadBrandColor } from 'src/utils/brand'
import AppSidebar from 'src/components/AppSidebar.vue'
import { useBreadcrumb } from 'src/composables/useBreadcrumb'
import { useNotificationsCenter } from 'src/composables/useNotificationsCenter'

export default defineComponent({
  name: 'MainLayout',

  components: {
    AppSidebar,
  },

  setup() {
    const router = useRouter()
    const route = useRoute()
    const notificationsWrapper = ref(null)
    const sidebarOpen = ref(true)
    const showNotifications = ref(false)
    const user = ref(null)
    const isDark = ref(false)

    const {
      unreadNotifications,
      unreadCount,
      unreadLoading,
      previewNotification,
      streamConnected,
      getNotifIcon,
      formatNotifTime,
      fetchPreferences,
      fetchUnreadNotifications,
      markAllNotificationsRead,
      openNotification,
      connectStream,
      disconnectStream,
    } = useNotificationsCenter()

    const menuItems = [
      { path: '/app', label: 'Dashboard' },
      { path: '/app/businesses', label: 'Negócios' },
      { path: '/app/queues', label: 'Filas' },
      { path: '/app/reports', label: 'Relatórios' },
      { path: '/app/appointments', label: 'Agendamentos' },
      { path: '/app/establishments', label: 'Estabelecimentos' },
      { path: '/app/admin', label: 'Administração' },
      { path: '/app/settings', label: 'Configurações' },
    ]

    const userName = computed(() => user.value?.name || 'Usuário')
    const userRole = computed(() => {
      const roles = { admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }
      return roles[user.value?.role] || 'Usuário'
    })
    const userRoleRaw = computed(() => user.value?.role || '')
    const userInitials = computed(() => {
      const name = user.value?.name || 'U'
      return name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase()
    })
    const userAvatar = computed(() => {
      if (!user.value?.id) return ''
      return `${import.meta.env.VITE_API_URL || 'http://localhost/api/v1'}/users/${user.value.id}/avatar`
    })

    const fetchCurrentUser = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success && response.data?.data?.user) {
          user.value = response.data.data.user
          localStorage.setItem('user', JSON.stringify(response.data.data.user))
        }
      } catch {
        // ignore: axios interceptor handles auth redirects
      }
    }

    const handleLogout = async () => {
      try {
        await api.post('/auth/logout')
      } catch {
        // ignore
      }

      disconnectStream()
      localStorage.removeItem('user')
      router.push('/login')
    }

    const toggleNotifications = async () => {
      showNotifications.value = !showNotifications.value
      if (showNotifications.value) {
        await fetchUnreadNotifications()
      }
    }

    const handleNotificationClick = async (notification) => {
      try {
        await openNotification(router, notification)
      } finally {
        showNotifications.value = false
      }
    }

    const handleMarkAllRead = async () => {
      await markAllNotificationsRead()
    }

    const openInbox = () => {
      showNotifications.value = false
      router.push('/app/settings?tab=notifications')
    }

    const handleClickOutside = (event) => {
      if (showNotifications.value && notificationsWrapper.value && !notificationsWrapper.value.contains(event.target)) {
        showNotifications.value = false
      }
    }

    const { state: breadcrumbState } = useBreadcrumb()
    const breadcrumbDetail = computed(() => breadcrumbState.detail)

    const breadcrumbParentPath = computed(() => {
      const currentItem = menuItems.find((item) => {
        if (item.path === '/app') return route.path === '/app' || route.path === '/app/'
        return route.path.startsWith(item.path)
      })
      return currentItem?.path || '/app'
    })

    const currentPageTitle = computed(() => {
      const currentItem = menuItems.find((item) => {
        if (item.path === '/app') return route.path === '/app' || route.path === '/app/'
        return route.path.startsWith(item.path)
      })
      return currentItem?.label || 'Dashboard'
    })

    const applyTheme = () => {
      document.documentElement.setAttribute('data-theme', isDark.value ? 'dark' : 'light')
    }

    const toggleTheme = () => {
      isDark.value = !isDark.value
      localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
      applyTheme()
      loadBrandColor()
    }

    onMounted(async () => {
      const savedUser = localStorage.getItem('user')
      if (savedUser) {
        try {
          user.value = JSON.parse(savedUser)
        } catch {
          // ignore invalid cache
        }
      }

      await fetchCurrentUser()

      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }

      applyTheme()
      loadBrandColor()

      document.addEventListener('click', handleClickOutside)
      await Promise.all([fetchPreferences(), fetchUnreadNotifications()])
      connectStream()
    })

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside)
      disconnectStream()
    })

    return {
      sidebarOpen,
      notificationsWrapper,
      userName,
      userRole,
      userRoleRaw,
      userInitials,
      userAvatar,
      handleLogout,
      showNotifications,
      unreadNotifications,
      unreadCount,
      unreadLoading,
      previewNotification,
      streamConnected,
      getNotifIcon,
      formatNotifTime,
      toggleNotifications,
      handleNotificationClick,
      handleMarkAllRead,
      openInbox,
      currentPageTitle,
      breadcrumbDetail,
      breadcrumbParentPath,
      isDark,
      toggleTheme,
    }
  },
})
</script>

<style lang="scss" scoped>
.main-layout {
  background: var(--qm-bg-secondary);
}

.sidebar {
  border: none !important;
  box-shadow: none !important;
}

.page-container {
  background: var(--qm-bg-secondary);
}

.main-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: transparent;
  gap: 1rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.menu-btn {
  color: var(--qm-text-secondary);

  @media (min-width: 1024px) {
    display: none;
  }
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  min-width: 0;
  flex-wrap: wrap;
}

.breadcrumb-item {
  font-size: 0.875rem;
  color: var(--qm-text-muted);
  min-width: 0;
}

.breadcrumb-link {
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }
}

.breadcrumb-separator {
  color: var(--qm-text-muted);
}

.breadcrumb-current {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-spacer {
  height: 0.5rem;
}

.notifications-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  min-width: 56px;
}

.notification-btn {
  color: var(--qm-text-secondary);
  background: color-mix(in srgb, var(--qm-surface) 70%, transparent);
  border: 1px solid var(--qm-border-light);

  &:hover {
    color: var(--qm-brand);
  }
}

.notification-preview-pill {
  position: absolute;
  right: 52px;
  top: 50%;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  max-width: 320px;
  padding: 0.625rem 0.875rem;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--qm-brand), color-mix(in srgb, var(--qm-brand) 70%, #081220));
  color: #fff;
  box-shadow: var(--qm-shadow-lg, 0 16px 36px rgba(0, 0, 0, 0.18));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  z-index: 5;
  pointer-events: none;
}

.notifications-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 380px;
  max-height: 520px;
  background: var(--qm-surface);
  border-radius: 1rem;
  box-shadow: var(--qm-shadow-lg, 0 10px 40px rgba(0, 0, 0, 0.15));
  border: 1px solid var(--qm-border);
  z-index: 1000;
  overflow: hidden;
  display: flex;
  flex-direction: column;

  @media (max-width: 480px) {
    width: calc(100vw - 2rem);
    right: -0.75rem;
  }
}

.notifications-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--qm-border);
}

.notifications-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.notifications-subtitle {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  margin-top: 0.125rem;
}

.notifications-header-actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.notifications-list {
  overflow-y: auto;
  max-height: 420px;
}

.notifications-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2rem;
  color: var(--qm-text-muted);
  font-size: 0.875rem;
}

.notification-item {
  display: flex;
  gap: 0.75rem;
  padding: 0.875rem 1.25rem;
  cursor: pointer;
  transition: background 0.15s ease;
  border-bottom: 1px solid var(--qm-border);

  &:hover {
    background: var(--qm-bg-tertiary);
  }

  &:last-child {
    border-bottom: none;
  }
}

.notification-item.unread {
  background: var(--qm-bg-secondary);
}

.notif-icon {
  width: 36px;
  height: 36px;
  border-radius: 0.5rem;
  background: var(--qm-bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--qm-brand);
  flex-shrink: 0;
}

.notif-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.notif-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notif-body {
  font-size: 0.75rem;
  color: var(--qm-text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notif-time {
  font-size: 0.6875rem;
  color: var(--qm-text-muted);
}

.notif-actions {
  display: flex;
  gap: 0.375rem;
  margin-top: 0.375rem;
  flex-wrap: wrap;
}

@media (max-width: 768px) {
  .main-header {
    padding: 1rem;
  }

  .header-left {
    gap: 0.75rem;
    min-width: 0;
  }

  .breadcrumbs {
    display: none;
  }
}

@media (max-width: 480px) {
  .notifications-wrapper {
    position: static;
  }

  .notification-preview-pill {
    display: none;
  }
}
</style>

<style lang="scss">
.page-fade-enter-active {
  transition: opacity 0.25s ease;
}

.page-fade-leave-active {
  transition: opacity 0.15s ease;
}

.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}

.notif-pill-enter-active,
.notif-pill-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease, width 0.25s ease;
}

.notif-pill-enter-from,
.notif-pill-leave-to {
  opacity: 0;
  transform: translateY(-50%) translateX(12px) scaleX(0.85);
}
</style>
