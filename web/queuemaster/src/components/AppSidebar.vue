<template>
  <div class="sidebar-content">
    
    <!-- Logo -->
    <div class="sidebar-header">
      <div class="logo">
        <svg class="logo-img" width="36" height="36" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M40 110L100 140L160 110L100 80L40 110Z" fill="currentColor" opacity="0.7"/>
          <path d="M40 110V130L100 160V140L40 110Z" fill="currentColor" opacity="0.85"/>
          <path d="M160 110V130L100 160V140L160 110Z" fill="currentColor" opacity="0.95"/>
          <path d="M40 90L100 120L160 90L100 60L40 90Z" fill="currentColor" opacity="0.4"/>
          <path d="M40 90V110L100 140V120L40 90Z" fill="currentColor" opacity="0.55"/>
          <path d="M160 90V110L100 140V120L160 90Z" fill="currentColor" opacity="0.7"/>
          <g transform="translate(20, -20)">
            <path d="M40 70L100 100L160 70L100 40L40 70Z" fill="currentColor" opacity="0.1"/>
            <path d="M40 70V90L100 120V100L40 70Z" fill="currentColor" opacity="0.25"/>
            <path d="M160 70V90L100 120V100L160 70Z" fill="currentColor" opacity="0.4"/>
            <path d="M 80 78 L 70 62 L 90 70 L 100 50 L 110 70 L 130 62 L 120 78 Z" fill="currentColor" opacity="0.85" stroke-linejoin="round"/>
          </g>
        </svg>
        <span class="logo-text">QueueMaster</span>
      </div>
    </div>

    <!-- Menu de Navegação -->
    <q-list class="nav-list">
      
      <q-item
        v-for="item in menuItems"
        :key="item.path"
        :to="item.path"
        clickable
        :class="{ 'nav-item-active': isActive(item.path) }"
        class="nav-item"
      >
        <q-item-section avatar>
          <div class="nav-icon-wrapper" :class="{ 'active': isActive(item.path) }">
            <q-icon :name="item.icon" size="20px" />
          </div>
        </q-item-section>
        <q-item-section>
          <q-item-label class="nav-label">{{ item.label }}</q-item-label>
        </q-item-section>
      </q-item>

    </q-list>

    <!-- Spacer -->
    <div class="sidebar-spacer"></div>

    <!-- Perfil do Usuário -->
    <div class="sidebar-footer">
      
      <!-- Card do Usuário -->
      <div class="user-card">
        <div class="user-avatar">
          {{ userInitials }}
        </div>
        <div class="user-info">
          <span class="user-name">{{ userName }}</span>
          <span class="user-role">{{ userRole }}</span>
        </div>
        <q-btn
          flat
          round
          dense
          icon="logout"
          class="logout-btn"
          @click="$emit('logout')"
          title="Sair"
        />
      </div>

      <!-- Toggle Tema -->
      <button class="theme-toggle-btn" @click="$emit('toggle-theme')">
        <q-icon :name="isDark ? 'light_mode' : 'dark_mode'" size="18px" />
        <span>{{ isDark ? 'Modo Claro' : 'Modo Escuro' }}</span>
      </button>

    </div>

  </div>
</template>

<script>
import { defineComponent } from 'vue'
import { useRoute } from 'vue-router'

export default defineComponent({
  name: 'AppSidebar',

  props: {
    userName: {
      type: String,
      default: 'Usuário'
    },
    userRole: {
      type: String,
      default: 'Usuário'
    },
    userInitials: {
      type: String,
      default: 'U'
    },
    isDark: {
      type: Boolean,
      default: false
    }
  },

  emits: ['logout', 'toggle-theme'],

  setup() {
    const route = useRoute()

    const menuItems = [
      { path: '/app', label: 'Dashboard', icon: 'dashboard' },
      { path: '/app/queues', label: 'Filas', icon: 'format_list_numbered' },
      { path: '/app/appointments', label: 'Agendamentos', icon: 'event' },
      { path: '/app/establishments', label: 'Estabelecimentos', icon: 'store' },
      { path: '/app/settings', label: 'Configurações', icon: 'settings' }
    ]

    const isActive = (path) => {
      if (path === '/app') {
        return route.path === '/app' || route.path === '/app/'
      }
      return route.path.startsWith(path)
    }

    return {
      menuItems,
      isActive
    }
  }
})
</script>

<style lang="scss" scoped>
.sidebar-content {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 1rem;
  background: transparent;
}

// ===== HEADER/LOGO =====
.sidebar-header {
  padding: 0 0.5rem;
  margin-bottom: 2rem;
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.logo-img {
  width: 36px;
  height: 36px;
  color: var(--qm-brand);
  transition: color var(--qm-transition-duration, 0.3s) ease;
}

.logo-text {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--qm-text-primary);
}

// ===== NAVEGAÇÃO =====
.nav-list {
  padding: 0;
}

.nav-item {
  border-radius: 0.75rem;
  margin-bottom: 0.25rem;
  padding: 0.75rem 1rem;
  min-height: auto;
  transition: all 0.2s ease;

  &:hover {
    background: var(--qm-surface);
    box-shadow: var(--qm-shadow-sm);
  }

  &.nav-item-active {
    background: var(--qm-surface);
    box-shadow: var(--qm-shadow);
  }
}

.nav-icon-wrapper {
  width: 32px;
  height: 32px;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-secondary);
  transition: all 0.2s ease;

  &.active {
    background: var(--qm-brand);
    color: var(--qm-brand-contrast);
  }
}

.nav-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--qm-text-secondary);
}

.nav-item-active .nav-label {
  color: var(--qm-text-primary);
  font-weight: 600;
}

// ===== FOOTER =====
.sidebar-spacer {
  flex: 1;
}

.sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

// ===== USER CARD =====
.user-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--qm-surface);
  border-radius: 1rem;
  box-shadow: var(--qm-shadow);
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 0.75rem;
  background: var(--qm-brand);
  color: var(--qm-brand-contrast);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  font-weight: 600;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.logout-btn {
  color: var(--qm-text-muted);
  flex-shrink: 0;
  
  &:hover {
    color: #ef4444;
  }
}

// ===== TOGGLE TEMA =====
.theme-toggle-btn {
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  border: none;
  background: var(--qm-surface);
  color: var(--qm-text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.2s ease;
  box-shadow: var(--qm-shadow-sm);

  &:hover {
    box-shadow: var(--qm-shadow);
  }
}
</style>
