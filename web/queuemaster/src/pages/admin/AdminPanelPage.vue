<template>
  <q-page class="admin-page">
    <div class="page-header">
      <h1 class="page-title">Painel de Administra??o</h1>
      <p class="page-subtitle">{{ isAdmin ? 'Gerencie usuários, logs e ferramentas do sistema.' : 'Visualize o que está dentro do seu escopo de gestao.' }}</p>
    </div>

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
        <q-tab v-if="isAdmin" name="plans" icon="workspace_premium" label="Planos" no-caps />
        <q-tab v-if="isAdmin" name="developer" icon="code" label="Developer" no-caps />
      </q-tabs>

      <q-separator style="margin-top: 10px;" />

      <q-tab-panels v-model="activeTab" animated class="tab-panels">
        <q-tab-panel name="users" class="tab-panel">
          <div class="panel-head">
            <div>
              <h2>Gerenciamento de usuários</h2>
              <p>{{ isAdmin ? 'Administre todos os usuários do sistema.' : 'Visualize os gerentes e profissionais vinculados ao seu escopo.' }}</p>
            </div>
          </div>

          <div class="toolbar">
            <div class="filter-tabs">
              <q-tabs v-model="roleFilter" dense no-caps active-color="primary" indicator-color="primary" inline-label>
                <q-tab v-for="option in roleFilterOptions" :key="option.value" :name="option.value" :label="option.label" />
              </q-tabs>
            </div>

            <q-input
              v-model="searchQuery"
              outlined
              dense
              clearable
              class="search"
              placeholder="Buscar por nome, email, telefone ou endereco"
            >
              <template #prepend>
                <q-icon name="search" />
              </template>
            </q-input>
          </div>

          <div v-if="loading" class="state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando usuários...</p>
          </div>

          <div v-else-if="filteredUsers.length === 0" class="state">
            <q-icon name="group_off" size="52px" />
            <p>Nenhum usuário encontrado.</p>
          </div>

          <div v-else class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Usuário</th>
                  <th>Email</th>
                  <th>Papel</th>
                  <th>Status</th>
                  <th>Criado em</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="user in filteredUsers"
                  :key="user.id"
                  class="clickable"
                  @click="openUserProfile(user.id)"
                >
                  <td>
                    <div class="user-cell">
                      <div class="avatar">
                        <img v-if="user.has_avatar" :src="resolveUserAvatarUrl(user)" alt="" />
                        <span v-else>{{ getInitials(user.name) }}</span>
                      </div>
                      <div class="meta">
                        <div class="meta-top">
                          <strong>{{ user.name }}</strong>
                        </div>
                        <span>ID {{ user.id }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="email-text">{{ user.email }}</span>
                  </td>
                  <td>
                    <q-badge :color="getRoleColor(resolveUserRole(user))" :label="getRoleLabel(resolveUserRole(user))" />
                  </td>
                  <td>
                    <q-badge :color="normalizeBoolean(user.is_active) ? 'positive' : 'negative'" :label="normalizeBoolean(user.is_active) ? 'Ativo' : 'Inativo'" />
                  </td>
                  <td>{{ formatDate(user.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </q-tab-panel>

        <q-tab-panel name="logs" class="tab-panel">
          <div class="panel-head">
            <div>
              <h2>Logs</h2>
              <p>{{ isAdmin ? 'Auditoria global do sistema.' : 'Auditoria filtrada pelo seu escopo.' }}</p>
            </div>
            <q-btn outline color="primary" icon="refresh" label="Atualizar" no-caps :loading="logsLoading" @click="fetchLogs" />
          </div>

          <div class="filters">
            <q-input v-model="logsSearch" outlined dense clearable stack-label class="filter-field filter-search" label="Busca" placeholder="Buscar" @keyup.enter="fetchLogs">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="logsActionFilter" outlined dense clearable stack-label class="filter-field" emit-value map-options :options="actionOptions" label="Acao" />
            <q-select v-model="logsEntityFilter" outlined dense clearable stack-label class="filter-field" emit-value map-options :options="entityOptions" label="Entidade" />
            <q-select v-if="businessOptions.length" v-model="logsBusinessFilter" outlined dense clearable stack-label class="filter-field" emit-value map-options :options="businessOptions" label="Negócio" />
            <q-input v-model="logsDateFrom" outlined dense stack-label class="filter-field filter-date" type="date" label="De" />
            <q-input v-model="logsDateTo" outlined dense stack-label class="filter-field filter-date" type="date" label="Ate" />
            <div class="filter-actions">
              <q-btn color="primary" icon="filter_list" label="Filtrar" no-caps @click="fetchLogs" />
              <q-btn v-if="hasActiveFilters" flat color="grey-7" icon="clear_all" label="Limpar" no-caps @click="clearFilters" />
            </div>
          </div>

          <div v-if="logsLoading" class="state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando logs...</p>
          </div>

          <div v-else-if="logs.length === 0" class="state">
            <q-icon name="receipt_long" size="52px" />
            <p>Nenhum log encontrado.</p>
          </div>

          <div v-else class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Usuário</th>
                  <th>Acao</th>
                  <th>Entidade</th>
                  <th>Resumo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in logs" :key="log.id" class="clickable" @click="openPayloadDialog(log)">
                  <td>{{ formatDateTime(log.created_at) }}</td>
                  <td>{{ log.user_name || 'Sistema' }}</td>
                  <td><q-badge :color="getActionColor(log.action)" :label="getActionLabel(log.action)" /></td>
                  <td>{{ getEntityLabel(log.entity) }}</td>
                  <td>{{ getLogSummary(log) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="logsTotalPages > 1" class="pager">
            <span>Pagina {{ logsPage }} de {{ logsTotalPages }}</span>
            <q-pagination
              v-model="logsPage"
              :max="logsTotalPages"
              :max-pages="7"
              direction-links
              boundary-links
              color="primary"
              active-design="unelevated"
              active-color="primary"
              @update:model-value="fetchLogs"
            />
          </div>
        </q-tab-panel>

        <q-tab-panel v-if="isAdmin" name="plans" class="tab-panel">
          <div class="panel-head">
            <div>
              <h2>Planos</h2>
              <p>Gerencie os planos padr?o e personalizados mantendo os bloqueios da nova logica.</p>
            </div>
            <q-btn color="primary" icon="add" label="Novo plano" no-caps @click="openCreatePlan" />
          </div>

          <div v-if="plansLoading" class="state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando planos...</p>
          </div>

          <div v-else-if="plans.length === 0" class="state">
            <q-icon name="workspace_premium" size="52px" />
            <p>Nenhum plano disponivel.</p>
          </div>

          <div v-else class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Plano</th>
                  <th>Negócios</th>
                  <th>Estabelecimentos</th>
                  <th>Gerentes</th>
                  <th>Profissionais</th>
                  <th>Status</th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="plan in plans" :key="plan.id">
                  <td>
                    <div class="plan-info">
                      <span class="plan-name">{{ plan.name }}</span>
                      <span class="plan-id">ID {{ plan.id }} | {{ plan.active_manager_count || 0 }} titular(es)</span>
                    </div>
                  </td>
                  <td>{{ formatLimit(plan.max_businesses) }}</td>
                  <td>{{ formatLimit(plan.max_establishments_per_business) }}</td>
                  <td>{{ formatLimit(plan.max_managers) }}</td>
                  <td>{{ formatLimit(plan.max_professionals_per_establishment) }}</td>
                  <td>
                    <q-badge :color="normalizeBoolean(plan.is_active) ? 'positive' : 'grey-7'" :label="normalizeBoolean(plan.is_active) ? 'Ativo' : 'Inativo'" />
                  </td>
                  <td>
                    <div class="plan-actions">
                      <q-btn flat round dense icon="edit" @click="editPlan(plan)">
                        <q-tooltip>Editar</q-tooltip>
                      </q-btn>
                      <q-btn flat round dense icon="delete" color="negative" @click="confirmDeletePlan(plan)">
                        <q-tooltip>Excluir</q-tooltip>
                      </q-btn>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </q-tab-panel>

        <q-tab-panel v-if="isAdmin" name="developer" class="tab-panel">
          <div class="panel-head">
            <div>
              <h2>Developer tools</h2>
              <p>Ferramentas de desenvolvimento e depura??o.</p>
            </div>
          </div>

          <div class="dev-warning">
            <q-icon name="warning" size="20px" />
            <span>Tokens não ficam expostos no browser. Gere um token temporário apenas para uso no Swagger.</span>
          </div>

          <div class="token-section">
            <div class="token-header">
              <h4>Token para Swagger</h4>
              <p>Gera um token JWT de curta dura??o para testar endpoints protegidos.</p>
            </div>

            <div v-if="!devToken" class="generate-token-area">
              <q-btn
                color="primary"
                icon="vpn_key"
                label="Gerar token para Swagger"
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
                <template #append>
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
                Expira em 5 minutos. Gere outro se necessario.
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

          <q-btn outline color="primary" icon="open_in_new" label="Abrir Swagger UI" no-caps @click="openSwagger" />
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <q-dialog v-model="showPlanDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-head">
          <h3>{{ isEditingPlan ? 'Editar plano' : 'Novo plano' }}</h3>
          <q-btn flat round dense icon="close" @click="showPlanDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="planForm.name" outlined dense label="Nome do plano" />
          <q-input v-model.number="planForm.max_businesses" outlined dense clearable type="number" label="Max. negócios" class="q-mt-md" />
          <q-input v-model.number="planForm.max_establishments_per_business" outlined dense clearable type="number" label="Max. estabelecimentos / negócio" class="q-mt-md" />
          <q-input v-model.number="planForm.max_managers" outlined dense clearable type="number" label="Max. gerentes / negócio" class="q-mt-md" />
          <q-input v-model.number="planForm.max_professionals_per_establishment" outlined dense clearable type="number" label="Max. profissionais / estabelecimento" class="q-mt-md" />
          <q-toggle v-model="planForm.is_active" label="Plano ativo" class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" no-caps @click="showPlanDialog = false" />
          <q-btn color="primary" :label="isEditingPlan ? 'Salvar' : 'Criar'" no-caps :loading="savingPlan" @click="savePlan" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showDeletePlanDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-head">
          <h3>Excluir plano</h3>
        </q-card-section>
        <q-card-section>
          <p>Deseja excluir <strong>{{ selectedPlan?.name }}</strong>?</p>
          <p class="muted">Planos com vínculos ativos ou histórico de uso s?o protegidos pelo backend.</p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" no-caps @click="showDeletePlanDialog = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deletingPlan" @click="deletePlan" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showPayloadDialog">
      <q-card class="dialog-card wide">
        <q-card-section class="dialog-head">
          <h3>Detalhes do log</h3>
          <q-btn flat round dense icon="close" @click="showPayloadDialog = false" />
        </q-card-section>
        <q-card-section>
          <pre class="payload">{{ formatPayload(selectedLog?.payload) || 'Sem payload registrado.' }}</pre>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { computed, defineComponent, onMounted, ref, watch } from 'vue'
import { copyToClipboard, openURL, useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { resolveUserAvatarUrl } from 'src/utils/userAvatar'

const emptyPlanForm = () => ({
  name: '',
  max_businesses: null,
  max_establishments_per_business: null,
  max_managers: null,
  max_professionals_per_establishment: null,
  is_active: true
})

export default defineComponent({
  name: 'AdminPanelPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    const activeTab = ref('users')
    const currentUserRole = ref(null)

    const users = ref([])
    const loading = ref(false)
    const searchQuery = ref('')
    const roleFilter = ref('all')

    const logs = ref([])
    const logsLoading = ref(false)
    const logsPage = ref(1)
    const logsPerPage = ref(25)
    const logsTotalPages = ref(1)
    const logsSearch = ref('')
    const logsActionFilter = ref(null)
    const logsEntityFilter = ref(null)
    const logsBusinessFilter = ref(null)
    const logsDateFrom = ref('')
    const logsDateTo = ref('')
    const actionOptions = ref([])
    const entityOptions = ref([])
    const businessOptions = ref([])
    const selectedLog = ref(null)
    const showPayloadDialog = ref(false)

    const plans = ref([])
    const plansLoading = ref(false)
    const showPlanDialog = ref(false)
    const showDeletePlanDialog = ref(false)
    const isEditingPlan = ref(false)
    const selectedPlan = ref(null)
    const savingPlan = ref(false)
    const deletingPlan = ref(false)
    const planForm = ref(emptyPlanForm())

    const devToken = ref('')
    const copiedAccess = ref(false)
    const generatingToken = ref(false)

    const isAdmin = computed(() => currentUserRole.value === 'admin')
    const resolveUserRole = (user) => {
      if (!user || typeof user !== 'object') return null
      if (user.role === 'admin' || user.effective_role === 'admin') return 'admin'
      return user.effective_role || user.role || null
    }

    const roleFilterOptions = computed(() => {
      const options = [
        { label: 'Todos', value: 'all' },
        { label: 'Gerentes', value: 'manager' },
        { label: 'Profissionais', value: 'professional' }
      ]
      if (isAdmin.value) {
        options.push({ label: 'Clientes', value: 'client' })
        options.push({ label: 'Admins', value: 'admin' })
      }
      return options
    })

    const filteredUsers = computed(() => {
      let list = users.value
      if (roleFilter.value !== 'all') {
        list = list.filter((user) => resolveUserRole(user) === roleFilter.value)
      }
      if (!searchQuery.value) return list

      const term = searchQuery.value.toLowerCase()
      return list.filter((user) => [user.name, user.email, user.phone, user.address_line_1, user.address_line_2]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(term))
    })

    const hasActiveFilters = computed(() => Boolean(
      logsSearch.value ||
      logsActionFilter.value ||
      logsEntityFilter.value ||
      logsBusinessFilter.value ||
      logsDateFrom.value ||
      logsDateTo.value
    ))

    const normalizeBoolean = (value) => value === true || value === 1 || value === '1'

    const notifyError = (error, fallback) => {
      $q.notify({ type: 'negative', message: error.response?.data?.error?.message || fallback })
    }

    const fetchCurrentUser = async () => {
      try {
        const response = await api.get('/auth/me')
        const user = response.data?.data?.user || {}
        localStorage.setItem('user', JSON.stringify(user))
        currentUserRole.value = resolveUserRole(user)
        if (!['admin', 'manager'].includes(currentUserRole.value)) {
          $q.notify({ type: 'warning', message: 'Acesso restrito ao painel administrativo.' })
          router.push('/app')
        }
      } catch {
        router.push('/app')
      }
    }

    const fetchUsers = async () => {
      loading.value = true
      try {
        const response = await api.get('/admin/users')
        users.value = response.data?.data?.users || []
      } catch (error) {
        notifyError(error, 'Erro ao carregar usuários.')
      } finally {
        loading.value = false
      }
    }

    const fetchLogFilters = async () => {
      try {
        const response = await api.get('/admin/audit-logs/filters')
        const data = response.data?.data || {}
        actionOptions.value = (data.actions || []).map((item) => ({ label: getActionLabel(item), value: item }))
        entityOptions.value = (data.entities || []).map((item) => ({ label: getEntityLabel(item), value: item }))
        businessOptions.value = (data.businesses || []).map((item) => ({ label: item.name, value: item.id }))
      } catch (error) {
        console.error(error)
      }
    }

    const fetchLogs = async () => {
      logsLoading.value = true
      try {
        const params = { page: logsPage.value, per_page: logsPerPage.value }
        if (logsSearch.value) params.search = logsSearch.value
        if (logsActionFilter.value) params.action = logsActionFilter.value
        if (logsEntityFilter.value) params.entity = logsEntityFilter.value
        if (logsBusinessFilter.value) params.business_id = logsBusinessFilter.value
        if (logsDateFrom.value) params.date_from = logsDateFrom.value
        if (logsDateTo.value) params.date_to = logsDateTo.value

        const response = await api.get('/admin/audit-logs', { params })
        const data = response.data?.data || {}
        logs.value = data.logs || []
        logsTotalPages.value = data.total_pages || 1
      } catch (error) {
        notifyError(error, 'Erro ao carregar logs.')
      } finally {
        logsLoading.value = false
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

    const fetchPlans = async () => {
      plansLoading.value = true
      try {
        const response = await api.get('/admin/plans')
        const data = response.data?.data || {}
        plans.value = data.plans || []
      } catch (error) {
        notifyError(error, 'Erro ao carregar planos.')
      } finally {
        plansLoading.value = false
      }
    }

    const openCreatePlan = () => {
      isEditingPlan.value = false
      selectedPlan.value = null
      planForm.value = emptyPlanForm()
      showPlanDialog.value = true
    }

    const editPlan = (plan) => {
      isEditingPlan.value = true
      selectedPlan.value = plan
      planForm.value = {
        name: plan.name || '',
        max_businesses: plan.max_businesses,
        max_establishments_per_business: plan.max_establishments_per_business,
        max_managers: plan.max_managers,
        max_professionals_per_establishment: plan.max_professionals_per_establishment,
        is_active: normalizeBoolean(plan.is_active)
      }
      showPlanDialog.value = true
    }

    const normalizePlanPayload = () => {
      const payload = { ...planForm.value }
      ;['max_businesses', 'max_establishments_per_business', 'max_managers', 'max_professionals_per_establishment'].forEach((field) => {
        if (payload[field] === '' || payload[field] === 0) payload[field] = null
      })
      return payload
    }

    const savePlan = async () => {
      if (!planForm.value.name?.trim()) {
        $q.notify({ type: 'warning', message: 'Informe o nome do plano.' })
        return
      }
      savingPlan.value = true
      try {
        const payload = normalizePlanPayload()
        if (isEditingPlan.value && selectedPlan.value) {
          await api.put(`/admin/plans/${selectedPlan.value.id}`, payload)
          $q.notify({ type: 'positive', message: 'Plano atualizado com sucesso.' })
        } else {
          await api.post('/admin/plans', payload)
          $q.notify({ type: 'positive', message: 'Plano criado com sucesso.' })
        }
        showPlanDialog.value = false
        fetchPlans()
      } catch (error) {
        notifyError(error, 'Erro ao salvar plano.')
      } finally {
        savingPlan.value = false
      }
    }

    const confirmDeletePlan = (plan) => {
      selectedPlan.value = plan
      showDeletePlanDialog.value = true
    }

    const deletePlan = async () => {
      if (!selectedPlan.value) return
      deletingPlan.value = true
      try {
        await api.delete(`/admin/plans/${selectedPlan.value.id}`)
        $q.notify({ type: 'positive', message: 'Plano excluido com sucesso.' })
        showDeletePlanDialog.value = false
        fetchPlans()
      } catch (error) {
        notifyError(error, 'Erro ao excluir plano.')
      } finally {
        deletingPlan.value = false
      }
    }

    const generateDevToken = async () => {
      generatingToken.value = true
      try {
        const response = await api.get('/auth/dev-token')
        devToken.value = response.data?.data?.token || ''
        $q.notify({ type: 'positive', message: 'Token gerado com sucesso.' })
      } catch (error) {
        notifyError(error, 'Erro ao gerar token.')
      } finally {
        generatingToken.value = false
      }
    }

    const copyToken = async () => {
      if (!devToken.value) return
      await copyToClipboard(devToken.value)
      copiedAccess.value = true
      window.setTimeout(() => {
        copiedAccess.value = false
      }, 1500)
      $q.notify({ type: 'positive', message: 'Token copiado.' })
    }

    const openUserProfile = (userId) => {
      router.push({ name: 'user-detail', params: { id: userId } })
    }

    const openSwagger = () => openURL(`${import.meta.env.VITE_API_BASE_URL || 'http://localhost'}/swagger`)
    const openPayloadDialog = (log) => { selectedLog.value = log; showPayloadDialog.value = true }
    const parsedPayload = (payload) => {
      if (!payload) return null
      if (typeof payload === 'string') {
        try { return JSON.parse(payload) } catch { return null }
      }
      return payload
    }
    const formatPayload = (payload) => {
      const parsed = parsedPayload(payload)
      return parsed ? JSON.stringify(parsed, null, 2) : ''
    }
    const getLogSummary = (log) => {
      const payload = parsedPayload(log?.payload) || {}
      return payload.summary || payload.entity_name || payload.name || `${getActionLabel(log?.action)} ${getEntityLabel(log?.entity).toLowerCase()}`
    }
    const getActionLabel = (action) => ({ create: 'Criar', update: 'Atualizar', delete: 'Excluir', login: 'Login', logout: 'Logout', cancel: 'Cancelar', check_in: 'Check-in', complete: 'Concluir', queue_join: 'Entrar na fila', queue_leave: 'Sair da fila', queue_call_next: 'Chamar pr?ximo', add_user: 'Adicionar usuário', remove_user: 'Remover usuário', assign_plan: 'Atribuir plano' }[action] || action || '-')
    const getActionColor = (action) => ({ create: 'positive', update: 'blue', delete: 'negative', login: 'teal', logout: 'grey-7', cancel: 'orange', check_in: 'cyan', complete: 'positive', queue_join: 'light-green', queue_leave: 'amber', queue_call_next: 'indigo', add_user: 'light-blue', remove_user: 'deep-orange', assign_plan: 'primary' }[action] || 'grey-7')
    const getEntityLabel = (entity) => ({ user: 'Usuário', business: 'Negócio', establishment: 'Estabelecimento', service: 'Serviço', professional: 'Profissional', queue: 'Fila', appointment: 'Agendamento', admin_user_profile: 'Perfil administrativo', user_plan_subscription: 'Assinatura' }[entity] || entity || '-')
    const getRoleLabel = (role) => ({ admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }[role] || role || '-')
    const getRoleColor = (role) => ({ admin: 'deep-orange', manager: 'primary', professional: 'teal', client: 'grey-7' }[role] || 'grey-7')
    const getInitials = (name) => name ? name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() : '?'
    const formatDate = (value) => value ? new Date(value).toLocaleDateString('pt-BR') : '-'
    const formatDateTime = (value) => value ? new Date(value).toLocaleString('pt-BR') : '-'
    const formatLimit = (value) => (value === null || value === undefined || value === '' ? 'Ilimitado' : value)
    watch(activeTab, async (tab) => {
      if (!isAdmin.value && ['plans', 'developer'].includes(tab)) {
        activeTab.value = 'users'
        return
      }
      if (tab === 'logs' && actionOptions.value.length === 0) await fetchLogFilters()
      if (tab === 'plans' && isAdmin.value && plans.value.length === 0) await fetchPlans()
    })

    onMounted(async () => {
      await fetchCurrentUser()
      if (!['admin', 'manager'].includes(currentUserRole.value)) return
      await fetchUsers()
    })

    return {
      actionOptions,
      activeTab,
      businessOptions,
      clearFilters,
      confirmDeletePlan,
      copyToken,
      copiedAccess,
      deletePlan,
      deletingPlan,
      devToken,
      editPlan,
      entityOptions,
      fetchLogs,
      filteredUsers,
      formatDate,
      formatDateTime,
      formatLimit,
      formatPayload,
      generateDevToken,
      generatingToken,
      getActionColor,
      getActionLabel,
      getEntityLabel,
      getInitials,
      getLogSummary,
      getRoleColor,
      getRoleLabel,
      hasActiveFilters,
      isAdmin,
      isEditingPlan,
      loading,
      logs,
      logsActionFilter,
      logsBusinessFilter,
      logsDateFrom,
      logsDateTo,
      logsEntityFilter,
      logsLoading,
      logsPage,
      logsPerPage,
      logsSearch,
      logsTotalPages,
      normalizeBoolean,
      openUserProfile,
      openCreatePlan,
      openPayloadDialog,
      openSwagger,
      planForm,
      plans,
      plansLoading,
      roleFilter,
      roleFilterOptions,
      resolveUserRole,
      resolveUserAvatarUrl,
      router,
      savePlan,
      savingPlan,
      searchQuery,
      selectedLog,
      selectedPlan,
      showDeletePlanDialog,
      showPayloadDialog,
      showPlanDialog
    }
  }
})
</script>

<style lang="scss" scoped>
.admin-page { padding: 0 1.5rem 1.5rem; }
.page-header { margin-bottom: 1.5rem; }
.page-title { margin: 0 0 0.25rem; font-size: 1.5rem; font-weight: 700; color: var(--qm-text-primary); }
.page-subtitle, .panel-head p, .meta span, .muted, .email-text, .plan-id, .token-header p, .token-expiry { color: var(--qm-text-muted); }
.page-subtitle { margin: 0; font-size: 0.875rem; }

.admin-tabs-container { padding: 0; overflow: hidden; }
.admin-tabs {
  margin-top: 10px;
  padding: 0 1rem;

  :deep(.q-tab__label) {
    font-weight: 500;
  }
}

.tab-panels { background: transparent; }
.tab-panel { padding: 1.5rem; }

.panel-head h2 {
  margin: 0 0 0.25rem;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.panel-head,
.toolbar,
.filters,
.pager,
.filter-actions,
.dialog-head,
.plan-actions {
  display: flex;
  gap: 1rem;
}

.panel-head,
.pager,
.dialog-head {
  justify-content: space-between;
  align-items: flex-start;
}

.toolbar,
.filters,
.filter-actions,
.plan-actions {
  flex-wrap: wrap;
  align-items: center;
}

.toolbar {
  justify-content: space-between;
  margin-bottom: 1rem;
}

.filter-tabs {
  background: var(--qm-bg-secondary);
  border-radius: 10px;
  padding: 0.25rem;
}

.filters { margin-bottom: 1rem; }
.filter-field {
  min-width: 150px;
  flex: 1;
  max-width: 220px;
}

.filter-search {
  min-width: 220px;
  max-width: 280px;
}

.filter-date {
  min-width: 140px;
  max-width: 170px;
}

.search { width: min(100%, 22rem); }

.state {
  min-height: 14rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  text-align: center;
  color: var(--qm-text-muted);
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--qm-border);
  border-radius: 12px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 0.95rem 1.15rem;
  text-align: left;
  vertical-align: top;
}

.data-table thead tr { background: var(--qm-bg-secondary); }
.data-table th {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--qm-text-muted);
}

.data-table tbody tr { border-top: 1px solid var(--qm-border); }
.clickable { cursor: pointer; transition: background 0.2s ease; }
.clickable:hover { background: var(--qm-bg-secondary); }

.user-cell {
  display: flex;
  gap: 0.8rem;
  align-items: center;
}

.avatar {
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 0.9rem;
  overflow: hidden;
  background: var(--qm-brand);
  color: var(--qm-brand-contrast);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex-shrink: 0;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  font-size: 0.82rem;
}

.meta-top {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
}

.email-text {
  display: inline-block;
  font-size: 0.875rem;
}

.plan-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.plan-name {
  font-weight: 600;
  color: var(--qm-text-primary);
}

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

.token-section { margin-bottom: 1rem; }

.token-header {
  margin-bottom: 0.75rem;

  h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.generate-token-area {
  padding: 1.5rem;
  background: var(--qm-bg-secondary);
  border-radius: 10px;
  text-align: center;
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
  margin: 0.5rem 0 0;
}

.dialog-card { width: min(100%, 38rem); border-radius: 18px; }
.dialog-card.wide { width: min(100%, 52rem); }
.payload { margin: 0; padding: 1rem; background: var(--qm-bg-secondary); border: 1px solid var(--qm-border); border-radius: 14px; font-size: 0.8rem; white-space: pre-wrap; word-break: break-word; overflow-x: auto; }

@media (max-width: 768px) {
  .admin-page { padding-inline: 1rem; }
  .panel-head, .toolbar, .filters, .pager { flex-direction: column; }
  .filter-field, .filter-search, .filter-date {
    min-width: 100%;
    max-width: 100%;
  }
  .search { width: 100%; }
  .data-table th, .data-table td { padding: 0.8rem 0.9rem; }
}
</style>
