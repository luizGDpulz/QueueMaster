<template>
  <q-layout view="lHh LpR lff" class="main-layout">
    
    <!-- ===== SIDEBAR (Menu Lateral) ===== -->
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

    <!-- ===== CONTEÚDO PRINCIPAL ===== -->
    <q-page-container class="page-container">
      
      <!-- Header -->
      <header class="main-header">
        <div class="header-left">
          <!-- Botão Menu Mobile -->
          <q-btn
            flat
            round
            dense
            icon="menu"
            class="menu-btn"
            @click="sidebarOpen = !sidebarOpen"
          />
          
          <!-- Breadcrumbs -->
          <nav class="breadcrumbs">
            <span class="breadcrumb-item">Páginas</span>
            <q-icon name="chevron_right" size="18px" class="breadcrumb-separator" />
            <span class="breadcrumb-current">{{ currentPageTitle }}</span>
          </nav>
        </div>

        <div class="header-right">
          <!-- Notificações -->
          <div class="notifications-wrapper">
            <q-btn
              flat
              round
              dense
              icon="notifications"
              class="notification-btn"
              @click="toggleNotifications"
            >
              <q-badge v-if="unreadCount > 0" color="red" floating rounded>{{ unreadCount > 99 ? '99+' : unreadCount }}</q-badge>
            </q-btn>

            <!-- Dropdown de notificações -->
            <div v-if="showNotifications" class="notifications-dropdown">
              <div class="notifications-header">
                <span class="notifications-title">Notificações</span>
                <q-btn v-if="unreadCount > 0" flat dense no-caps size="sm" label="Marcar todas como lidas" @click="markAllRead" />
              </div>
              <div class="notifications-list">
                <div v-if="notifications.length === 0" class="notifications-empty">
                  <q-icon name="notifications_none" size="32px" color="grey-5" />
                  <span>Nenhuma notificação</span>
                </div>
                <div
                  v-for="notif in notifications"
                  :key="notif.id"
                  class="notification-item"
                  :class="{ unread: !notif.read_at }"
                  @click="handleNotificationClick(notif)"
                >
                  <div class="notif-icon">
                    <q-icon :name="getNotifIcon(notif.type)" size="20px" />
                  </div>
                  <div class="notif-content">
                    <span class="notif-title">{{ notif.title }}</span>
                    <span class="notif-body">{{ notif.body }}</span>
                    <span class="notif-time">{{ formatNotifTime(notif.created_at) }}</span>
                    <div v-if="notif.type === 'business_invitation' && !notif.read_at && notif.data?.invitation_id" class="notif-actions" @click.stop>
                      <q-btn dense flat no-caps size="sm" color="positive" icon="check" label="Aceitar" @click="acceptInvitation(notif)" />
                      <q-btn dense flat no-caps size="sm" color="negative" icon="close" label="Rejeitar" @click="rejectInvitation(notif)" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Página -->
      <router-view v-slot="{ Component }">
        <transition name="page-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
      
    </q-page-container>

  </q-layout>
</template>

