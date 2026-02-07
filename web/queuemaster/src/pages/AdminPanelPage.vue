<template>
  <q-page class="admin-panel-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Painel de Administração</h1>
        <p class="page-subtitle">Visualize e gerencie os usuários do sistema</p>
      </div>
    </div>

    <!-- Role Filter Tabs -->
    <div class="filter-tabs soft-card q-mb-lg">
      <q-tabs v-model="activeTab" dense no-caps active-color="primary" indicator-color="primary">
        <q-tab name="all" label="Todos" />
        <q-tab v-if="canSeeManagers" name="manager" label="Gerentes" />
        <q-tab v-if="canSeeProfessionals" name="professional" label="Profissionais" />
        <q-tab name="client" label="Clientes" />
      </q-tabs>
    </div>

    <!-- Users Table Card -->
    <div class="table-card soft-card">
      <div class="table-header">
        <h2 class="table-title">Usuários</h2>
        <div class="table-actions">
          <q-input
            v-model="searchQuery"
            outlined
            dense
            placeholder="Buscar por nome ou email..."
            class="search-input"
          >
            <template v-slot:prepend>
              <q-icon name="search" />
            </template>
          </q-input>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <q-spinner-dots color="primary" size="40px" />
        <p>Carregando usuários...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredUsers.length === 0" class="empty-state">
        <q-icon name="people" size="64px" />
        <h3>Nenhum usuário encontrado</h3>
        <p v-if="searchQuery">Tente ajustar sua busca</p>
        <p v-else>Nenhum usuário cadastrado nesta categoria</p>
      </div>

      <!-- Table -->
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="th-user">Usuário</th>
              <th class="th-email">Email</th>
              <th class="th-role">Papel</th>
              <th class="th-status">Status</th>
              <th class="th-created">Criado em</th>
              <th class="th-actions">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>
                <div class="user-info">
                  <div class="user-avatar-cell">
                    <img v-if="user.avatar_url" :src="user.avatar_url" class="user-avatar-img" />
                    <div v-else class="user-avatar-initials">{{ getInitials(user.name) }}</div>
                  </div>
                  <div class="user-details">
                    <span class="user-name">{{ user.name }}</span>
                    <span class="user-id">ID: {{ user.id }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="email-text">{{ user.email }}</span>
              </td>
              <td>
                <q-badge :color="getRoleColor(user.role)" :label="getRoleLabel(user.role)" />
              </td>
              <td>
                <q-badge :color="user.is_active ? 'positive' : 'negative'" :label="user.is_active ? 'Ativo' : 'Inativo'" />
              </td>
              <td>
                <span class="date-text">{{ formatDate(user.created_at) }}</span>
              </td>
              <td>
                <div class="row-actions">
                  <q-btn flat round dense icon="visibility" size="sm" @click="viewUser(user)">
                    <q-tooltip>Ver detalhes</q-tooltip>
                  </q-btn>
                  <q-btn v-if="isAdmin" flat round dense icon="edit" size="sm" @click="editUser(user)">
                    <q-tooltip>Editar</q-tooltip>
                  </q-btn>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- View User Dialog -->
    <q-dialog v-model="showViewDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Detalhes do Usuário</h3>
          <q-btn flat round dense icon="close" @click="showViewDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-content" v-if="selectedUser">
          <div class="user-profile-header">
            <img v-if="selectedUser.avatar_url" :src="selectedUser.avatar_url" class="profile-avatar" />
            <div v-else class="profile-avatar-initials">{{ getInitials(selectedUser.name) }}</div>
            <div class="profile-info">
              <h4>{{ selectedUser.name }}</h4>
              <q-badge :color="getRoleColor(selectedUser.role)" :label="getRoleLabel(selectedUser.role)" />
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Email</span>
              <span class="detail-value">{{ selectedUser.email }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Telefone</span>
              <span class="detail-value">{{ selectedUser.phone || 'Não informado' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <q-badge :color="selectedUser.is_active ? 'positive' : 'negative'" :label="selectedUser.is_active ? 'Ativo' : 'Inativo'" />
            </div>
            <div class="detail-item">
              <span class="detail-label">Email Verificado</span>
              <span class="detail-value">{{ selectedUser.email_verified ? 'Sim' : 'Não' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Último Login</span>
              <span class="detail-value">{{ formatDate(selectedUser.last_login_at) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Criado em</span>
              <span class="detail-value">{{ formatDate(selectedUser.created_at) }}</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Fechar" no-caps @click="showViewDialog = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Edit User Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Editar Usuário</h3>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-input
            v-model="editForm.name"
            label="Nome"
            outlined
            dense
          />
          <q-input
            v-model="editForm.email"
            label="Email"
            outlined
            dense
            class="q-mt-md"
          />
          <q-input
            v-model="editForm.phone"
            label="Telefone"
            outlined
            dense
            class="q-mt-md"
          />
          <q-select
            v-model="editForm.role"
            label="Papel"
            outlined
            dense
            :options="roleOptions"
            emit-value
            map-options
            class="q-mt-md"
          />
          <q-toggle
            v-model="editForm.is_active"
            label="Ativo"
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted, watch } from 'vue'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'AdminPanelPage',

  setup() {
    const $q = useQuasar()

    // State
    const users = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const searchQuery = ref('')
    const activeTab = ref('all')
    const currentUserRole = ref(null)

    // Dialogs
    const showViewDialog = ref(false)
    const showEditDialog = ref(false)
    const selectedUser = ref(null)

    // Edit form
    const editForm = ref({
      name: '',
      email: '',
      phone: '',
      role: 'client',
      is_active: true
    })

    const roleOptions = [
      { label: 'Cliente', value: 'client' },
      { label: 'Profissional', value: 'professional' },
      { label: 'Gerente', value: 'manager' },
      { label: 'Administrador', value: 'admin' }
    ]

    // Computed
    const isAdmin = computed(() => currentUserRole.value === 'admin')
    const canSeeManagers = computed(() => ['admin', 'manager'].includes(currentUserRole.value))
    const canSeeProfessionals = computed(() => ['admin', 'manager'].includes(currentUserRole.value))

    const filteredUsers = computed(() => {
      let filtered = users.value

      // Filter by tab
      if (activeTab.value !== 'all') {
        filtered = filtered.filter(u => u.role === activeTab.value)
      }

      // Filter by search
      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(u =>
          u.name?.toLowerCase().includes(query) ||
          u.email?.toLowerCase().includes(query)
        )
      }

      return filtered
    })

    // Methods
    const fetchUsers = async () => {
      loading.value = true
      try {
        const params = {}
        const response = await api.get('/users', { params })
        if (response.data?.success) {
          users.value = response.data.data?.users || []
        }
      } catch (err) {
        console.error('Erro ao buscar usuários:', err)
        if (err.response?.status === 403) {
          $q.notify({ type: 'warning', message: 'Você não tem permissão para visualizar usuários' })
        } else {
          $q.notify({ type: 'negative', message: 'Erro ao carregar usuários' })
        }
      } finally {
        loading.value = false
      }
    }

    const fetchCurrentUser = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          currentUserRole.value = response.data.data.user.role
        }
      } catch (err) {
        console.error('Erro ao buscar role:', err)
      }
    }

    const viewUser = (user) => {
      selectedUser.value = user
      showViewDialog.value = true
    }

    const editUser = (user) => {
      selectedUser.value = user
      editForm.value = {
        name: user.name || '',
        email: user.email || '',
        phone: user.phone || '',
        role: user.role || 'client',
        is_active: user.is_active !== false && user.is_active !== 0
      }
      showEditDialog.value = true
    }

    const saveUser = async () => {
      saving.value = true
      try {
        const payload = {}
        if (editForm.value.name) payload.name = editForm.value.name
        if (editForm.value.email) payload.email = editForm.value.email
        if (editForm.value.phone !== undefined) payload.phone = editForm.value.phone
        if (editForm.value.role) payload.role = editForm.value.role

        await api.put(`/users/${selectedUser.value.id}`, payload)
        $q.notify({ type: 'positive', message: 'Usuário atualizado com sucesso' })
        showEditDialog.value = false
        fetchUsers()
      } catch (err) {
        const msg = err.response?.data?.error?.message || 'Erro ao salvar'
        $q.notify({ type: 'negative', message: msg })
      } finally {
        saving.value = false
      }
    }

    const getRoleLabel = (role) => {
      const labels = {
        admin: 'Administrador',
        manager: 'Gerente',
        professional: 'Profissional',
        client: 'Cliente'
      }
      return labels[role] || role
    }

    const getRoleColor = (role) => {
      const colors = {
        admin: 'deep-purple',
        manager: 'blue',
        professional: 'teal',
        client: 'grey'
      }
      return colors[role] || 'grey'
    }

    const getInitials = (name) => {
      if (!name) return '?'
      return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
    }

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('pt-BR')
    }

    // Watch tab changes
    watch(activeTab, () => {
      // Tab filter is handled by computed, no need to refetch
    })

    // Lifecycle
    onMounted(async () => {
      await fetchCurrentUser()
      await fetchUsers()
    })

    return {
      users,
      loading,
      saving,
      searchQuery,
      activeTab,
      currentUserRole,
      showViewDialog,
      showEditDialog,
      selectedUser,
      editForm,
      roleOptions,
      isAdmin,
      canSeeManagers,
      canSeeProfessionals,
      filteredUsers,
      viewUser,
      editUser,
      saveUser,
      getRoleLabel,
      getRoleColor,
      getInitials,
      formatDate
    }
  }
})
</script>

