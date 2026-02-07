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

      <q-separator style="margin-top: 10px;"/>

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
              <div class="detail-card">
                <q-icon name="phone" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Telefone</span>
                  <span class="detail-value">{{ user?.phone || 'Não informado' }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="verified" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">E-mail verificado</span>
                  <span class="detail-value">
                    <q-badge :color="user?.email_verified ? 'positive' : 'grey-6'" class="verification-badge">
                      {{ user?.email_verified ? 'Verificado' : 'Não verificado' }}
                    </q-badge>
                  </span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="login" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Último login</span>
                  <span class="detail-value">{{ formatDate(user?.last_login_at) }}</span>
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

            <div class="setting-row brand-color-row">
              <div class="setting-icon">
                <q-icon name="palette" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Cor da marca</span>
                <span class="setting-description">Cor principal aplicada em botões, ícones e destaques</span>
              </div>
            </div>
            <div class="brand-presets">
              <button
                v-for="preset in brandPresets"
                :key="preset.color"
                class="color-swatch"
                :class="{ active: brandColor === preset.color }"
                :style="{ background: preset.color }"
                @click="setBrandColor(preset.color)"
                :title="preset.label"
              >
                <q-icon v-if="brandColor === preset.color" name="check" size="16px" />
              </button>
            </div>

            <div class="custom-hex-row">
              <q-input
                v-model="customHex"
                outlined
                dense
                label="Cor personalizada (HEX)"
                maxlength="7"
                class="hex-input"
                :error="hexError"
                :error-message="hexErrorMsg"
                hint="Ex: #3b82f6"
                @keyup.enter="applyCustomHex"
              >
                <template v-slot:prepend>
                  <div class="hex-preview" :style="{ background: hexPreviewColor }"></div>
                </template>
                <template v-slot:append>
                  <q-btn flat dense no-caps label="Aplicar" color="primary" @click="applyCustomHex" :disable="!customHex" />
                </template>
              </q-input>
              <q-btn flat dense no-caps label="Restaurar padrão" icon="restart_alt" class="reset-brand-btn" @click="resetBrand" />
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
            <span>Tokens não ficam expostos no browser. Use o botão abaixo para gerar um token temporário para o Swagger.</span>
          </div>

          <div class="token-section">
            <div class="token-header">
              <h4>Token para Swagger</h4>
              <p>Gera um token JWT de curta duração (5 min) para uso no Swagger UI</p>
            </div>

            <div v-if="!devToken" class="generate-token-area">
              <q-btn
                color="primary"
                icon="vpn_key"
                label="Gerar Token para Swagger"
                no-caps
                :loading="generatingToken"
                @click="generateDevToken"
              />
            </div>

            <div v-else class="token-field">
              <q-input
                v-model="devToken"
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
                    :color="copiedAccess ? 'positive' : 'primary'"
                  >
                    <q-tooltip>{{ copiedAccess ? 'Copiado!' : 'Copiar' }}</q-tooltip>
                  </q-btn>
                </template>
              </q-input>
              <p class="token-expiry">
                <q-icon name="schedule" size="14px" />
                Expira em 5 minutos. Gere outro se necessário.
              </p>
              <q-btn
                flat
                color="primary"
                icon="refresh"
                label="Gerar novo"
                no-caps
                size="sm"
                class="q-mt-sm"
                :loading="generatingToken"
                @click="generateDevToken"
              />
            </div>
          </div>

          <q-separator class="q-my-md" />

          <q-btn
            outline
            color="primary"
            icon="open_in_new"
            label="Abrir Swagger UI"
            @click="openSwagger"
            no-caps
          />
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <!-- User Edit Dialog -->
    <q-dialog v-model="showUserDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Editar Usuário</h3>
          <q-btn flat round dense icon="close" @click="closeUserDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-input
            v-model="userForm.name"
            label="Nome"
            outlined
            dense
          />
          <q-input
            v-model="userForm.email"
            label="E-mail"
            outlined
            dense
            type="email"
            class="q-mt-md"
            disable
            hint="E-mail vinculado ao Google"
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
            label="Salvar" 
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
import { BRAND_PRESETS, loadBrandColor, saveBrandColor, resetBrandColor, isValidHex, normalizeHex } from 'src/utils/brand'

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
    const devToken = ref('')
    const copiedAccess = ref(false)
    const generatingToken = ref(false)
    const loadingUser = ref(true)

    // Settings states
    const isDark = ref(false)
    const brandColor = ref('')
    const brandPresets = BRAND_PRESETS
    const customHex = ref('')
    const hexError = ref(false)
    const hexErrorMsg = ref('')
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
          users.value = response.data.data?.users || []
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
      // Load brand color
      brandColor.value = loadBrandColor()
    }

    const setBrandColor = (color) => {
      brandColor.value = color
      saveBrandColor(color)
      customHex.value = ''
      hexError.value = false
    }

    const hexPreviewColor = computed(() => {
      if (!customHex.value) return 'var(--qm-bg-tertiary)'
      const hex = customHex.value.startsWith('#') ? customHex.value : '#' + customHex.value
      return isValidHex(hex) ? hex : 'var(--qm-bg-tertiary)'
    })

    const applyCustomHex = () => {
      let hex = customHex.value.trim()
      if (!hex.startsWith('#')) hex = '#' + hex
      if (!isValidHex(hex)) {
        hexError.value = true
        hexErrorMsg.value = 'HEX inválido. Use formato #RGB ou #RRGGBB'
        return
      }
      hex = normalizeHex(hex)
      hexError.value = false
      hexErrorMsg.value = ''
      brandColor.value = hex
      saveBrandColor(hex)
      customHex.value = hex
    }

    const resetBrand = () => {
      brandColor.value = resetBrandColor()
      customHex.value = ''
      hexError.value = false
    }

    const generateDevToken = async () => {
      generatingToken.value = true
      try {
        const response = await api.get('/auth/dev-token')
        if (response.data?.success) {
          devToken.value = response.data.data.token
        }
      } catch (err) {
        console.error('Erro ao gerar dev token:', err)
        $q.notify({ type: 'negative', message: 'Erro ao gerar token. Verifique se você é admin.' })
      } finally {
        generatingToken.value = false
      }
    }

    const toggleTheme = (value) => {
      localStorage.setItem('theme', value ? 'dark' : 'light')
      document.documentElement.setAttribute('data-theme', value ? 'dark' : 'light')
      // Recarregar cor da marca adequada ao tema
      brandColor.value = loadBrandColor()
    }

    const copyToken = async () => {
      try {
        await copyToClipboard(devToken.value)
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
    const editUser = (u) => {
      isEditingUser.value = true
      selectedUser.value = u
      userForm.value = {
        name: u.name,
        email: u.email,
        role: u.role
      }
      showUserDialog.value = true
    }

    const closeUserDialog = () => {
      showUserDialog.value = false
    }

    const saveUser = async () => {
      if (!userForm.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }

      savingUser.value = true
      try {
        const payload = {
          name: userForm.value.name,
          role: userForm.value.role
        }

        await api.put(`/users/${selectedUser.value.id}`, payload)
        $q.notify({ type: 'positive', message: 'Usuário atualizado com sucesso' })
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

      localStorage.removeItem('user')
      router.push('/login')
    }

    return {
      activeTab,
      user,
      loadingUser,
      devToken,
      copiedAccess,
      generatingToken,
      isDark,
      brandColor,
      brandPresets,
      customHex,
      hexError,
      hexErrorMsg,
      hexPreviewColor,
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
      setBrandColor,
      applyCustomHex,
      resetBrand,
      copyToken,
      generateDevToken,
      openSwagger,
      formatDate,
      getRoleLabel,
      getRoleColor,
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
  margin-top: 10px;
  padding: 0 1rem;

  :deep(.q-tab) {
    padding: 0 1rem;
    
    .q-focus-helper {
      border-radius: 15px;
    }
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
  background: var(--qm-brand-light);
  color: var(--qm-brand);
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

// Brand Color Picker
.brand-color-row {
  border-bottom: none !important;
  padding-bottom: 0.5rem !important;
}

.brand-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  padding: 0 0 1rem 0;
}

.custom-hex-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--qm-border);
  flex-wrap: wrap;
}

.hex-input {
  flex: 1;
  min-width: 220px;
}

.hex-preview {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid var(--qm-border);
}

.reset-brand-btn {
  color: var(--qm-text-muted);
  font-size: 0.8125rem;
  margin-top: 2px;
}

.color-swatch {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 3px solid transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  transition: all 0.2s ease;
  box-shadow: var(--qm-shadow-sm);

  &:hover {
    transform: scale(1.15);
    box-shadow: var(--qm-shadow);
  }

  &.active {
    border-color: var(--qm-text-primary);
    transform: scale(1.1);
    box-shadow: var(--qm-shadow-lg);
  }
}

// Developer Section
.dev-warning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #e8a317;
  padding: 1rem;
  background: rgba(255, 152, 0, 0.1);
  border-radius: 10px;
  margin-bottom: 1.5rem;
}

.token-section {
  margin-bottom: 1rem;
}

.generate-token-area {
  padding: 1.5rem;
  background: var(--qm-bg-tertiary);
  border-radius: 10px;
  text-align: center;
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
  background: var(--qm-brand-light);
  color: var(--qm-brand);
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