<script>
import { defineComponent, ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from 'boot/axios'
import { loadBrandColor } from 'src/utils/brand'
import AppSidebar from 'src/components/AppSidebar.vue'

export default defineComponent({
  name: 'MainLayout',

  components: {
    AppSidebar
  },

  setup() {
    const router = useRouter()
    const route = useRoute()

    // ===== SIDEBAR =====
    const sidebarOpen = ref(true)

    // Menu items para o breadcrumb
    const menuItems = [
      { path: '/app', label: 'Dashboard' },
      { path: '/app/businesses', label: 'Negócios' },
      { path: '/app/queues', label: 'Filas' },
      { path: '/app/appointments', label: 'Agendamentos' },
      { path: '/app/establishments', label: 'Estabelecimentos' },
      { path: '/app/admin', label: 'Administração' },
      { path: '/app/settings', label: 'Configurações' }
    ]

    // ===== USUÁRIO =====
    const user = ref(null)

    const userName = computed(() => user.value?.name || 'Usuário')
    const userRole = computed(() => {
      const roles = { admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }
      return roles[user.value?.role] || 'Usuário'
    })
    const userRoleRaw = computed(() => user.value?.role || '')
    const userInitials = computed(() => {
      const name = user.value?.name || 'U'
      return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
    })
    const userAvatar = computed(() => {
      if (!user.value?.id) return ''
      // Use API route for avatar (cached base64 in DB, no Google dependency)
      return `http://localhost/api/v1/users/${user.value.id}/avatar`
    })

    const handleLogout = async () => {
      try {
        await api.post('/auth/logout')
      } catch {
        // Ignora erro — limpa localmente de qualquer forma
      }
      localStorage.removeItem('user')
      router.push('/login')
    }

    // ===== HEADER / NOTIFICATIONS =====
    const notifications = ref([])
    const unreadCount = ref(0)
    const showNotifications = ref(false)
    let notifInterval = null

    const fetchNotifications = async () => {
      try {
        const [listRes, countRes] = await Promise.all([
          api.get('/notifications?per_page=10'),
          api.get('/notifications/unread-count'),
        ])
        // Handle both response shapes (array or {notifications: []})
        const data = listRes.data?.data
        notifications.value = Array.isArray(data) ? data : (data?.notifications ?? [])
        unreadCount.value = countRes.data?.data?.unread_count ?? 0
      } catch {
        // Silently fail — user might not be logged in yet
      }
    }

    const toggleNotifications = () => {
      showNotifications.value = !showNotifications.value
      if (showNotifications.value) {
        fetchNotifications()
      }
    }

    const markAllRead = async () => {
      try {
        await api.post('/notifications/mark-all-read')
        notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString(), is_read: true }))
        unreadCount.value = 0
      } catch { /* ignore */ }
    }

    const handleNotificationClick = async (notif) => {
      if (!notif.read_at) {
        try {
          await api.post(`/notifications/${notif.id}/read`)
          notif.read_at = new Date().toISOString()
          unreadCount.value = Math.max(0, unreadCount.value - 1)
        } catch { /* ignore */ }
      }
      showNotifications.value = false

      // Navigate based on notification type
      if (notif.type === 'business_invitation' || notif.type === 'join_request' || notif.type === 'invitation_accepted') {
        router.push('/app/businesses')
      } else if (notif.type === 'appointment_reminder') {
        router.push('/app/appointments')
      } else if (notif.type === 'queue_called') {
        router.push('/app/queues')
      }
    }

    const getNotifIcon = (type) => {
      const icons = {
        business_invitation: 'business',
        join_request: 'person_add',
        invitation_accepted: 'check_circle',
        invitation_rejected: 'cancel',
        appointment_reminder: 'event',
        queue_called: 'notifications_active',
      }
      return icons[type] || 'notifications'
    }

    const formatNotifTime = (dateStr) => {
      if (!dateStr) return ''
      const date = new Date(dateStr)
      const now = new Date()
      const diffMs = now - date
      const diffMins = Math.floor(diffMs / 60000)
      if (diffMins < 1) return 'agora'
      if (diffMins < 60) return `${diffMins}min`
      const diffHours = Math.floor(diffMins / 60)
      if (diffHours < 24) return `${diffHours}h`
      const diffDays = Math.floor(diffHours / 24)
      return `${diffDays}d`
    }

    const acceptInvitation = async (notif) => {
      const invId = notif.data?.invitation_id
      if (!invId) return
      try {
        await api.post(`/invitations/${invId}/accept`)
        notif.read_at = new Date().toISOString()
        fetchNotifications()
      } catch { /* ignore */ }
    }

    const rejectInvitation = async (notif) => {
      const invId = notif.data?.invitation_id
      if (!invId) return
      try {
        await api.post(`/invitations/${invId}/reject`)
        notif.read_at = new Date().toISOString()
        fetchNotifications()
      } catch { /* ignore */ }
    }

    // Close dropdown when clicking outside
    const handleClickOutside = (e) => {
      if (showNotifications.value && !e.target.closest('.notifications-wrapper')) {
        showNotifications.value = false
      }
    }

    onMounted(() => {
      // Load user
      const savedUser = localStorage.getItem('user')
      if (savedUser) {
        user.value = JSON.parse(savedUser)
      }
      
      // Load theme
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }
      applyTheme()
      loadBrandColor()

      // Notifications
      document.addEventListener('click', handleClickOutside)
      fetchNotifications()
      notifInterval = setInterval(fetchNotifications, 30000)
    })

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside)
      if (notifInterval) clearInterval(notifInterval)
    })

    const currentPageTitle = computed(() => {
      const currentItem = menuItems.find(item => {
        if (item.path === '/app') {
          return route.path === '/app' || route.path === '/app/'
        }
        return route.path.startsWith(item.path)
      })
      return currentItem?.label || 'Dashboard'
    })

    // ===== TEMA =====
    const isDark = ref(false)

    const applyTheme = () => {
      document.documentElement.setAttribute('data-theme', isDark.value ? 'dark' : 'light')
    }

    const toggleTheme = () => {
      isDark.value = !isDark.value
      localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
      applyTheme()
      loadBrandColor()
    }

    return {
      sidebarOpen,
      userName,
      userRole,
      userRoleRaw,
      userInitials,
      userAvatar,
      handleLogout,
      notifications,
      unreadCount,
      showNotifications,
      toggleNotifications,
      markAllRead,
      handleNotificationClick,
      getNotifIcon,
      formatNotifTime,
      acceptInvitation,
      rejectInvitation,
      currentPageTitle,
      isDark,
      toggleTheme
    }
  }
})
</script>

