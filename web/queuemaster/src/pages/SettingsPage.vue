<template>
  <q-page class="settings-page">
    <div class="page-header">
      <h1 class="page-title">Configurações</h1>
      <p class="page-subtitle">Gerencie suas preferências e configurações da conta</p>
    </div>

    <!-- Tabbed Navigation -->
    <div class="settings-tabs-container soft-card">
      <q-tabs
        v-model="activeTab"
        dense
        class="settings-tabs"
        active-color="primary"
        indicator-color="primary"
        align="left"
        narrow-indicator
      >
        <q-tab name="profile" icon="person" label="Perfil" no-caps />
        <q-tab name="appearance" icon="palette" label="Aparência" no-caps />
        <q-tab name="notifications" icon="notifications" label="Notificações" no-caps />
        <q-tab v-if="isAdmin" name="users" icon="group" label="Usuários" no-caps />
        <q-tab v-if="isAdmin" name="developer" icon="code" label="Developer" no-caps />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="activeTab" animated class="tab-panels">
        <!-- Tab: Perfil -->
        <q-tab-panel name="profile" class="tab-panel">
          <div class="panel-header">
            <h3>Informações do Perfil</h3>
            <p>Seus dados de conta</p>
          </div>

          <div class="profile-section">
            <div class="profile-card">
              <q-avatar size="100px" class="profile-avatar">
                <img v-if="user?.avatar_url" :src="user.avatar_url" alt="Avatar" />
                <q-icon v-else name="person" size="50px" />
              </q-avatar>
              <div class="profile-info">
                <h4>{{ user?.name || 'Usuário' }}</h4>
                <p class="profile-email">{{ user?.email || '-' }}</p>
                <q-badge :color="roleColor" class="role-badge">{{ roleLabel }}</q-badge>
              </div>
            </div>

            <div class="profile-details-grid">
              <div class="detail-card">
                <q-icon name="badge" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Nome completo</span>
                  <span class="detail-value">{{ user?.name || 'Não informado' }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="mail" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">E-mail</span>
                  <span class="detail-value">{{ user?.email || 'Não informado' }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="work" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Função</span>
                  <span class="detail-value">{{ roleLabel }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="calendar_today" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Membro desde</span>
                  <span class="detail-value">{{ formatDate(user?.created_at) }}</span>
                </div>
              </div>
            </div>

            <div class="logout-section">
              <q-btn
                outline
                color="negative"
                icon="logout"
                label="Sair da conta"
                no-caps
                @click="handleLogout"
              />
            </div>
          </div>
        </q-tab-panel>

        <!-- Tab: Aparência -->
        <q-tab-panel name="appearance" class="tab-panel">
          <div class="panel-header">
            <h3>Aparência</h3>
            <p>Personalize a interface do sistema</p>
          </div>

          <div class="settings-list">
            <div class="setting-row">
              <div class="setting-icon">
                <q-icon :name="isDark ? 'dark_mode' : 'light_mode'" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Tema escuro</span>
                <span class="setting-description">Alterna entre modo claro e escuro</span>
              </div>
              <q-toggle v-model="isDark" @update:model-value="toggleTheme" color="primary" />
            </div>
          </div>
        </q-tab-panel>

        <!-- Tab: Notificações -->
        <q-tab-panel name="notifications" class="tab-panel">
          <div class="panel-header">
            <h3>Notificações</h3>
            <p>Configure como deseja receber alertas</p>
          </div>

          <div class="settings-list">
            <div class="setting-row">
              <div class="setting-icon">
                <q-icon name="mark_email_unread" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Notificações por e-mail</span>
                <span class="setting-description">Receber atualizações por e-mail</span>
              </div>
              <q-toggle v-model="emailNotifications" color="primary" />
            </div>
            <div class="setting-row">
              <div class="setting-icon">
                <q-icon name="campaign" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Notificações push</span>
                <span class="setting-description">Receber alertas no navegador</span>
              </div>
              <q-toggle v-model="pushNotifications" color="primary" />
            </div>
            <div class="setting-row">
              <div class="setting-icon">
                <q-icon name="sms" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Notificações por SMS</span>
                <span class="setting-description">Receber alertas importantes por SMS</span>
              </div>
              <q-toggle v-model="smsNotifications" color="primary" />
            </div>
          </div>
        </q-tab-panel>

        <!-- Tab: Usuários (Admin) -->
        <q-tab-panel v-if="isAdmin" name="users" class="tab-panel">
          <div class="panel-header">
            <div class="panel-header-left">
              <h3>Gerenciamento de Usuários</h3>
              <p>Administre os usuários do sistema</p>
            </div>
            <q-btn
              color="primary"
              icon="person_add"
              label="Novo Usuário"
              no-caps
              @click="openUserDialog"
            />
          </div>

          <!-- Users Table -->
          <div class="users-table-container">
            <div v-if="loadingUsers" class="loading-state">
              <q-spinner-dots color="primary" size="40px" />
              <p>Carregando usuários...</p>
            </div>

            <div v-else-if="users.length === 0" class="empty-state">
              <q-icon name="group" size="48px" />
              <p>Nenhum usuário encontrado</p>
            </div>

            <table v-else class="users-table">
              <thead>
                <tr>
                  <th>Usuário</th>
                  <th>Função</th>
                  <th>Criado em</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="u in users" :key="u.id">
                  <td>
                    <div class="user-cell">
                      <q-avatar size="32px" class="user-avatar">
                        <img v-if="u.avatar_url" :src="u.avatar_url" />
                        <q-icon v-else name="person" size="16px" />
                      </q-avatar>
                      <div class="user-info">
                        <span class="user-name">{{ u.name }}</span>
                        <span class="user-email">{{ u.email }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <q-badge :color="getRoleColor(u.role)">{{ getRoleLabel(u.role) }}</q-badge>
                  </td>
                  <td class="date-cell">{{ formatDate(u.created_at) }}</td>
                  <td>
                    <div class="row-actions">
                      <q-btn flat round dense icon="edit" size="sm" @click="editUser(u)">
                        <q-tooltip>Editar</q-tooltip>
                      </q-btn>
                      <q-btn 
                        v-if="u.id !== user?.id" 
                        flat round dense icon="delete" size="sm" color="negative" 
                        @click="confirmDeleteUser(u)"
                      >
                        <q-tooltip>Excluir</q-tooltip>
                      </q-btn>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </q-tab-panel>

        <!-- Tab: Developer (Admin) -->
        <q-tab-panel v-if="isAdmin" name="developer" class="tab-panel">
          <div class="panel-header">
            <h3>Developer Tools</h3>
            <p>Ferramentas de desenvolvimento e depuração</p>
          </div>

          <div class="dev-warning">
            <q-icon name="warning" size="20px" />
            <span>Essas informações são sensíveis. Não compartilhe com terceiros.</span>
          </div>

          <div class="token-section">
            <div class="token-header">
              <h4>Access Token (JWT)</h4>
              <p>Use no Swagger UI: Authorize → Bearer token</p>
            </div>
            <div class="token-field">
              <q-input
                v-model="accessToken"
                readonly
                outlined
                dense
                type="textarea"
                :rows="3"
                class="token-input"
              >
                <template v-slot:append>
                  <q-btn
                    flat
                    round
                    dense
                    icon="content_copy"
                    @click="copyToken"
                    :color="copiedAccess ? 'positive' : 'grey'"
                  >
                    <q-tooltip>{{ copiedAccess ? 'Copiado!' : 'Copiar' }}</q-tooltip>
                  </q-btn>
                </template>
              </q-input>
            </div>
            <p class="token-expiry">
              <q-icon name="schedule" size="14px" />
              Expira em ~15 minutos. Faça refresh se necessário.
            </p>
          </div>

          <q-btn
            outline
            color="primary"
            icon="open_in_new"
            label="Abrir Swagger UI"
            @click="openSwagger"
            no-caps
            class="q-mt-md"
          />
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <!-- User Create/Edit Dialog -->
    <q-dialog v-model="showUserDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>{{ isEditingUser ? 'Editar Usuário' : 'Novo Usuário' }}</h3>
          <q-btn flat round dense icon="close" @click="closeUserDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-input
            v-model="userForm.name"
            label="Nome *"
            outlined
            dense
          />
          <q-input
            v-model="userForm.email"
            label="E-mail *"
            outlined
            dense
            type="email"
            class="q-mt-md"
          />
          <q-input
            v-if="!isEditingUser"
            v-model="userForm.password"
            label="Senha *"
            outlined
            dense
            type="password"
            class="q-mt-md"
          />
          <q-select
            v-model="userForm.role"
            label="Função *"
            outlined
            dense
            :options="roleOptions"
            emit-value
            map-options
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeUserDialog" />
          <q-btn 
            color="primary" 
            :label="isEditingUser ? 'Salvar' : 'Criar'" 
            no-caps 
            :loading="savingUser"
            @click="saveUser" 
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete User Confirmation Dialog -->
    <q-dialog v-model="showDeleteUserDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Confirmar Exclusão</h3>
        </q-card-section>

        <q-card-section class="dialog-content">
          <p>Tem certeza que deseja excluir o usuário <strong>{{ selectedUser?.name }}</strong>?</p>
          <p class="delete-warning">Esta ação não pode ser desfeita.</p>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteUserDialog = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deletingUser" @click="deleteUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { copyToClipboard, useQuasar } from 'quasar'

export default defineComponent({
  name: 'SettingsPage',

  setup() {
    const router = useRouter()
    const $q = useQuasar()

    // Tab state
    const activeTab = ref('profile')

    // User data
    const user = ref(null)
    const verifiedRole = ref(null)
    const accessToken = ref('')
    const copiedAccess = ref(false)
    const loadingUser = ref(true)

    // Settings states
    const isDark = ref(false)
    const emailNotifications = ref(true)
    const pushNotifications = ref(false)
    const smsNotifications = ref(false)

    // Users management
    const users = ref([])
    const loadingUsers = ref(false)
    const savingUser = ref(false)
    const deletingUser = ref(false)
    const showUserDialog = ref(false)
    const showDeleteUserDialog = ref(false)
    const isEditingUser = ref(false)
    const selectedUser = ref(null)
    const userForm = ref({
      name: '',
      email: '',
      password: '',
      role: 'client'
    })

    const roleOptions = [
      { label: 'Cliente', value: 'client' },
      { label: 'Atendente', value: 'attendant' },
      { label: 'Gerente', value: 'manager' },
      { label: 'Administrador', value: 'admin' }
    ]

    // Computed
    const isAdmin = computed(() => verifiedRole.value === 'admin')

    const roleLabel = computed(() => {
      return getRoleLabel(verifiedRole.value)
    })

    const roleColor = computed(() => {
      return getRoleColor(verifiedRole.value)
    })

    // Lifecycle
    onMounted(() => {
      fetchUserFromBackend()
      loadTheme()
      loadTokens()
    })

    // Methods
    const fetchUserFromBackend = async () => {
      loadingUser.value = true
      
      try {
        const response = await api.get('/auth/me')
        
        if (response.data?.success && response.data?.data?.user) {
          user.value = response.data.data.user
          verifiedRole.value = response.data.data.user.role
          localStorage.setItem('user', JSON.stringify(response.data.data.user))
          
          // Se admin, carrega lista de usuários
          if (verifiedRole.value === 'admin') {
            fetchUsers()
          }
        }
      } catch (err) {
        console.error('Erro ao buscar usuário:', err)
        verifiedRole.value = null
      } finally {
        loadingUser.value = false
      }
    }

    const fetchUsers = async () => {
      loadingUsers.value = true
      try {
        const response = await api.get('/users')
        if (response.data?.success) {
          users.value = response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar usuários:', err)
      } finally {
        loadingUsers.value = false
      }
    }

    const loadTheme = () => {
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }
    }

    const loadTokens = () => {
      accessToken.value = localStorage.getItem('access_token') || ''
    }

    const toggleTheme = (value) => {
      localStorage.setItem('theme', value ? 'dark' : 'light')
      document.documentElement.setAttribute('data-theme', value ? 'dark' : 'light')
    }

    const copyToken = async () => {
      try {
        await copyToClipboard(accessToken.value)
        copiedAccess.value = true
        setTimeout(() => { copiedAccess.value = false }, 2000)
      } catch (err) {
        console.error('Erro ao copiar:', err)
      }
    }

    const openSwagger = () => {
      window.open('http://localhost/swagger', '_blank')
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Não informado'
      const date = new Date(dateString)
      return date.toLocaleDateString('pt-BR', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric' 
      })
    }

    const getRoleLabel = (role) => {
      const roles = {
        admin: 'Administrador',
        manager: 'Gerente',
        attendant: 'Atendente',
        user: 'Usuário',
        client: 'Cliente'
      }
      return roles[role] || 'Usuário'
    }

    const getRoleColor = (role) => {
      const colors = {
        admin: 'negative',
        manager: 'warning',
        attendant: 'info',
        user: 'grey',
        client: 'grey'
      }
      return colors[role] || 'grey'
    }

    // User CRUD
    const openUserDialog = () => {
      isEditingUser.value = false
      userForm.value = { name: '', email: '', password: '', role: 'client' }
      showUserDialog.value = true
    }

    const editUser = (u) => {
      isEditingUser.value = true
      selectedUser.value = u
      userForm.value = {
        name: u.name,
        email: u.email,
        password: '',
        role: u.role
      }
      showUserDialog.value = true
    }

    const closeUserDialog = () => {
      showUserDialog.value = false
    }

    const saveUser = async () => {
      if (!userForm.value.name || !userForm.value.email) {
        $q.notify({ type: 'warning', message: 'Nome e e-mail são obrigatórios' })
        return
      }
      if (!isEditingUser.value && !userForm.value.password) {
        $q.notify({ type: 'warning', message: 'Senha é obrigatória para novo usuário' })
        return
      }

      savingUser.value = true
      try {
        const payload = {
          name: userForm.value.name,
          email: userForm.value.email,
          role: userForm.value.role
        }
        if (userForm.value.password) {
          payload.password = userForm.value.password
        }

        if (isEditingUser.value) {
          await api.put(`/users/${selectedUser.value.id}`, payload)
          $q.notify({ type: 'positive', message: 'Usuário atualizado com sucesso' })
        } else {
          await api.post('/users', payload)
          $q.notify({ type: 'positive', message: 'Usuário criado com sucesso' })
        }
        closeUserDialog()
        fetchUsers()
      } catch (err) {
        console.error('Erro ao salvar usuário:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar usuário' })
      } finally {
        savingUser.value = false
      }
    }

    const confirmDeleteUser = (u) => {
      selectedUser.value = u
      showDeleteUserDialog.value = true
    }

    const deleteUser = async () => {
      deletingUser.value = true
      try {
        await api.delete(`/users/${selectedUser.value.id}`)
        $q.notify({ type: 'positive', message: 'Usuário excluído com sucesso' })
        showDeleteUserDialog.value = false
        fetchUsers()
      } catch (err) {
        console.error('Erro ao excluir usuário:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir usuário' })
      } finally {
        deletingUser.value = false
      }
    }

    const handleLogout = async () => {
      try {
        await api.post('/auth/logout')
      } catch {
        // Ignora erro
      }

      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
      localStorage.removeItem('user')
      router.push('/login')
    }

    return {
      activeTab,
      user,
      loadingUser,
      accessToken,
      copiedAccess,
      isDark,
      emailNotifications,
      pushNotifications,
      smsNotifications,
      users,
      loadingUsers,
      savingUser,
      deletingUser,
      showUserDialog,
      showDeleteUserDialog,
      isEditingUser,
      selectedUser,
      userForm,
      roleOptions,
      isAdmin,
      roleLabel,
      roleColor,
      toggleTheme,
      copyToken,
      openSwagger,
      formatDate,
      getRoleLabel,
      getRoleColor,
      openUserDialog,
      editUser,
      closeUserDialog,
      saveUser,
      confirmDeleteUser,
      deleteUser,
      handleLogout
    }
  }
})
</script>

<style lang="scss" scoped>
.settings-page {
  padding: 0 1.5rem 1.5rem;
}

.page-header {
  margin-bottom: 1.5rem;
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

// Tabs Container
.settings-tabs-container {
  padding: 0;
  overflow: hidden;
}

.settings-tabs {
  padding: 0 1rem;

  :deep(.q-tab) {
    padding: 0 1rem;
  }

  :deep(.q-tab__label) {
    font-weight: 500;
  }
}

.tab-panels {
  background: transparent;
}

.tab-panel {
  padding: 1.5rem;
}

// Panel Header
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;

  h3 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--qm-text-muted);
  }
}

