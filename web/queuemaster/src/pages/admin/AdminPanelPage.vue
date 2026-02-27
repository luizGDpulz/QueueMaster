<template>
  <q-page class="admin-panel-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Painel de Administração</h1>
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Gerencie usuários, logs e ferramentas do sistema</p>
      </div>
    </div>

    <!-- Tabbed Navigation -->
    <div class="admin-tabs-container soft-card">
      <q-tabs
        v-model="activeTab"
        dense
        class="admin-tabs"
        active-color="primary"
        indicator-color="primary"
        align="left"
        narrow-indicator
      >
        <q-tab name="users" icon="group" label="Usuários" no-caps />
        <q-tab name="logs" icon="history" label="Logs" no-caps />
        <q-tab v-if="isAdmin" name="developer" icon="code" label="Developer" no-caps />
      </q-tabs>

      <q-separator style="margin-top: 10px;" />

      <q-tab-panels v-model="activeTab" animated class="tab-panels">
        <!-- ================================================================ -->
        <!-- Tab: Usuários -->
        <!-- ================================================================ -->
        <q-tab-panel name="users" class="tab-panel">
          <div class="panel-header">
            <div class="panel-header-left">
              <h3>Gerenciamento de Usuários</h3>
              <p>{{ isAdmin ? 'Administre todos os usuários do sistema' : 'Visualize os usuários vinculados ao seu negócio' }}</p>
            </div>
          </div>

          <!-- Role Filter Tabs -->
          <div class="filter-tabs q-mb-md">
            <q-tabs v-model="roleFilter" dense no-caps active-color="primary" indicator-color="primary" inline-label>
              <q-tab name="all" label="Todos" />
              <q-tab v-if="canSeeManagers" name="manager" label="Gerentes" />
              <q-tab v-if="canSeeProfessionals" name="professional" label="Profissionais" />
              <q-tab name="client" label="Clientes" />
            </q-tabs>
          </div>

          <!-- Search -->
          <div class="search-bar q-mb-md">
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

          <!-- Users Table -->
          <div v-else class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th class="th-user">Usuário</th>
                  <th class="th-email">Email</th>
                  <th class="th-role">Papel</th>
                  <th class="th-status">Status</th>
                  <th class="th-created">Criado em</th>
                  <th v-if="isAdmin" class="th-actions">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in filteredUsers" :key="user.id" class="clickable-row" @click="router.push(`/app/admin/users/${user.id}`)">
                  <td>
                    <div class="user-info">
                      <div class="user-avatar-cell">
                        <img v-if="user.avatar_url" :src="user.avatar_url" class="user-avatar-img" referrerpolicy="no-referrer" />
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
                  <td v-if="isAdmin">
                    <div class="row-actions">
                      <q-btn flat round dense icon="edit" size="sm" @click.stop="editUser(user)">
                        <q-tooltip>Editar</q-tooltip>
                      </q-btn>
                      <q-btn
                        v-if="user.id !== currentUserId"
                        flat round dense icon="delete" size="sm" color="negative"
                        @click.stop="confirmDeleteUser(user)"
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

        <!-- ================================================================ -->
        <!-- Tab: Logs -->
        <!-- ================================================================ -->
        <q-tab-panel name="logs" class="tab-panel">
          <div class="panel-header">
            <div class="panel-header-left">
              <h3>Logs do Sistema</h3>
              <p>{{ isAdmin ? 'Visualize todas as ações registradas no sistema' : 'Visualize as ações dos seus negócios' }}</p>
            </div>
            <div class="panel-header-right">
              <q-btn
                outline
                color="primary"
                icon="refresh"
                label="Atualizar"
                no-caps
                size="sm"
                :loading="logsLoading"
                @click="fetchLogs"
              />
            </div>
          </div>

          <!-- Filters Row -->
          <div class="logs-filters">
            <div class="filters-row">
              <!-- Search -->
              <q-input
                v-model="logsSearch"
                outlined
                dense
                placeholder="Buscar nos logs..."
                class="filter-input filter-search"
                @keyup.enter="fetchLogs"
                clearable
              >
                <template v-slot:prepend>
                  <q-icon name="search" />
                </template>
              </q-input>

              <!-- Action Filter -->
              <q-select
                v-model="logsActionFilter"
                outlined
                dense
                :options="actionOptions"
                label="Ação"
                class="filter-input"
                clearable
                emit-value
                map-options
              />

              <!-- Entity Filter -->
              <q-select
                v-model="logsEntityFilter"
                outlined
                dense
                :options="entityOptions"
                label="Entidade"
                class="filter-input"
                clearable
                emit-value
                map-options
              />

              <!-- Business Filter (admin only shows all, manager sees own) -->
              <q-select
                v-if="businessOptions.length > 0"
                v-model="logsBusinessFilter"
                outlined
                dense
                :options="businessOptions"
                label="Negócio"
                class="filter-input"
                clearable
                emit-value
                map-options
              />

              <!-- Date From -->
              <q-input
                v-model="logsDateFrom"
                outlined
                dense
                type="date"
                label="De"
                class="filter-input filter-date"
                clearable
              />

              <!-- Date To -->
              <q-input
                v-model="logsDateTo"
                outlined
                dense
                type="date"
                label="Até"
                class="filter-input filter-date"
                clearable
              />

              <!-- Apply -->
              <q-btn
                color="primary"
                icon="filter_list"
                label="Filtrar"
                no-caps
                dense
                class="filter-btn"
                @click="fetchLogs"
              />

              <!-- Clear -->
              <q-btn
                v-if="hasActiveFilters"
                flat
                color="grey"
                icon="clear_all"
                label="Limpar"
                no-caps
                dense
                class="filter-btn"
                @click="clearFilters"
              />
            </div>

            <!-- Active Filters Chips -->
            <div v-if="hasActiveFilters" class="active-filters">
              <q-chip
                v-if="logsSearch"
                removable
                dense
                color="primary"
                text-color="white"
                icon="search"
                @remove="logsSearch = ''; fetchLogs()"
              >
                "{{ logsSearch }}"
              </q-chip>
              <q-chip
                v-if="logsActionFilter"
                removable
                dense
                color="teal"
                text-color="white"
                icon="bolt"
                @remove="logsActionFilter = null; fetchLogs()"
              >
                {{ logsActionFilter }}
              </q-chip>
              <q-chip
                v-if="logsEntityFilter"
                removable
                dense
                color="blue"
                text-color="white"
                icon="category"
                @remove="logsEntityFilter = null; fetchLogs()"
              >
                {{ logsEntityFilter }}
              </q-chip>
              <q-chip
                v-if="logsBusinessFilter"
                removable
                dense
                color="deep-purple"
                text-color="white"
                icon="business"
                @remove="logsBusinessFilter = null; fetchLogs()"
              >
                {{ getBusinessName(logsBusinessFilter) }}
              </q-chip>
              <q-chip
                v-if="logsDateFrom"
                removable
                dense
                color="orange"
                text-color="white"
                icon="event"
                @remove="logsDateFrom = ''; fetchLogs()"
              >
                De: {{ logsDateFrom }}
              </q-chip>
              <q-chip
                v-if="logsDateTo"
                removable
                dense
                color="orange"
                text-color="white"
                icon="event"
                @remove="logsDateTo = ''; fetchLogs()"
              >
                Até: {{ logsDateTo }}
              </q-chip>
            </div>
          </div>

          <!-- Logs Loading -->
          <div v-if="logsLoading" class="loading-state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando logs...</p>
          </div>

          <!-- Logs Empty -->
          <div v-else-if="logs.length === 0 && !logsLoading" class="empty-state">
            <q-icon name="receipt_long" size="64px" />
            <h3>Nenhum log encontrado</h3>
            <p v-if="hasActiveFilters">Tente ajustar seus filtros</p>
            <p v-else>Clique em <strong>Atualizar</strong> para carregar os logs</p>
          </div>

          <!-- Logs Table -->
          <div v-else class="table-container">
            <table class="data-table logs-table">
              <thead>
                <tr>
                  <th class="th-datetime">Data / Hora</th>
                  <th class="th-user">Usuário</th>
                  <th class="th-action">Ação</th>
                  <th class="th-entity">Entidade</th>
                  <th class="th-entity-id">ID</th>
                  <th class="th-ip">IP</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="log in logs" :key="log.id">
                  <tr class="clickable-row" :class="{ 'expanded-parent': expandedLogId === log.id }" @click="toggleLogDetail(log.id)">
                    <td>
                      <span class="datetime-text">{{ formatDateTime(log.created_at) }}</span>
                    </td>
                    <td>
                      <div v-if="log.user_name" class="user-info">
                        <div class="user-avatar-cell user-avatar-cell--sm">
                          <img v-if="log.user_avatar" :src="log.user_avatar" class="user-avatar-img" referrerpolicy="no-referrer" />
                          <div v-else class="user-avatar-initials user-avatar-initials--sm">{{ getInitials(log.user_name) }}</div>
                        </div>
                        <div class="user-details">
                          <span class="user-name">{{ log.user_name }}</span>
                          <span class="user-id">{{ log.user_email }}</span>
                        </div>
                      </div>
                      <span v-else class="text-muted">Sistema</span>
                    </td>
                    <td>
                      <q-badge
                        :color="getActionColor(log.action)"
                        :label="getActionLabel(log.action)"
                        class="action-badge"
                      />
                    </td>
                    <td>
                      <span class="entity-text">{{ getEntityLabel(log.entity) }}</span>
                    </td>
                    <td>
                      <span class="entity-id-text">{{ log.entity_id || '-' }}</span>
                    </td>
                    <td>
                      <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="ip-text">{{ log.ip || '-' }}</span>
                        <q-icon
                          v-if="log.payload"
                          :name="expandedLogId === log.id ? 'expand_less' : 'expand_more'"
                          size="18px"
                          color="grey-6"
                        />
                      </div>
                    </td>
                  </tr>
                  <!-- Inline detail row -->
                  <tr v-if="expandedLogId === log.id && log.payload" class="detail-row">
                    <td colspan="6">
                      <div class="log-detail-content">
                        <!-- Structured payload display -->
                        <template v-if="parsedPayload(log.payload)?.changes">
                          <div class="detail-label">
                            <q-icon name="compare_arrows" size="16px" />
                            <span>Alterações</span>
                          </div>
                          <div class="changes-list">
                            <div v-for="(change, field) in parsedPayload(log.payload).changes" :key="field" class="change-item">
                              <span class="change-field">{{ getFieldLabel(field) }}</span>
                              <span class="change-from">{{ change.from ?? '(vazio)' }}</span>
                              <q-icon name="arrow_forward" size="14px" color="grey-6" class="change-arrow" />
                              <span class="change-to">{{ change.to ?? '(vazio)' }}</span>
                            </div>
                          </div>
                          <div v-if="parsedPayload(log.payload).entity_name" class="detail-entity-name">
                            <q-icon name="label" size="14px" />
                            <span>{{ parsedPayload(log.payload).entity_name }}</span>
                          </div>
                        </template>
                        <!-- Simple key-value payload -->
                        <template v-else>
                          <div class="detail-label">
                            <q-icon name="data_object" size="16px" />
                            <span>Detalhes</span>
                          </div>
                          <div class="payload-fields">
                            <div v-for="(value, key) in parsedPayload(log.payload)" :key="key" class="payload-field">
                              <span class="payload-field-label">{{ getFieldLabel(key) }}</span>
                              <span class="payload-field-value">{{ value ?? '(vazio)' }}</span>
                            </div>
                          </div>
                        </template>
                        <div v-if="log.user_agent" class="detail-user-agent">
                          <q-icon name="devices" size="14px" />
                          <span>{{ log.user_agent }}</span>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="logsTotalPages > 1" class="logs-pagination">
            <div class="pagination-info">
              Mostrando {{ ((logsPage - 1) * logsPerPage) + 1 }}–{{ Math.min(logsPage * logsPerPage, logsTotal) }} de {{ logsTotal }} registros
            </div>
            <q-pagination
              v-model="logsPage"
              :max="logsTotalPages"
              :max-pages="7"
              direction-links
              boundary-links
              color="primary"
              active-design="unelevated"
              active-color="primary"
              active-text-color="white"
              @update:model-value="fetchLogs"
            />
          </div>
        </q-tab-panel>

        <!-- ================================================================ -->
        <!-- Tab: Developer (Admin) -->
        <!-- ================================================================ -->
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
                    flat round dense
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
                flat color="primary" icon="refresh"
                label="Gerar novo" no-caps size="sm"
                class="q-mt-sm"
                :loading="generatingToken"
                @click="generateDevToken"
              />
            </div>
          </div>

          <q-separator class="q-my-md" />

          <q-btn
            outline color="primary" icon="open_in_new"
            label="Abrir Swagger UI"
            @click="openSwagger" no-caps
          />
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <!-- Edit User Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Editar Usuário</h3>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-input v-model="editForm.name" label="Nome" outlined dense />
          <q-input v-model="editForm.email" label="Email" outlined dense class="q-mt-md" disable hint="E-mail vinculado ao Google" />
          <q-input v-model="editForm.phone" label="Telefone" outlined dense class="q-mt-md" />
          <q-select
            v-model="editForm.role" label="Papel" outlined dense
            :options="roleOptions" emit-value map-options class="q-mt-md"
          />
          <q-toggle v-model="editForm.is_active" label="Ativo" class="q-mt-md" />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete User Confirmation -->
    <q-dialog v-model="showDeleteDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Confirmar Exclusão</h3>
        </q-card-section>

        <q-card-section class="dialog-content">
          <p>Tem certeza que deseja excluir o usuário <strong>{{ selectedUser?.name }}</strong>?</p>
          <p class="delete-warning">Esta ação não pode ser desfeita.</p>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteDialog = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { copyToClipboard, useQuasar } from 'quasar'

