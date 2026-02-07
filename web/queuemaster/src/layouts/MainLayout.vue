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
        :user-initials="userInitials"
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
          <!-- Campo de Busca -->
          <div class="search-wrapper">
            <q-input
              v-model="searchQuery"
              placeholder="Buscar..."
              dense
              outlined
              class="search-input"
            >
              <template #prepend>
                <q-icon name="search" size="20px" class="search-icon" />
              </template>
            </q-input>
          </div>
        </div>
      </header>

      <!-- Página -->
      <router-view />
      
    </q-page-container>

  </q-layout>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
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

    onMounted(() => {
      const savedUser = localStorage.getItem('user')
      if (savedUser) {
        user.value = JSON.parse(savedUser)
      }
      
      // Carregar tema
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }
      applyTheme()
      loadBrandColor()
    })

    const userName = computed(() => user.value?.name || 'Usuário')
    const userRole = computed(() => {
      const roles = { admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }
      return roles[user.value?.role] || 'Usuário'
    })
    const userInitials = computed(() => {
      const name = user.value?.name || 'U'
      return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
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

    // ===== HEADER =====
    const searchQuery = ref('')

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
      userInitials,
      handleLogout,
      searchQuery,
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

// ===== SEARCH =====
.search-wrapper {
  width: 200px;

  @media (max-width: 600px) {
    width: 150px;
  }
}

.search-input {
  :deep(.q-field__control) {
    border-radius: 0.75rem !important;
    background: var(--qm-surface) !important;
    
    &::before {
      border: 1px solid var(--qm-border) !important;
    }
  }

  :deep(.q-field__native) {
    color: var(--qm-text-primary) !important;
    font-size: 0.875rem;

    &::placeholder {
      color: var(--qm-text-muted) !important;
    }
  }
}

.search-icon {
  color: var(--qm-text-muted);
}
</style>