.panel-header-left {
  flex: 1;
}

// Profile Section
.profile-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.profile-card {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  background: var(--qm-bg-secondary);
  border-radius: 12px;

  @media (max-width: 500px) {
    flex-direction: column;
    text-align: center;
  }
}

.profile-avatar {
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-muted);
  flex-shrink: 0;
}

.profile-info {
  h4 {
    margin: 0 0 0.25rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.profile-email {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  color: var(--qm-text-muted);
}

.role-badge {
  font-size: 0.75rem;
}

.profile-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.detail-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--qm-bg-secondary);
  border-radius: 10px;
  color: var(--qm-text-muted);
}

.detail-content {
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

.logout-section {
  padding-top: 1rem;
  border-top: 1px solid var(--qm-border);
}

// Settings List
.settings-list {
  display: flex;
  flex-direction: column;
}

.setting-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 0;
  border-bottom: 1px solid var(--qm-border);

  &:last-child {
    border-bottom: none;
  }
}

.setting-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: var(--qm-primary-alpha);
  color: var(--qm-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.setting-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.setting-title {
  font-weight: 500;
  color: var(--qm-text-primary);
  font-size: 0.9375rem;
}

.setting-description {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

// Developer Section
.dev-warning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #ff9800;
  padding: 1rem;
  background: rgba(255, 152, 0, 0.1);
  border-radius: 10px;
  margin-bottom: 1.5rem;
}

.token-section {
  margin-bottom: 1rem;
}

.token-header {
  margin-bottom: 0.75rem;

  h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--qm-text-muted);
  }
}

