<template>
  <q-page class="appointments-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Agendamentos</h1>
        <p class="page-subtitle">Gerencie todos os agendamentos</p>
      </div>
      <div class="header-right">
        <q-btn
          color="primary"
          icon="add"
          label="Novo Agendamento"
          no-caps
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Filters Card -->
    <div class="filters-card soft-card">
      <div class="filters-row">
        <q-select
          v-model="filters.status"
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
          v-model="filters.date"
          outlined
          dense
          type="date"
          label="Data"
          class="filter-date"
        />
        <q-select
          v-model="filters.establishment_id"
          outlined
          dense
          label="Estabelecimento"
          :options="establishmentOptions"
          emit-value
          map-options
          clearable
          class="filter-select"
        />
        <q-btn flat color="primary" label="Filtrar" no-caps @click="fetchAppointments" />
        <q-btn flat label="Limpar" no-caps @click="clearFilters" />
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card soft-card">
      <div class="table-header">
        <h2 class="table-title">Lista de Agendamentos</h2>
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

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <q-spinner-dots color="primary" size="40px" />
        <p>Carregando agendamentos...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredAppointments.length === 0" class="empty-state">
        <q-icon name="event" size="64px" />
        <h3>Nenhum agendamento encontrado</h3>
        <p v-if="searchQuery || hasActiveFilters">Tente ajustar seus filtros</p>
        <p v-else>Comece criando um novo agendamento</p>
      </div>

      <!-- Table -->
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="th-patient">Paciente</th>
              <th class="th-professional">Profissional</th>
              <th class="th-service">Serviço</th>
              <th class="th-datetime">Data/Hora</th>
              <th class="th-status">Status</th>
              <th class="th-actions"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="appointment in filteredAppointments" :key="appointment.id" class="clickable-row" @click="router.push(`/app/appointments/${appointment.id}`)">
              <td>
                <div class="patient-info">
                  <q-avatar size="32px" class="patient-avatar">
                    <q-icon name="person" size="16px" />
                  </q-avatar>
                  <div class="patient-details">
                    <span class="patient-name">{{ appointment.user_name || 'Usuário #' + appointment.user_id }}</span>
                    <span class="patient-id">ID: {{ appointment.user_id }}</span>
                  </div>
                </div>
              </td>
              <td>
                <div class="professional-info">
                  <span class="professional-name">{{ appointment.professional_name || 'Profissional #' + appointment.professional_id }}</span>
                  <span class="professional-specialty">{{ appointment.specialization || '-' }}</span>
                </div>
              </td>
              <td>
                <span class="service-name">{{ appointment.service_name || 'Serviço #' + appointment.service_id }}</span>
              </td>
              <td>
                <div class="datetime-info">
                  <span class="datetime-date">{{ formatDate(appointment.start_at) }}</span>
                  <span class="datetime-time">{{ formatTime(appointment.start_at) }} - {{ formatTime(appointment.end_at) }}</span>
                </div>
              </td>
              <td>
                <q-badge :color="getStatusColor(appointment.status)" class="status-badge">
                  {{ getStatusLabel(appointment.status) }}
                </q-badge>
              </td>
              <td>
                <div class="row-actions">
                  <q-btn-dropdown
                    v-if="canChangeStatus(appointment)"
                    flat
                    round
                    dense
                    icon="more_vert"
                    size="sm"
                    @click.stop
                  >
                    <q-list dense>
                      <q-item clickable v-close-popup @click.stop="updateStatus(appointment, 'checked_in')" v-if="appointment.status === 'booked'">
                        <q-item-section>Check-in</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click.stop="updateStatus(appointment, 'in_progress')" v-if="appointment.status === 'checked_in'">
                        <q-item-section>Iniciar</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click.stop="updateStatus(appointment, 'completed')" v-if="appointment.status === 'in_progress'">
                        <q-item-section>Concluir</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click.stop="updateStatus(appointment, 'no_show')" v-if="appointment.status === 'booked'">
                        <q-item-section>Não compareceu</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click.stop="updateStatus(appointment, 'cancelled')" v-if="['booked', 'checked_in'].includes(appointment.status)">
                        <q-item-section class="text-negative">Cancelar</q-item-section>
                      </q-item>
                    </q-list>
                  </q-btn-dropdown>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total_pages > 1" class="table-pagination">
        <q-pagination
          v-model="pagination.page"
          :max="pagination.total_pages"
          direction-links
          boundary-links
          @update:model-value="fetchAppointments"
        />
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card dialog-large">
        <q-card-section class="dialog-header">
          <h3>{{ isEditing ? 'Editar Agendamento' : 'Novo Agendamento' }}</h3>
          <q-btn flat round dense icon="close" @click="closeDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <div class="form-grid">
            <q-select
              v-model="form.establishment_id"
              label="Estabelecimento *"
              outlined
              dense
              :options="establishmentOptions"
              emit-value
              map-options
              @update:model-value="onEstablishmentChange"
            />
            <q-select
              v-model="form.professional_id"
              label="Profissional *"
              outlined
              dense
              :options="professionalOptions"
              emit-value
              map-options
              :disable="!form.establishment_id"
            />
            <q-select
              v-model="form.service_id"
              label="Serviço *"
              outlined
              dense
              :options="serviceOptions"
              emit-value
              map-options
              :disable="!form.establishment_id"
            />
            <q-input
              v-model="form.start_at"
              label="Data e Hora *"
              outlined
              dense
              type="datetime-local"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeDialog" />
          <q-btn 
            color="primary" 
            :label="isEditing ? 'Salvar' : 'Agendar'" 
            no-caps 
            :loading="saving"
            @click="saveAppointment" 
          />
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
  name: 'AppointmentsPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    // State
    const appointments = ref([])
    const establishments = ref([])
    const professionals = ref([])
    const services = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const searchQuery = ref('')
    const userRole = ref(null)
    const userId = ref(null)

    // Filters
    const filters = ref({
      status: null,
      date: null,
      establishment_id: null
    })

    // Pagination
    const pagination = ref({
      page: 1,
      per_page: 20,
      total: 0,
      total_pages: 1
    })

    // Dialogs
    const showDialog = ref(false)
    const isEditing = ref(false)
    const selectedAppointment = ref(null)

    // Form
    const form = ref({
      establishment_id: null,
      professional_id: null,
      service_id: null,
      start_at: ''
    })

    // Options
    const statusOptions = [
      { label: 'Agendado', value: 'booked' },
      { label: 'Check-in', value: 'checked_in' },
      { label: 'Em andamento', value: 'in_progress' },
      { label: 'Conclué­do', value: 'completed' },
      { label: 'Não compareceu', value: 'no_show' },
      { label: 'Cancelado', value: 'cancelled' }
    ]

    // Computed
    const canManage = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))

    const establishmentOptions = computed(() => 
      establishments.value.map(e => ({ label: e.name, value: e.id }))
    )

    const professionalOptions = computed(() => 
      professionals.value
        .filter(p => !form.value.establishment_id || p.establishment_id === form.value.establishment_id)
        .map(p => ({ label: p.name, value: p.id }))
    )

    const serviceOptions = computed(() => 
      services.value
        .filter(s => !form.value.establishment_id || s.establishment_id === form.value.establishment_id)
        .map(s => ({ label: s.name, value: s.id }))
    )

    const hasActiveFilters = computed(() => 
      filters.value.status || filters.value.date || filters.value.establishment_id
    )

    const filteredAppointments = computed(() => {
      if (!searchQuery.value) return appointments.value
      const query = searchQuery.value.toLowerCase()
      return appointments.value.filter(a => 
        (a.user_name && a.user_name.toLowerCase().includes(query)) ||
        (a.professional_name && a.professional_name.toLowerCase().includes(query)) ||
        (a.service_name && a.service_name.toLowerCase().includes(query))
      )
    })

    // Methods
    const fetchAppointments = async () => {
      loading.value = true
      try {
        const params = {
          page: pagination.value.page,
          per_page: pagination.value.per_page
        }
        
        if (filters.value.status) params.status = filters.value.status
        if (filters.value.date) params.date = filters.value.date
        if (filters.value.establishment_id) params.establishment_id = filters.value.establishment_id

        const response = await api.get('/appointments', { params })
        if (response.data?.success) {
          appointments.value = response.data.data || []
          if (response.data.pagination) {
            pagination.value = { ...pagination.value, ...response.data.pagination }
          }
        }
      } catch (err) {
        console.error('Erro ao buscar agendamentos:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar agendamentos' })
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

    const fetchUserInfo = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          userRole.value = response.data.data.user.role
          userId.value = response.data.data.user.id
        }
      } catch (err) {
        console.error('Erro ao buscar usué¡rio:', err)
      }
    }

    const clearFilters = () => {
      filters.value = { status: null, date: null, establishment_id: null }
      fetchAppointments()
    }

    const openCreateDialog = () => {
      isEditing.value = false
      form.value = {
        establishment_id: null,
        professional_id: null,
        service_id: null,
        start_at: ''
      }
      showDialog.value = true
    }

    const editAppointment = (appointment) => {
      isEditing.value = true
      selectedAppointment.value = appointment
      form.value = {
        establishment_id: appointment.establishment_id,
        professional_id: appointment.professional_id,
        service_id: appointment.service_id,
        start_at: appointment.start_at?.replace(' ', 'T').slice(0, 16) || ''
      }
      showDialog.value = true
    }

    const closeDialog = () => {
      showDialog.value = false
    }

    const onEstablishmentChange = () => {
      form.value.professional_id = null
      form.value.service_id = null
    }

    const saveAppointment = async () => {
      if (!form.value.establishment_id || !form.value.professional_id || !form.value.service_id || !form.value.start_at) {
        $q.notify({ type: 'warning', message: 'Preencha todos os campos obrigatórios' })
        return
      }

      saving.value = true
      try {
        const payload = {
          ...form.value,
          start_at: form.value.start_at.replace('T', ' ') + ':00'
        }

        if (isEditing.value) {
          await api.put(`/appointments/${selectedAppointment.value.id}`, payload)
          $q.notify({ type: 'positive', message: 'Agendamento atualizado com sucesso' })
        } else {
          await api.post('/appointments', payload)
          $q.notify({ type: 'positive', message: 'Agendamento criado com sucesso' })
        }
        closeDialog()
        fetchAppointments()
      } catch (err) {
        console.error('Erro ao salvar:', err)
        const message = err.response?.data?.error?.message || 'Erro ao salvar agendamento'
        $q.notify({ type: 'negative', message })
      } finally {
        saving.value = false
      }
    }

    const updateStatus = async (appointment, status) => {
      try {
        await api.put(`/appointments/${appointment.id}`, { status })
        $q.notify({ type: 'positive', message: 'Status atualizado com sucesso' })
        fetchAppointments()
      } catch (err) {
        console.error('Erro ao atualizar status:', err)
        $q.notify({ type: 'negative', message: 'Erro ao atualizar status' })
      }
    }

    const canEdit = (appointment) => {
      if (canManage.value) return true
      return appointment.user_id === userId.value && appointment.status === 'booked'
    }

    const canChangeStatus = (appointment) => {
      return canManage.value && !['completed', 'cancelled', 'no_show'].includes(appointment.status)
    }

    const getStatusColor = (status) => {
      const colors = {
        booked: 'blue',
        checked_in: 'orange',
        in_progress: 'purple',
        completed: 'positive',
        no_show: 'deep-orange',
        cancelled: 'negative'
      }
      return colors[status] || 'grey'
    }

    const getStatusLabel = (status) => {
      const labels = {
        booked: 'Agendado',
        checked_in: 'Check-in',
        in_progress: 'Em andamento',
        completed: 'Conclué­do',
        no_show: 'Não compareceu',
        cancelled: 'Cancelado'
      }
      return labels[status] || status
    }

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('pt-BR')
    }

    const formatTime = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    }

    const formatDateTime = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleString('pt-BR')
    }

    // Lifecycle
    onMounted(async () => {
      await fetchUserInfo()
      await Promise.all([
        fetchEstablishments(),
        fetchProfessionals(),
        fetchServices()
      ])
      fetchAppointments()
    })

    return {
      appointments,
      loading,
      saving,
      searchQuery,
      filters,
      pagination,
      showDialog,
      isEditing,
      selectedAppointment,
      form,
      statusOptions,
      establishmentOptions,
      professionalOptions,
      serviceOptions,
      hasActiveFilters,
      filteredAppointments,
      clearFilters,
      openCreateDialog,
      editAppointment,
      closeDialog,
      onEstablishmentChange,
      saveAppointment,
      updateStatus,
      canEdit,
      canChangeStatus,
      getStatusColor,
      getStatusLabel,
      formatDate,
      formatTime,
      formatDateTime,
      fetchAppointments,
      router
    }
  }
})
</script>

