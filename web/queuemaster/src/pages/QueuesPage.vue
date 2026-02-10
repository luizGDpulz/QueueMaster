<template>
  <q-page class="queues-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Filas</h1>
        <p class="page-subtitle">Gerencie e monitore todas as filas de atendimento</p>
      </div>
      <div class="header-right">
        <q-btn
          v-if="isAdmin"
          color="primary"
          icon="add"
          label="Nova Fila"
          no-caps
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
      <div class="stat-card soft-card">
        <div class="stat-icon open">
          <q-icon name="lock_open" size="24px" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.open }}</span>
          <span class="stat-label">Filas Abertas</span>
        </div>
      </div>
      <div class="stat-card soft-card">
        <div class="stat-icon closed">
          <q-icon name="lock" size="24px" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.closed }}</span>
          <span class="stat-label">Filas Fechadas</span>
        </div>
      </div>
      <div class="stat-card soft-card">
        <div class="stat-icon waiting">
          <q-icon name="groups" size="24px" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.totalWaiting }}</span>
          <span class="stat-label">Total Aguardando</span>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card soft-card">
      <div class="table-header">
        <h2 class="table-title">Lista de Filas</h2>
        <div class="table-actions">
          <q-select
            v-model="filterStatus"
            outlined
            dense
            label="Status"
            :options="statusOptions"
            emit-value
            map-options
            clearable
            class="filter-select"
          />
          <q-input
            v-model="searchQuery"
            outlined
            dense
            placeholder="Buscar..."
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
        <p>Carregando filas...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredQueues.length === 0" class="empty-state">
        <q-icon name="format_list_numbered" size="64px" />
        <h3>Nenhuma fila encontrada</h3>
        <p v-if="searchQuery || filterStatus">Tente ajustar seus filtros</p>
        <p v-else>Comece criando uma nova fila</p>
      </div>

      <!-- Table -->
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="th-queue">Fila</th>
              <th class="th-establishment">Estabelecimento</th>
              <th class="th-service">Serviço</th>
              <th class="th-waiting">Aguardando</th>
              <th class="th-status">Status</th>
              <th class="th-actions"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="queue in filteredQueues" :key="queue.id" class="clickable-row" @click="router.push(`/app/queues/${queue.id}`)">
              <td>
                <div class="queue-info">
                  <div class="queue-avatar" :class="queue.status">
                    <q-icon name="format_list_numbered" size="20px" />
                  </div>
                  <div class="queue-details">
                    <span class="queue-name">{{ queue.name }}</span>
                    <span class="queue-id">ID: {{ queue.id }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="establishment-name">{{ queue.establishment_name || 'N/A' }}</span>
              </td>
              <td>
                <span class="service-name">{{ queue.service_name || 'Geral' }}</span>
              </td>
              <td>
                <div class="waiting-info">
                  <span class="waiting-count">{{ queue.waiting_count || 0 }}</span>
                  <span class="waiting-label">pessoas</span>
                </div>
              </td>
              <td>
                <q-badge 
                  :color="queue.status === 'open' ? 'positive' : 'grey'" 
                  class="status-badge"
                >
                  {{ queue.status === 'open' ? 'Aberta' : 'Fechada' }}
                </q-badge>
              </td>
              <td>
                <div class="row-actions">
                  <q-btn 
                    v-if="queue.status === 'open'" 
                    flat round dense icon="person_add" size="sm" color="primary"
                    @click.stop="joinQueue(queue)"
                  >
                    <q-tooltip>Entrar na fila</q-tooltip>
                  </q-btn>
                  <q-btn 
                    v-if="canManage && queue.status === 'open' && queue.waiting_count > 0" 
                    flat round dense icon="campaign" size="sm" color="warning"
                    @click.stop="callNext(queue)"
                  >
                    <q-tooltip>Chamar próximo</q-tooltip>
                  </q-btn>
                  <q-btn v-if="isAdmin" flat round dense icon="delete" size="sm" color="negative" @click.stop="confirmDelete(queue)">
                    <q-tooltip>Excluir</q-tooltip>
                  </q-btn>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>{{ isEditing ? 'Editar Fila' : 'Nova Fila' }}</h3>
          <q-btn flat round dense icon="close" @click="closeDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-input
            v-model="form.name"
            label="Nome da Fila *"
            outlined
            dense
            :rules="[val => !!val || 'Nome é© obrigatório']"
          />
          <q-select
            v-model="form.establishment_id"
            label="Estabelecimento *"
            outlined
            dense
            :options="establishmentOptions"
            emit-value
            map-options
            class="q-mt-md"
            @update:model-value="onEstablishmentChange"
          />
          <q-select
            v-model="form.service_id"
            label="Serviço (opcional)"
            outlined
            dense
            :options="serviceOptions"
            emit-value
            map-options
            clearable
            class="q-mt-md"
            :disable="!form.establishment_id"
          />
          <q-select
            v-model="form.status"
            label="Status *"
            outlined
            dense
            :options="statusOptions"
            emit-value
            map-options
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeDialog" />
          <q-btn 
            color="primary" 
            :label="isEditing ? 'Salvar' : 'Criar'" 
            no-caps 
            :loading="saving"
            @click="saveQueue" 
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete Confirmation Dialog -->
    <q-dialog v-model="showDeleteDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Confirmar Exclusão</h3>
        </q-card-section>

        <q-card-section class="dialog-content">
          <p>Tem certeza que deseja excluir a fila <strong>{{ selectedQueue?.name }}</strong>?</p>
          <p class="delete-warning">Esta ação né£o pode ser desfeita e todas as entradas serão perdidas.</p>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteDialog = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteQueue" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Call Next Dialog -->
    <q-dialog v-model="showCallDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Chamar Próximo</h3>
          <q-btn flat round dense icon="close" @click="showCallDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-select
            v-model="callForm.professional_id"
            label="Profissional (opcional)"
            outlined
            dense
            :options="professionalOptions"
            emit-value
            map-options
            clearable
          />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showCallDialog = false" />
          <q-btn color="warning" icon="campaign" label="Chamar" no-caps :loading="calling" @click="executeCallNext" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'QueuesPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    // State
    const queues = ref([])
    const establishments = ref([])
    const services = ref([])
    const professionals = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const deleting = ref(false)
    const calling = ref(false)
    const searchQuery = ref('')
    const filterStatus = ref(null)
    const userRole = ref(null)

    // Dialogs
    const showDialog = ref(false)
    const showDeleteDialog = ref(false)
    const showCallDialog = ref(false)
    const isEditing = ref(false)
    const selectedQueue = ref(null)

    // Form
    const form = ref({
      name: '',
      establishment_id: null,
      service_id: null,
      status: 'open'
    })

    const callForm = ref({
      professional_id: null
    })

    const statusOptions = [
      { label: 'Aberta', value: 'open' },
      { label: 'Fechada', value: 'closed' }
    ]

    // Computed
    const isAdmin = computed(() => userRole.value === 'admin')
    const canManage = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))

    const stats = computed(() => {
      const open = queues.value.filter(q => q.status === 'open').length
      const closed = queues.value.filter(q => q.status === 'closed').length
      const totalWaiting = queues.value.reduce((sum, q) => sum + (q.waiting_count || 0), 0)
      return { open, closed, totalWaiting }
    })

    const establishmentOptions = computed(() => 
      establishments.value.map(e => ({ label: e.name, value: e.id }))
    )

    const serviceOptions = computed(() => 
      services.value
        .filter(s => !form.value.establishment_id || s.establishment_id === form.value.establishment_id)
        .map(s => ({ label: s.name, value: s.id }))
    )

    const professionalOptions = computed(() => 
      professionals.value
        .filter(p => !selectedQueue.value?.establishment_id || p.establishment_id === selectedQueue.value.establishment_id)
        .map(p => ({ label: p.name, value: p.id }))
    )

    const filteredQueues = computed(() => {
      let result = queues.value

      if (filterStatus.value) {
        result = result.filter(q => q.status === filterStatus.value)
      }

      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        result = result.filter(q => 
          q.name.toLowerCase().includes(query) ||
          (q.establishment_name && q.establishment_name.toLowerCase().includes(query)) ||
          (q.service_name && q.service_name.toLowerCase().includes(query))
        )
      }

      return result
    })

    // Methods
    const fetchQueues = async () => {
      loading.value = true
      try {
        const response = await api.get('/queues')
        if (response.data?.success) {
          queues.value = response.data.data?.queues || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar filas:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar filas' })
      } finally {
        loading.value = false
      }
    }

    const fetchEstablishments = async () => {
      try {
        const response = await api.get('/establishments')
        if (response.data?.success) {
          establishments.value = response.data.data?.establishments || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar estabelecimentos:', err)
      }
    }

    const fetchServices = async () => {
      try {
        const response = await api.get('/services')
        if (response.data?.success) {
          services.value = response.data.data?.services || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar serviços:', err)
      }
    }

    const fetchProfessionals = async () => {
      try {
        const response = await api.get('/professionals')
        if (response.data?.success) {
          professionals.value = response.data.data?.professionals || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar profissionais:', err)
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          userRole.value = response.data.data.user.role
        }
      } catch (err) {
        console.error('Erro ao buscar role:', err)
      }
    }

    const openCreateDialog = () => {
      isEditing.value = false
      form.value = { name: '', establishment_id: null, service_id: null, status: 'open' }
      showDialog.value = true
    }

    const editQueue = (queue) => {
      isEditing.value = true
      selectedQueue.value = queue
      form.value = {
        name: queue.name,
        establishment_id: queue.establishment_id,
        service_id: queue.service_id,
        status: queue.status
      }
      showDialog.value = true
    }

    const confirmDelete = (queue) => {
      selectedQueue.value = queue
      showDeleteDialog.value = true
    }

    const closeDialog = () => {
      showDialog.value = false
    }

    const onEstablishmentChange = () => {
      form.value.service_id = null
    }

    const saveQueue = async () => {
      if (!form.value.name || !form.value.establishment_id) {
        $q.notify({ type: 'warning', message: 'Nome e estabelecimento são obrigatórios' })
        return
      }

      saving.value = true
      try {
        if (isEditing.value) {
          await api.put(`/queues/${selectedQueue.value.id}`, form.value)
          $q.notify({ type: 'positive', message: 'Fila atualizada com sucesso' })
        } else {
          await api.post('/queues', form.value)
          $q.notify({ type: 'positive', message: 'Fila criada com sucesso' })
        }
        closeDialog()
        fetchQueues()
      } catch (err) {
        console.error('Erro ao salvar:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const deleteQueue = async () => {
      deleting.value = true
      try {
        await api.delete(`/queues/${selectedQueue.value.id}`)
        $q.notify({ type: 'positive', message: 'Fila exclué­da com sucesso' })
        showDeleteDialog.value = false
        fetchQueues()
      } catch (err) {
        console.error('Erro ao excluir:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally {
        deleting.value = false
      }
    }

    const joinQueue = async (queue) => {
      try {
        await api.post(`/queues/${queue.id}/join`)
        $q.notify({ type: 'positive', message: 'Você entrou na fila com sucesso!' })
        fetchQueues()
      } catch (err) {
        console.error('Erro ao entrar na fila:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao entrar na fila' })
      }
    }

    const callNext = (queue) => {
      selectedQueue.value = queue
      callForm.value = { professional_id: null }
      showCallDialog.value = true
    }

    const executeCallNext = async () => {
      calling.value = true
      try {
        const payload = {
          establishment_id: selectedQueue.value.establishment_id
        }
        if (callForm.value.professional_id) {
          payload.professional_id = callForm.value.professional_id
        }

        const response = await api.post(`/queues/${selectedQueue.value.id}/call-next`, payload)
        
        if (response.data?.success && response.data?.data?.called) {
          const called = response.data.data.called
          $q.notify({ 
            type: 'positive', 
            message: `Chamando: ${called.user_name || 'Usué¡rio #' + called.user_id}`,
            timeout: 5000
          })
        } else {
          $q.notify({ type: 'info', message: 'Próximo da fila foi chamado' })
        }
        
        showCallDialog.value = false
        fetchQueues()
      } catch (err) {
        console.error('Erro ao chamar próximo:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao chamar próximo' })
      } finally {
        calling.value = false
      }
    }

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleString('pt-BR')
    }

    // Lifecycle
    onMounted(async () => {
      await fetchUserRole()
      await Promise.all([
        fetchEstablishments(),
        fetchServices(),
        fetchProfessionals()
      ])
      fetchQueues()
    })

    return {
      queues,
      loading,
      saving,
      deleting,
      calling,
      searchQuery,
      filterStatus,
      showDialog,
      showDeleteDialog,
      showCallDialog,
      isEditing,
      selectedQueue,
      form,
      callForm,
      statusOptions,
      isAdmin,
      canManage,
      stats,
      establishmentOptions,
      serviceOptions,
      professionalOptions,
      filteredQueues,
      openCreateDialog,
      editQueue,
      confirmDelete,
      closeDialog,
      onEstablishmentChange,
      saveQueue,
      deleteQueue,
      joinQueue,
      callNext,
      executeCallNext,
      formatDate,
      router
    }
  }
})
</script>