<style lang="scss" scoped>
.admin-panel-page {
  padding: 0 1.5rem 1.5rem;
}

// Header
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0 0 0.25rem;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--qm-text-muted);
  margin: 0;
}

// Filter Tabs
.filter-tabs {
  padding: 0.5rem;
}

// Table Card
.table-card {
  padding: 0;
  overflow: hidden;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--qm-border);
  flex-wrap: wrap;
  gap: 1rem;
}

.table-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin: 0;
}

.search-input {
  width: 280px;

  @media (max-width: 600px) {
    width: 100%;
  }
}

// Loading & Empty States
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--qm-text-muted);
  text-align: center;

  h3 {
    margin: 1rem 0 0.5rem;
    font-size: 1.125rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.875rem;
  }
}

// Table
.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;

  th, td {
    padding: 0.875rem 1.5rem;
    text-align: left;
  }

  thead {
    tr {
      background: var(--qm-bg-secondary);
    }

    th {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--qm-text-muted);
      border-bottom: 1px solid var(--qm-border);
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid var(--qm-border);
      transition: background 0.2s ease;

      &:last-child {
        border-bottom: none;
      }

      &:hover {
        background: var(--qm-bg-secondary);
      }
    }

    td {
      font-size: 0.875rem;
      color: var(--qm-text-primary);
    }
  }
}