<style lang="scss" scoped>
.appointments-page {
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

// Filters Card
.filters-card {
  padding: 1rem 1.25rem;
  margin-bottom: 1rem;
}

.filters-row {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: center;
}

.filter-select {
  min-width: 180px;
}

.filter-date {
  min-width: 150px;
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
  width: 250px;

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

.th-patient { min-width: 180px; }
.th-professional { min-width: 160px; }
.th-service { min-width: 140px; }
.th-datetime { min-width: 140px; }
.th-status { min-width: 120px; }
.th-actions { width: 100px; }

// Patient Info Cell
.patient-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.patient-avatar {
  background: var(--qm-brand-light);
  color: var(--qm-brand);
}

.patient-details {
  display: flex;
  flex-direction: column;
}

.patient-name {
  font-weight: 600;
  font-size: 0.875rem;
}

.patient-id {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

// Professional Info
.professional-info {
  display: flex;
  flex-direction: column;
}

.professional-name {
  font-weight: 500;
  font-size: 0.875rem;
}

.professional-specialty {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.service-name {
  font-size: 0.8125rem;
}

// DateTime Info
.datetime-info {
  display: flex;
  flex-direction: column;
}

.datetime-date {
  font-weight: 500;
  font-size: 0.875rem;
}

.datetime-time {
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

// Pagination
.table-pagination {
  display: flex;
  justify-content: center;
  padding: 1rem;
  border-top: 1px solid var(--qm-border);
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;

  @media (max-width: 500px) {
    grid-template-columns: 1fr;
  }
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