.token-input {
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.75rem;
}

.token-expiry {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  margin: 0.5rem 0 0;
}

// Users Table
.users-table-container {
  border: 1px solid var(--qm-border);
  border-radius: 12px;
  overflow: hidden;
}

.users-table {
  width: 100%;
  border-collapse: collapse;

  th, td {
    padding: 0.875rem 1rem;
    text-align: left;
  }

  thead {
    background: var(--qm-bg-secondary);

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

.user-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  background: var(--qm-primary-alpha);
  color: var(--qm-primary);
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  font-size: 0.875rem;
}

.user-email {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.date-cell {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

.row-actions {
  display: flex;
  gap: 0.25rem;
}

// Loading & Empty States
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 2rem;
  color: var(--qm-text-muted);
  text-align: center;

  p {
    margin: 1rem 0 0;
    font-size: 0.875rem;
  }
}

// Dialog Styles
.dialog-card {
  width: 100%;
  max-width: 450px;
  border-radius: 16px;
  background: var(--qm-bg-primary);

  :deep(.q-btn) {
    min-height: 36px;
  }

  :deep(.q-btn__content) {
    color: inherit;
  }
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

.delete-warning {
  color: var(--qm-error);
  font-size: 0.8125rem;
  margin-top: 0.5rem;
}
</style>