<style lang="scss" scoped>
.queues-page {
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

// Stats Row
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  line-height: 1;
}

.stat-label {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
  margin-top: 0.25rem;
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

.table-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.filter-select {
  min-width: 140px;
}

.search-input {
  width: 200px;

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

    tr.clickable-row {
      cursor: pointer;
    }

    td {
      font-size: 0.875rem;
      color: var(--qm-text-primary);
    }
  }
}

.th-queue { min-width: 180px; }
.th-establishment { min-width: 160px; }
.th-service { min-width: 120px; }
.th-waiting { min-width: 100px; }
.th-status { min-width: 100px; }
.th-actions { width: 160px; }

// Queue Info Cell
.queue-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.queue-avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
}

.queue-details {
  display: flex;
  flex-direction: column;
}

.queue-name {
  font-weight: 600;
  font-size: 0.875rem;
}

.queue-id {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.establishment-name,
.service-name {
  font-size: 0.8125rem;
}

// Waiting Info
.waiting-info {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
}

.waiting-count {
  font-weight: 700;
  font-size: 1rem;
  color: var(--qm-brand);
}

.waiting-label {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.status-badge {
  font-size: 0.6875rem;
  padding: 0.25rem 0.625rem;
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

  :deep(.q-btn) {
    min-height: 36px;
  }

  :deep(.q-btn__content) {
    color: inherit;
  }
}

.dialog-large {
  max-width: 600px;
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

.delete-warning {
  color: var(--qm-error);
  font-size: 0.8125rem;
  margin-top: 0.5rem;
}

// Queue Status Section
.queue-status-section {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--qm-border);

  h4 {
    margin: 0 0 1rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;

  @media (max-width: 500px) {
    grid-template-columns: repeat(2, 1fr);
  }
}

.status-item {
  text-align: center;
  padding: 0.75rem;
  background: var(--qm-bg-secondary);
  border-radius: 8px;
}

.status-number {
  display: block;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--qm-brand);
}

.status-text {
  display: block;
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  margin-top: 0.25rem;
}

.user-position {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: var(--qm-brand-light);
  border-radius: 8px;
  color: var(--qm-brand);
  font-size: 0.875rem;
}

.wait-estimate {
  color: var(--qm-text-muted);
  font-size: 0.8125rem;
}
</style>