<style lang="scss" scoped>
// ===== MAIN LAYOUT =====
.main-layout {
  background: var(--qm-bg-secondary);
}

// ===== SIDEBAR =====
.sidebar {
  border: none !important;
  box-shadow: none !important;
}

// ===== PAGE CONTAINER =====
.page-container {
  background: var(--qm-bg-secondary);
}

// ===== HEADER =====
.main-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: transparent;
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
}

.breadcrumb-item {
  font-size: 0.875rem;
  color: var(--qm-text-muted);
}

.breadcrumb-separator {
  color: var(--qm-text-muted);
}

.breadcrumb-current {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

// ===== NOTIFICATIONS =====
.notifications-wrapper {
  position: relative;
}

.notification-btn {
  color: var(--qm-text-secondary);
  
  &:hover {
    color: var(--qm-brand);
  }
}

.notifications-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  width: 360px;
  max-height: 480px;
  background: var(--qm-surface);
  border-radius: 1rem;
  box-shadow: var(--qm-shadow-lg, 0 10px 40px rgba(0,0,0,0.15));
  border: 1px solid var(--qm-border);
  z-index: 1000;
  overflow: hidden;
  display: flex;
  flex-direction: column;

  @media (max-width: 480px) {
    width: calc(100vw - 2rem);
    right: -1rem;
  }
}

.notifications-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--qm-border);
}

.notifications-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.notifications-list {
  overflow-y: auto;
  max-height: 400px;
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

  &.unread {
    background: var(--qm-bg-secondary);
    
    .notif-title {
      font-weight: 600;
    }
  }

  &:last-child {
    border-bottom: none;
  }
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
  font-weight: 500;
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
  margin-top: 0.25rem;
}
</style>

<style lang="scss">
// ===== PAGE TRANSITIONS (must be non-scoped) =====
.page-fade-enter-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.page-fade-leave-active {
  transition: opacity 0.15s ease;
}

.page-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}

.page-fade-leave-to {
  opacity: 0;
}
</style>
