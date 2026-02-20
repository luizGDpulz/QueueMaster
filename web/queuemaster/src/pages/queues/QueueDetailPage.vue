<template>
  <q-page class="detail-page">
    <!-- Header (aligned with QueuesPage pattern) -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <h1 class="page-title">{{ queue?.name || '\u00A0' }}</h1>
      </div>
      <div class="header-right">
        <template v-if="canManage && queue">
          <q-btn
            v-if="queue.status === 'open' && statistics?.total_waiting > 0"
            color="warning"
            icon="campaign"
            label="Chamar Próximo"
            no-caps
            :loading="callingNext"
            @click="callNext"
          />
          <q-btn
            color="info"
            icon="qr_code"
            label="Gerar Código"
            no-caps
            :loading="generatingCode"
            @click="generateCode"
          />
          <q-btn outline icon="edit" label="Editar" no-caps @click="openEdit" />
        </template>
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">
          {{ queue?.establishment_name || '' }}
          <template v-if="queue?.service_name"> · {{ queue.service_name }}</template>
          <template v-if="queue">
            · <q-badge
              :color="queue.status === 'open' ? 'positive' : 'grey'"
              class="status-badge-sm"
            >{{ queue.status === 'open' ? 'Aberta' : 'Fechada' }}</q-badge>
          </template>
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="queue">
      <!-- Regular user view (not staff) -->
      <template v-if="isRegularUser">
        <div class="soft-card q-mb-lg">
          <h2 class="section-title">Informações da Fila</h2>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Pessoas Aguardando</span>
              <span class="detail-value detail-value-lg">{{ statistics?.total_waiting || 0 }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Tempo Médio de Espera</span>
              <span class="detail-value">{{ statistics?.average_wait_time_minutes || 0 }} min</span>
            </div>
          </div>

          <!-- User position if in queue -->
          <div v-if="userEntry" class="highlight-box q-mt-md">
            <q-icon name="person" size="20px" />
            <span>Você está na posição <strong>{{ userEntry.position }}</strong></span>
            <span class="text-muted">(~{{ userEntry.estimated_wait_minutes || '?' }} min)</span>
          </div>
        </div>

        <!-- Join with code -->
        <div v-if="queue.status === 'open' && !userEntry" class="soft-card">
          <h2 class="section-title">Entrar na Fila</h2>
          <p class="text-muted q-mb-md">Insira o código de acesso fornecido pelo estabelecimento:</p>
          <div class="join-form">
            <q-input
              v-model="accessCode"
              outlined
              dense
              placeholder="Código de acesso"
              class="code-input"
              @keyup.enter="joinWithCode"
            >
              <template v-slot:prepend>
                <q-icon name="vpn_key" />
              </template>
            </q-input>
            <q-btn
              color="primary"
              label="Entrar"
              no-caps
              :loading="joining"
              @click="joinWithCode"
            />
          </div>
        </div>
      </template>

      <!-- Staff view (professional/manager/admin) -->
      <template v-else>
        <!-- Stats Row -->
        <div class="stats-row q-mb-lg">
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.total_waiting || 0 }}</span>
            <span class="stat-text">Aguardando</span>
          </div>
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.total_being_served || 0 }}</span>
            <span class="stat-text">Em atendimento</span>
          </div>
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.total_completed_today || 0 }}</span>
            <span class="stat-text">Concluídos hoje</span>
          </div>
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.average_wait_time_minutes || 0 }} min</span>
            <span class="stat-text">Tempo médio</span>
          </div>
        </div>

        <!-- Entries Tabs Card -->
        <div class="soft-card entries-card">
          <q-tabs
            v-model="activeTab"
            dense
            class="entries-tabs"
            active-color="primary"
            indicator-color="primary"
            align="left"
            narrow-indicator
          >
            <q-tab name="waiting" no-caps>
              <div class="tab-content">
                <span>Aguardando</span>
                <q-badge v-if="entries.length" color="warning" :label="entries.length" class="q-ml-sm" />
              </div>
            </q-tab>
            <q-tab name="serving" no-caps>
              <div class="tab-content">
                <span>Em Atendimento</span>
                <q-badge v-if="entriesServing.length" color="info" :label="entriesServing.length" class="q-ml-sm" />
              </div>
            </q-tab>
            <q-tab name="completed" no-caps>
              <div class="tab-content">
                <span>Concluídos</span>
                <q-badge v-if="entriesCompleted.length" color="positive" :label="entriesCompleted.length" class="q-ml-sm" />
              </div>
            </q-tab>
          </q-tabs>

          <q-separator />

          <q-tab-panels v-model="activeTab" animated class="tab-panels">
            <!-- Waiting Tab -->
            <q-tab-panel name="waiting" class="q-pa-none">
              <div v-if="entries.length === 0" class="empty-state-sm">
                <q-icon name="groups" size="48px" />
                <p>Nenhuma pessoa aguardando</p>
              </div>
              <div v-else class="list-items">
                <div v-for="(entry, index) in entries" :key="entry.id" class="list-item entry-item">
                  <div class="list-item-info">
                    <div class="entry-position">{{ index + 1 }}</div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ entry.user_name }}</span>
                      <span class="list-item-meta">
                        <q-icon name="schedule" size="12px" class="q-mr-xs" />
                        {{ formatWaitTime(entry.waiting_since_minutes) }}
                        <template v-if="entry.estimated_wait_minutes !== undefined">
                          · ~{{ entry.estimated_wait_minutes }} min p/ atendimento
                        </template>
                      </span>
                    </div>
                  </div>
                  <div class="list-item-side" v-if="canFullManage">
                    <q-btn v-if="index > 0" flat round dense icon="keyboard_arrow_up" size="sm" @click.stop="moveEntry(index, -1)" />
                    <q-btn v-if="index < entries.length - 1" flat round dense icon="keyboard_arrow_down" size="sm" @click.stop="moveEntry(index, 1)" />
                    <q-btn flat round dense icon="close" size="sm" color="negative" @click.stop="confirmRemoveEntry(entry)" />
                  </div>
                </div>
              </div>

              <!-- Add person button -->
              <div v-if="canFullManage" class="tab-footer">
                <q-btn flat icon="person_add" label="Adicionar Pessoa" no-caps color="primary" @click="showAddPersonDialog = true" />
              </div>
            </q-tab-panel>

            <!-- Serving Tab -->
            <q-tab-panel name="serving" class="q-pa-none">
              <div v-if="entriesServing.length === 0" class="empty-state-sm">
                <q-icon name="support_agent" size="48px" />
                <p>Nenhuma pessoa em atendimento</p>
              </div>
              <div v-else class="list-items">
                <div v-for="entry in entriesServing" :key="entry.id" class="list-item entry-item">
                  <div class="list-item-info">
                    <div class="entry-position serving">
                      <q-icon name="headset_mic" size="16px" />
                    </div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ entry.user_name }}</span>
                      <span class="list-item-meta">
                        <q-icon name="timer" size="12px" class="q-mr-xs" />
                        Em atendimento há {{ formatWaitTime(entry.serving_since_minutes) }}
                      </span>
                    </div>
                  </div>
                  <q-badge color="info" label="Atendendo" />
                </div>
              </div>
            </q-tab-panel>

            <!-- Completed Tab -->
            <q-tab-panel name="completed" class="q-pa-none">
              <div v-if="entriesCompleted.length === 0" class="empty-state-sm">
                <q-icon name="check_circle" size="48px" />
                <p>Nenhum atendimento concluído hoje</p>
              </div>
              <div v-else class="list-items">
                <div v-for="entry in entriesCompleted" :key="entry.id" class="list-item entry-item">
                  <div class="list-item-info">
                    <div class="entry-position completed">
                      <q-icon name="check" size="16px" />
                    </div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ entry.user_name }}</span>
                      <span class="list-item-meta">
                        <q-icon name="event" size="12px" class="q-mr-xs" />
                        Concluído {{ formatDate(entry.completed_at) }}
                      </span>
                    </div>
                  </div>
                  <q-badge color="positive" label="Concluído" />
                </div>
              </div>
            </q-tab-panel>
          </q-tab-panels>
        </div>
      </template>
    </template>

    <!-- Edit Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Fila</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" label="Nome da Fila *" outlined dense />
          <q-select v-model="editForm.status" label="Status" outlined dense :options="statusOptions" emit-value map-options class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveQueue" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Remove Entry Confirm -->
    <q-dialog v-model="showRemoveEntryConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Remover da Fila</div>
        </q-card-section>
        <q-card-section>
          <p>Remover <strong>{{ selectedEntry?.user_name }}</strong> da fila?</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showRemoveEntryConfirm = false" />
          <q-btn color="negative" label="Remover" no-caps :loading="removingEntry" @click="removeEntry" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Person Dialog -->
    <q-dialog v-model="showAddPersonDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Adicionar Pessoa à Fila</div>
          <q-btn flat round dense icon="close" @click="showAddPersonDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="addPersonForm.userId"
            label="Selecionar Usuário"
            outlined
            dense
            use-input
            input-debounce="300"
            :options="filteredUsers"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            @filter="filterUsers"
            class="q-mb-md"
          >
            <template v-slot:no-option>
              <q-item>
                <q-item-section class="text-grey">Nenhum usuário encontrado</q-item-section>
              </q-item>
            </template>
          </q-select>
          <q-input v-model.number="addPersonForm.priority" label="Prioridade (0 = normal)" outlined dense type="number" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showAddPersonDialog = false" />
          <q-btn color="primary" label="Adicionar" no-caps :loading="addingPerson" @click="addPerson" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Access Code Dialog -->
    <q-dialog v-model="showCodeDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Código de Acesso</div>
          <q-btn flat round dense icon="close" @click="showCodeDialog = false" />
        </q-card-section>
        <q-card-section class="text-center" style="padding: 2rem;">
          <p class="text-muted q-mb-md" style="font-size: 0.875rem;">Compartilhe este código para que clientes entrem na fila:</p>
          <div class="access-code-display">
            <span class="access-code-text">{{ generatedCode }}</span>
          </div>
          <q-btn
            outline
            color="primary"
            icon="content_copy"
            label="Copiar Código"
            no-caps
            class="q-mt-md"
            @click="copyCode"
          />
          <p v-if="codeExpiresAt" class="text-muted q-mt-md" style="font-size: 0.75rem;">Expira em: {{ codeExpiresAt }}</p>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'QueueDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const queue = ref(null)
    const entries = ref([])
    const entriesServing = ref([])
    const entriesCompleted = ref([])
    const statistics = ref(null)
    const userEntry = ref(null)
    const loading = ref(true)
    const saving = ref(false)
    const callingNext = ref(false)
    const generatingCode = ref(false)
    const removingEntry = ref(false)
    const addingPerson = ref(false)
    const joining = ref(false)
    const userRole = ref(null)
    const activeTab = ref('waiting')
    const accessCode = ref('')

    const showEditDialog = ref(false)
    const showRemoveEntryConfirm = ref(false)
    const showAddPersonDialog = ref(false)
    const showCodeDialog = ref(false)
    const selectedEntry = ref(null)
    const generatedCode = ref('')
    const codeExpiresAt = ref('')

    const editForm = ref({ name: '', status: 'open' })
    const addPersonForm = ref({ userId: null, priority: 0 })
    const allUsers = ref([])
    const filteredUsers = ref([])

    const statusOptions = [
      { label: 'Aberta', value: 'open' },
      { label: 'Fechada', value: 'closed' }
    ]

    const isRegularUser = computed(() => !userRole.value || userRole.value === 'user' || userRole.value === 'client')
    const canManage = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))
    const canFullManage = computed(() => ['admin', 'manager'].includes(userRole.value))

    const goBack = () => router.push('/app/queues')

    let refreshInterval = null

    const fetchData = async () => {
      try {
        const response = await api.get(`/queues/${route.params.id}/status`)
        if (response.data?.success) {
          const data = response.data.data
          queue.value = data.queue || null
          entries.value = data.entries || []
          entriesServing.value = data.entries_serving || []
          entriesCompleted.value = data.entries_completed || []
          statistics.value = data.statistics || null
          userEntry.value = data.user_entry || null
        }
      } catch (err) {
        console.error('Erro ao buscar status da fila:', err)
        try {
          const res = await api.get(`/queues/${route.params.id}`)
          if (res.data?.success) {
            queue.value = res.data.data.queue || null
          }
        } catch {
          $q.notify({ type: 'negative', message: 'Erro ao carregar fila' })
          goBack()
          return
        }
      } finally {
        loading.value = false
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          userRole.value = response.data.data.user.role
        }
      } catch { /* ignore */ }
    }

    const joinWithCode = async () => {
      if (!accessCode.value.trim()) {
        $q.notify({ type: 'warning', message: 'Insira o código de acesso' })
        return
      }
      joining.value = true
      try {
        await api.post(`/queues/${route.params.id}/join`, { access_code: accessCode.value.trim() })
        $q.notify({ type: 'positive', message: 'Você entrou na fila com sucesso!' })
        accessCode.value = ''
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Código inválido ou erro ao entrar na fila' })
      } finally {
        joining.value = false
      }
    }

    const callNext = async () => {
      callingNext.value = true
      try {
        const payload = { establishment_id: queue.value?.establishment_id }
        const response = await api.post(`/queues/${route.params.id}/call-next`, payload)
        if (response.data?.success && response.data?.data?.called) {
          const called = response.data.data.called
          $q.notify({ type: 'positive', message: `Chamando: ${called.user_name || called.guest_name || 'Próximo'}`, timeout: 5000 })
        } else {
          $q.notify({ type: 'info', message: response.data?.data?.message || 'Fila vazia' })
        }
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao chamar próximo' })
      } finally {
        callingNext.value = false
      }
    }

    const openEdit = () => {
      editForm.value = { name: queue.value?.name || '', status: queue.value?.status || 'open' }
      showEditDialog.value = true
    }

    const saveQueue = async () => {
      if (!editForm.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }
      saving.value = true
      try {
        await api.put(`/queues/${route.params.id}`, editForm.value)
        $q.notify({ type: 'positive', message: 'Fila atualizada com sucesso' })
        showEditDialog.value = false
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const generateCode = async () => {
      generatingCode.value = true
      try {
        const response = await api.post(`/queues/${route.params.id}/generate-code`)
        if (response.data?.success) {
          const data = response.data.data
          const codeObj = data?.access_code || data
          generatedCode.value = codeObj?.code || codeObj?.access_code || ''
          codeExpiresAt.value = codeObj?.expires_at ? new Date(codeObj.expires_at).toLocaleString('pt-BR') : ''
          showCodeDialog.value = true
        }
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao gerar código' })
      } finally {
        generatingCode.value = false
      }
    }

    const copyCode = () => {
      navigator.clipboard.writeText(generatedCode.value)
        .then(() => $q.notify({ type: 'positive', message: 'Código copiado!', timeout: 2000 }))
        .catch(() => $q.notify({ type: 'warning', message: 'Não foi possível copiar' }))
    }

    const confirmRemoveEntry = (entry) => {
      selectedEntry.value = entry
      showRemoveEntryConfirm.value = true
    }

    const removeEntry = async () => {
      removingEntry.value = true
      try {
        await api.post(`/queues/${selectedEntry.value.id}/leave`)
        $q.notify({ type: 'positive', message: 'Pessoa removida da fila' })
        showRemoveEntryConfirm.value = false
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao remover' })
      } finally {
        removingEntry.value = false
      }
    }

    const moveEntry = (currentIndex, direction) => {
      const newIndex = currentIndex + direction
      if (newIndex < 0 || newIndex >= entries.value.length) return
      const temp = entries.value[currentIndex]
      entries.value[currentIndex] = entries.value[newIndex]
      entries.value[newIndex] = temp
      entries.value = [...entries.value]
    }

    const fetchUsers = async () => {
      try {
        const response = await api.get('/users')
        if (response.data?.success) {
          allUsers.value = response.data.data?.users || response.data.data || []
        }
      } catch { /* ignore */ }
    }

    const filterUsers = (val, update) => {
      update(() => {
        const needle = val.toLowerCase()
        filteredUsers.value = allUsers.value.filter(u =>
          (u.name || '').toLowerCase().includes(needle) ||
          (u.email || '').toLowerCase().includes(needle)
        )
      })
    }

    const addPerson = async () => {
      if (!addPersonForm.value.userId) {
        $q.notify({ type: 'warning', message: 'Selecione um usuário' })
        return
      }
      addingPerson.value = true
      try {
        await api.post(`/queues/${route.params.id}/join`, { priority: addPersonForm.value.priority || 0 })
        $q.notify({ type: 'positive', message: 'Pessoa adicionada à fila' })
        showAddPersonDialog.value = false
        addPersonForm.value = { userId: null, priority: 0 }
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao adicionar' })
      } finally {
        addingPerson.value = false
      }
    }

    const formatDate = (d) => d ? new Date(d).toLocaleString('pt-BR') : '-'

    const formatWaitTime = (minutes) => {
      if (!minutes || minutes <= 0) return 'menos de 1 min'
      if (minutes < 60) return `${minutes} min`
      const h = Math.floor(minutes / 60)
      const m = minutes % 60
      return m > 0 ? `${h}h ${m}min` : `${h}h`
    }

    onMounted(async () => {
      await fetchUserRole()
      await fetchData()
      if (canManage.value) fetchUsers()
      refreshInterval = setInterval(fetchData, 30000)
    })

    onUnmounted(() => {
      if (refreshInterval) clearInterval(refreshInterval)
    })

    return {
      queue, entries, entriesServing, entriesCompleted, statistics, userEntry,
      loading, saving, callingNext, generatingCode, removingEntry, addingPerson, joining,
      canManage, canFullManage, isRegularUser, userRole, activeTab, accessCode,
      showEditDialog, showRemoveEntryConfirm, showAddPersonDialog, showCodeDialog,
      selectedEntry, generatedCode, codeExpiresAt,
      editForm, addPersonForm, statusOptions, filteredUsers,
      goBack, joinWithCode, callNext, openEdit, saveQueue,
      generateCode, copyCode, confirmRemoveEntry, removeEntry, moveEntry,
      addPerson, filterUsers, formatDate, formatWaitTime
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

// Status badge (specific to queue detail)
.status-badge-sm {
  font-size: 0.625rem;
  padding: 2px 6px;
  vertical-align: middle;
}

// Stats
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}

.stat-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1.25rem;
  text-align: center;
}

.stat-number {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  line-height: 1;
}

.stat-text {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
  margin-top: 0.25rem;
}

// Entries card
.entries-card {
  padding: 0;
  overflow: hidden;
}

.entries-tabs {
  padding: 0 0.5rem;
}

.tab-content {
  display: flex;
  align-items: center;
}

.tab-panels {
  background: transparent;
  min-height: 200px;
}

.tab-footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--qm-border);
}