export default defineComponent({
  name: 'AdminPanelPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    // =====================================================
    // Users tab state
    // =====================================================
    const users = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const deleting = ref(false)
    const searchQuery = ref('')
    const activeTab = ref('users')
    const roleFilter = ref('all')
    const currentUserRole = ref(null)
    const currentUserId = ref(null)

    // Dialogs
    const showEditDialog = ref(false)
    const showDeleteDialog = ref(false)
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

    // Developer tools
    const devToken = ref('')
    const copiedAccess = ref(false)
    const generatingToken = ref(false)

    // =====================================================
    // Logs tab state
    // =====================================================
    const logs = ref([])
    const logsLoading = ref(false)
    const logsPage = ref(1)
    const logsPerPage = ref(25)
    const logsTotal = ref(0)
    const logsTotalPages = ref(1)
    const expandedLogId = ref(null)

    // Filters
    const logsSearch = ref('')
    const logsActionFilter = ref(null)
    const logsEntityFilter = ref(null)
    const logsBusinessFilter = ref(null)
    const logsDateFrom = ref('')
    const logsDateTo = ref('')

    // Filter options (loaded from API)
    const actionOptions = ref([])
    const entityOptions = ref([])
    const businessOptions = ref([])

    // =====================================================
    // Computed
    // =====================================================
    const isAdmin = computed(() => currentUserRole.value === 'admin')
    const canSeeManagers = computed(() => ['admin', 'manager'].includes(currentUserRole.value))
    const canSeeProfessionals = computed(() => ['admin', 'manager'].includes(currentUserRole.value))

    const filteredUsers = computed(() => {
      let filtered = users.value

      if (roleFilter.value !== 'all') {
        filtered = filtered.filter(u => u.role === roleFilter.value)
      }

      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(u =>
          u.name?.toLowerCase().includes(query) ||
          u.email?.toLowerCase().includes(query)
        )
      }

      return filtered
    })

    const hasActiveFilters = computed(() => {
      return !!(logsSearch.value || logsActionFilter.value || logsEntityFilter.value || logsBusinessFilter.value || logsDateFrom.value || logsDateTo.value)
    })

    // =====================================================
    // Users methods
    // =====================================================
    const fetchUsers = async () => {
      loading.value = true
      try {
        const response = await api.get('/users')
        if (response.data?.success) {
          users.value = response.data.data?.users || []
        }
      } catch (err) {
        console.error('Erro ao buscar usuários:', err)
        if (err.response?.status === 403) {
          $q.notify({ type: 'warning', message: 'Você não tem permissão para visualizar usuários' })
          router.push('/app')
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
          currentUserId.value = response.data.data.user.id
          if (!['admin', 'manager'].includes(currentUserRole.value)) {
            $q.notify({ type: 'warning', message: 'Acesso restrito a administradores e gerentes' })
            router.push('/app')
          }
        }
      } catch (err) {
        console.error('Erro ao buscar role:', err)
      }
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

    const confirmDeleteUser = (user) => {
      selectedUser.value = user
      showDeleteDialog.value = true
    }

    const saveUser = async () => {
      saving.value = true
      try {
        const payload = {}
        if (editForm.value.name) payload.name = editForm.value.name
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

    const deleteUser = async () => {
      deleting.value = true
      try {
        await api.delete(`/users/${selectedUser.value.id}`)
        $q.notify({ type: 'positive', message: 'Usuário excluído com sucesso' })
        showDeleteDialog.value = false
        fetchUsers()
      } catch (err) {
        const msg = err.response?.data?.error?.message || 'Erro ao excluir'
        $q.notify({ type: 'negative', message: msg })
      } finally {
        deleting.value = false
      }
    }

    // =====================================================
    // Logs methods
    // =====================================================
    const fetchLogs = async () => {
      logsLoading.value = true
      try {
        const params = {
          page: logsPage.value,
          per_page: logsPerPage.value,
        }
        if (logsSearch.value) params.search = logsSearch.value
        if (logsActionFilter.value) params.action = logsActionFilter.value
        if (logsEntityFilter.value) params.entity = logsEntityFilter.value
        if (logsBusinessFilter.value) params.business_id = logsBusinessFilter.value
        if (logsDateFrom.value) params.date_from = logsDateFrom.value
        if (logsDateTo.value) params.date_to = logsDateTo.value

        const response = await api.get('/admin/audit-logs', { params })
        if (response.data?.success) {
          const d = response.data.data
          logs.value = d.logs || []
          logsTotal.value = d.total || 0
          logsTotalPages.value = d.total_pages || 1
        }
      } catch (err) {
        console.error('Erro ao buscar logs:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar logs do sistema' })
      } finally {
        logsLoading.value = false
      }
    }

    const fetchLogFilters = async () => {
      try {
        const response = await api.get('/admin/audit-logs/filters')
        if (response.data?.success) {
          const d = response.data.data
          actionOptions.value = (d.actions || []).map(a => ({ label: getActionLabel(a), value: a }))
          entityOptions.value = (d.entities || []).map(e => ({ label: getEntityLabel(e), value: e }))
          businessOptions.value = (d.businesses || []).map(b => ({ label: b.name, value: b.id }))
        }
      } catch (err) {
        console.error('Erro ao buscar filtros:', err)
      }
    }

    const clearFilters = () => {
      logsSearch.value = ''
      logsActionFilter.value = null
      logsEntityFilter.value = null
      logsBusinessFilter.value = null
      logsDateFrom.value = ''
      logsDateTo.value = ''
      logsPage.value = 1
      fetchLogs()
    }

    const toggleLogDetail = (id) => {
      expandedLogId.value = expandedLogId.value === id ? null : id
    }

    const getBusinessName = (businessId) => {
      const opt = businessOptions.value.find(b => b.value === businessId)
      return opt ? opt.label : `#${businessId}`
    }

    // =====================================================
    // Developer tools
    // =====================================================
    const generateDevToken = async () => {
      generatingToken.value = true
      try {
        const response = await api.get('/auth/dev-token')
        if (response.data?.success) {
          devToken.value = response.data.data.access_token || response.data.data.token || ''
        }
      } catch (err) {
        console.error('Erro ao gerar dev token:', err)
        $q.notify({ type: 'negative', message: 'Erro ao gerar token. Verifique se você é admin.' })
      } finally {
        generatingToken.value = false
      }
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
      window.open('/swagger', '_blank')
    }

    // =====================================================
    // Formatters & Helpers
    // =====================================================
    const getRoleLabel = (role) => {
      const labels = { admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }
      return labels[role] || role
    }

    const getRoleColor = (role) => {
      const colors = { admin: 'deep-purple', manager: 'blue', professional: 'teal', client: 'grey' }
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

    const formatDateTime = (dateString) => {
      if (!dateString) return '-'
      const d = new Date(dateString)
      return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    }

    const getActionLabel = (action) => {
      const labels = {
        create: 'Criar',
        update: 'Atualizar',
        delete: 'Excluir',
        login: 'Login',
        logout: 'Logout',
        cancel: 'Cancelar',
        check_in: 'Check-in',
        complete: 'Concluir',
        no_show: 'Não compareceu',
        queue_join: 'Entrar na fila',
        queue_leave: 'Sair da fila',
        queue_call_next: 'Chamar próximo',
        generate_code: 'Gerar código',
        add_user: 'Adicionar usuário',
        remove_user: 'Remover usuário',
      }
      return labels[action] || action
    }

    const getActionColor = (action) => {
      const colors = {
        create: 'positive',
        update: 'blue',
        delete: 'negative',
        login: 'teal',
        logout: 'grey',
        cancel: 'orange',
        check_in: 'cyan',
        complete: 'positive',
        no_show: 'red-5',
        queue_join: 'light-green',
        queue_leave: 'amber',
        queue_call_next: 'indigo',
        generate_code: 'purple',
        add_user: 'light-blue',
        remove_user: 'deep-orange',
      }
      return colors[action] || 'grey'
    }

    const getEntityLabel = (entity) => {
      const labels = {
        user: 'Usuário',
        business: 'Negócio',
        establishment: 'Estabelecimento',
        service: 'Serviço',
        professional: 'Profissional',
        queue: 'Fila',
        appointment: 'Agendamento',
        queue_entry: 'Entrada na fila',
      }
      return labels[entity] || entity || '-'
    }

    const parsedPayload = (payload) => {
      if (!payload) return null
      if (typeof payload === 'string') {
        try { return JSON.parse(payload) } catch { return null }
      }
      return payload
    }

    const getFieldLabel = (field) => {
      const labels = {
        name: 'Nome', email: 'E-mail', role: 'Papel', status: 'Status',
        phone: 'Telefone', address: 'Endereço', description: 'Descrição',
        slug: 'Slug', timezone: 'Fuso horário',
        duration_minutes: 'Duração (min)', price: 'Preço',
        specialization: 'Especialização',
        establishment_id: 'Estabelecimento', business_id: 'Negócio',
        service_id: 'Serviço', professional_id: 'Profissional',
        start_at: 'Início', entity_name: 'Nome da entidade',
        target_user_id: 'Usuário alvo', removed_user_id: 'Usuário removido',
        user_id: 'Usuário', queue_id: 'Fila', position: 'Posição',
        access_code: 'Código de acesso', ip: 'IP',
      }
      return labels[field] || field
    }

    const formatPayload = (payload) => {
      if (!payload) return ''
      const parsed = parsedPayload(payload)
      if (!parsed) return ''
      return JSON.stringify(parsed, null, 2)
    }

    // =====================================================
    // Lifecycle
    // =====================================================
    watch(roleFilter, () => { /* handled by computed */ })

    // Load only filters when switching to logs tab (lazy loading: user clicks Atualizar)
    watch(activeTab, (newTab) => {
      if (newTab === 'logs' && actionOptions.value.length === 0) {
        fetchLogFilters()
      }
    })

    onMounted(async () => {
      await fetchCurrentUser()
      if (['admin', 'manager'].includes(currentUserRole.value)) {
        await fetchUsers()
      }
    })

    return {
      // Users tab
      users, loading, saving, deleting, searchQuery,
      activeTab, roleFilter, currentUserRole, currentUserId,
      showEditDialog, showDeleteDialog, selectedUser,
      editForm, roleOptions,
      // Dev tools
      devToken, copiedAccess, generatingToken,
      // Computed
      isAdmin, canSeeManagers, canSeeProfessionals, filteredUsers, hasActiveFilters,
      // Users methods
      editUser, confirmDeleteUser, saveUser, deleteUser,
      // Dev methods
      generateDevToken, copyToken, openSwagger,
      // Logs tab
      logs, logsLoading, logsPage, logsPerPage, logsTotal, logsTotalPages, expandedLogId,
      logsSearch, logsActionFilter, logsEntityFilter, logsBusinessFilter,
      logsDateFrom, logsDateTo,
      actionOptions, entityOptions, businessOptions,
      fetchLogs, clearFilters, toggleLogDetail, getBusinessName,
      // Formatters
      getRoleLabel, getRoleColor, getInitials, formatDate, formatDateTime,
      getActionLabel, getActionColor, getEntityLabel, formatPayload,
      parsedPayload, getFieldLabel,
      router
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
  column-gap: 1rem;
  row-gap: 0.25rem;
}

.header-left {
  flex: 1;
  min-height: 40px;
  display: flex;
  align-items: center;
}

.header-bottom {
  flex-basis: 100%;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--qm-text-muted);
  margin: 0;
}

// Tabs Container
.admin-tabs-container {
  padding: 0;
  overflow: hidden;
}

.admin-tabs {
  margin-top: 10px;
  padding: 0 1rem;

  :deep(.q-tab) {
    padding: 0 1rem;
    .q-focus-helper { border-radius: 15px; }
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

.panel-header-right {
  flex-shrink: 0;
}

// Filter Tabs
.filter-tabs {
  background: var(--qm-bg-secondary);
  border-radius: 10px;
  padding: 0.25rem;
}

.search-bar {
  display: flex;
  justify-content: flex-end;
}

.search-input {
  width: 280px;
  @media (max-width: 600px) { width: 100%; }
}

// ==========================================
// Logs Filters
// ==========================================
.logs-filters {
  margin-bottom: 1.25rem;
}

.filters-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  align-items: flex-end;
}

.filter-input {
  min-width: 140px;
  flex: 1;
  max-width: 200px;
}

.filter-search {
  min-width: 200px;
  max-width: 280px;
}

.filter-date {
  min-width: 130px;
  max-width: 160px;
}

.filter-btn {
  height: 40px;
}

.active-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-top: 0.75rem;
}

// ==========================================
// Logs Table
// ==========================================
.logs-table {
  .th-datetime { min-width: 150px; }
  .th-user { min-width: 180px; }
  .th-action { min-width: 120px; }
  .th-entity { min-width: 120px; }
  .th-entity-id { min-width: 60px; }
  .th-ip { min-width: 110px; }
  .th-details { width: 70px; }
}

.datetime-text {
  font-size: 0.8125rem;
  color: var(--qm-text-secondary);
  white-space: nowrap;
}

.action-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.entity-text {
  font-size: 0.8125rem;
  color: var(--qm-text-primary);
  font-weight: 500;
}

.entity-id-text {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
  font-family: 'Consolas', 'Monaco', monospace;
}

.ip-text {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  font-family: 'Consolas', 'Monaco', monospace;
}

.text-muted {
  color: var(--qm-text-muted);
  font-size: 0.8125rem;
}

.user-avatar-cell--sm {
  width: 28px;
  height: 28px;
}

.user-avatar-initials--sm {
  width: 28px;
  height: 28px;
  font-size: 0.625rem;
}

// Detail row
.detail-row {
  td {
    padding: 0 !important;
    border-bottom: 1px solid var(--qm-border);
  }
}

.log-detail-content {
  padding: 0.75rem 1.5rem 1rem;
  background: var(--qm-bg-secondary);
}

.detail-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--qm-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.375rem;
}

.detail-json {
  background: var(--qm-bg-tertiary, var(--qm-surface));
  border: 1px solid var(--qm-border);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  font-size: 0.75rem;
  font-family: 'Consolas', 'Monaco', monospace;
  color: var(--qm-text-primary);
  overflow-x: auto;
  max-height: 200px;
  white-space: pre-wrap;
  word-break: break-all;
  margin: 0;
}

.detail-user-agent {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.5rem;
  font-size: 0.6875rem;
  color: var(--qm-text-muted);
}

.detail-entity-name {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.5rem;
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

// Structured changes display
.changes-list {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.change-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.625rem;
  background: var(--qm-bg-tertiary, var(--qm-surface));
  border: 1px solid var(--qm-border);
  border-radius: 6px;
  font-size: 0.8125rem;
  flex-wrap: wrap;
}

.change-field {
  font-weight: 600;
  color: var(--qm-text-primary);
  min-width: 120px;
}

.change-from {
  color: var(--qm-error, #e53935);
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.75rem;
  text-decoration: line-through;
  opacity: 0.8;
}

.change-arrow {
  flex-shrink: 0;
}

.change-to {
  color: var(--qm-success, #43a047);
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.75rem;
  font-weight: 600;
}

// Simple payload fields
.payload-fields {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.payload-field {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.25rem 0.625rem;
  font-size: 0.8125rem;
}

.payload-field-label {
  font-weight: 600;
  color: var(--qm-text-muted);
  min-width: 120px;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.payload-field-value {
  color: var(--qm-text-primary);
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.75rem;
}

// Expanded parent highlight
tr.expanded-parent {
  background: var(--qm-bg-secondary) !important;
  border-bottom-color: transparent !important;
}

// Pagination
.logs-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.25rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.pagination-info {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
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

  p { margin: 0; font-size: 0.875rem; }
}

// Table
.table-container {
  overflow-x: auto;
  border: 1px solid var(--qm-border);
  border-radius: 12px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;

  th, td { padding: 0.875rem 1.5rem; text-align: left; }

  thead {
    tr { background: var(--qm-bg-secondary); }

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
      &:last-child { border-bottom: none; }
      &:hover { background: var(--qm-bg-secondary); }
    }

    td { font-size: 0.875rem; color: var(--qm-text-primary); }
  }
}

.th-user { min-width: 200px; }
.th-email { min-width: 200px; }
.th-role { min-width: 120px; }
.th-status { min-width: 80px; }
.th-created { min-width: 100px; }
.th-actions { width: 100px; }

tr.clickable-row { cursor: pointer; }

// User Info Cell
.user-info { display: flex; align-items: center; gap: 0.75rem; }

.user-avatar-cell {
  width: 36px; height: 36px;
  border-radius: 10px; overflow: hidden; flex-shrink: 0;
}

.user-avatar-img { width: 100%; height: 100%; object-fit: cover; }

.user-avatar-initials {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--qm-brand); color: var(--qm-brand-contrast);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 600;
}

.user-details { display: flex; flex-direction: column; }
.user-name { font-weight: 600; font-size: 0.875rem; }
.user-id { font-size: 0.75rem; color: var(--qm-text-muted); }
.email-text { font-size: 0.8125rem; color: var(--qm-text-secondary); }
.date-text { font-size: 0.8125rem; color: var(--qm-text-muted); }
.row-actions { display: flex; gap: 0.25rem; }

// Developer Section
.dev-warning {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 0.875rem; color: #e8a317;
  padding: 1rem; background: rgba(255, 152, 0, 0.1);
  border-radius: 10px; margin-bottom: 1.5rem;
}

.token-section { margin-bottom: 1rem; }

.generate-token-area {
  padding: 1.5rem; background: var(--qm-bg-tertiary);
  border-radius: 10px; text-align: center;
}

.token-header {
  margin-bottom: 0.75rem;
  h4 { margin: 0 0 0.25rem; font-size: 1rem; font-weight: 600; color: var(--qm-text-primary); }
  p { margin: 0; font-size: 0.8125rem; color: var(--qm-text-muted); }
}

.token-input { font-family: 'Consolas', 'Monaco', monospace; font-size: 0.75rem; }

.token-expiry {
  display: flex; align-items: center; gap: 0.375rem;
  font-size: 0.75rem; color: var(--qm-text-muted); margin: 0.5rem 0 0;
}

// Dialog Styles
.dialog-card {
  width: 100%; max-width: 500px;
  border-radius: 16px; background: var(--qm-bg-primary);
}

.dialog-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--qm-border);

  h3 { margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--qm-text-primary); }
}

.dialog-content { padding: 1.5rem; }

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

// Responsive
@media (max-width: 768px) {
  .filters-row {
    flex-direction: column;
  }

  .filter-input,
  .filter-search,
  .filter-date {
    max-width: 100%;
    min-width: 100%;
  }

  .logs-pagination {
    flex-direction: column;
    align-items: center;
  }
}
</style>