.th-user { min-width: 200px; }
.th-email { min-width: 200px; }
.th-role { min-width: 120px; }
.th-status { min-width: 80px; }
.th-created { min-width: 100px; }
.th-actions { width: 100px; }

// User Info Cell
.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar-cell {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
}

.user-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar-initials,
.profile-avatar-initials {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--qm-brand);
  color: var(--qm-brand-contrast);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
}

.user-details {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  font-size: 0.875rem;
}

.user-id {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.email-text {
  font-size: 0.8125rem;
  color: var(--qm-text-secondary);
}

.date-text {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

.row-actions {
  display: flex;
  gap: 0.25rem;
}

// Dialog Styles
.dialog-card {
  width: 100%;
  max-width: 500px;
  border-radius: 16px;
  background: var(--qm-bg-primary);
}

.dialog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--qm-border);

  h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.dialog-content {
  padding: 1.5rem;
}

.dialog-actions {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--qm-border);
  gap: 0.5rem;
}

// Profile Header
.user-profile-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--qm-border);

  h4 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.profile-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  object-fit: cover;
}

.profile-avatar-initials {
  width: 48px;
  height: 48px;
  font-size: 1rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;

  @media (max-width: 500px) {
    grid-template-columns: 1fr;
  }
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 0.9375rem;
  color: var(--qm-text-primary);
  font-weight: 500;
}
</style>