// List items inside tabs
.list-items {
  padding: 0;
}

.list-item {
  padding: 0.875rem 1.25rem;
}

.entry-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: background 0.15s;

  &:hover {
    background: var(--qm-bg-secondary);
  }
}

.list-item-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}

.list-item-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.list-item-name {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--qm-text-primary);
}

.list-item-meta {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  display: flex;
  align-items: center;
  gap: 2px;
}

.list-item-side {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.entry-position {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.875rem;
  flex-shrink: 0;

  &.serving {
    background: rgba(var(--q-info-rgb, 33, 150, 243), 0.12);
    color: var(--q-info, #2196f3);
  }

  &.completed {
    background: rgba(var(--q-positive-rgb, 76, 175, 80), 0.12);
    color: var(--q-positive, #4caf50);
  }
}

.empty-state-sm {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 3rem 2rem;
  color: var(--qm-text-muted);

  p {
    margin: 0.5rem 0 0;
    font-size: 0.875rem;
  }
}

// Join form
.join-form {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.code-input {
  flex: 1;
  max-width: 300px;
}

// Highlight box
.highlight-box {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  background: var(--qm-brand-light);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--qm-text-primary);
}

.text-muted {
  color: var(--qm-text-muted);
  font-size: 0.8125rem;
}

// Detail grid
.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
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
  letter-spacing: 0.3px;
}

.detail-value {
  font-size: 0.9375rem;
  color: var(--qm-text-primary);
  font-weight: 500;
}

.detail-value-lg {
  font-size: 1.75rem;
  font-weight: 700;
}

// Access code
.access-code-display {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1rem 2rem;
  background: var(--qm-bg-secondary);
  border-radius: 12px;
  border: 2px dashed var(--qm-brand);
}

.access-code-text {
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: 0.5rem;
  color: var(--qm-brand);
  font-family: monospace;
}
</style>
