<template>
  <q-page class="dashboard-page">
    
    <!-- Título da Página -->
    <div class="page-header">
      <h1 class="page-title">Dashboard</h1>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando dashboard...</p>
    </div>

    <template v-else>
      <!-- Cards de Estatísticas -->
      <div class="stats-grid">
        <div class="stat-card soft-card" v-for="stat in stats" :key="stat.label">
          <div class="stat-content">
            <div class="stat-info">
              <span class="stat-label">{{ stat.label }}</span>
              <div class="stat-value-row">
                <span class="stat-value">{{ stat.value }}</span>
              </div>
            </div>
            <div class="stat-icon-wrapper">
              <q-icon :name="stat.icon" size="24px" />
            </div>
          </div>
        </div>
      </div>

      <!-- Grid Principal -->
      <div class="main-grid">
        
        <!-- Card Filas Ativas -->
        <div class="content-card soft-card">
          <div class="card-header">
            <h3 class="card-title">Filas Ativas</h3>
          </div>
          
          <div class="card-body">
            <div class="empty-state" v-if="!queues.length">
              <q-icon name="format_list_numbered" size="48px" class="empty-icon" />
              <p class="empty-text">Nenhuma fila ativa</p>
            </div>
            
            <div class="queue-list" v-else>
              <div class="queue-item" v-for="queue in queues" :key="queue.id">
                <div class="queue-info">
                  <span class="queue-name">{{ queue.name }}</span>
                  <span class="queue-count">{{ queue.waiting }} aguardando</span>
                </div>
                <div class="queue-status" :class="queue.status">
                  {{ queue.status === 'open' ? 'Aberta' : 'Fechada' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card Agendamentos do Dia -->
        <div class="content-card soft-card">
          <div class="card-header">
            <h3 class="card-title">Agendamentos de Hoje</h3>
          </div>
          
          <div class="card-body">
            <div class="empty-state" v-if="!appointments.length">
              <q-icon name="event" size="48px" class="empty-icon" />
              <p class="empty-text">Nenhum agendamento hoje</p>
            </div>
            
            <div class="appointment-list" v-else>
              <div class="appointment-item" v-for="apt in appointments" :key="apt.id">
                <div class="appointment-time">
                  <span class="time">{{ apt.time }}</span>
                </div>
                <div class="appointment-info">
                  <span class="appointment-client">{{ apt.client }}</span>
                  <span class="appointment-service">{{ apt.service }}</span>
                </div>
                <div class="appointment-status" :class="apt.status">
                  {{ getStatusLabel(apt.status) }}
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </template>

  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { api } from 'boot/axios'

export default defineComponent({
  name: 'DashboardPage',

  setup() {
    // ===== STATE =====
    const loading = ref(true)
    const queueOverview = ref({ queues: [], totals: { served_today: 0, no_show_today: 0, currently_waiting: 0 } })
    const appointmentsData = ref({ appointments: [], total: 0 })
    const userRole = ref(null)

    // ===== COMPUTED =====
    const canManage = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))

    const stats = computed(() => {
      const totals = queueOverview.value.totals
      const activeQueues = queueOverview.value.queues.filter(q => q.status === 'open').length
      return [
        { label: 'Atendidos Hoje', value: String(totals.served_today), icon: 'people' },
        { label: 'Na Fila', value: String(totals.currently_waiting), icon: 'format_list_numbered' },
        { label: 'Agendamentos', value: String(appointmentsData.value.total), icon: 'event' },
        { label: 'Filas Ativas', value: String(activeQueues), icon: 'queue' },
      ]
    })

    const queues = computed(() => {
      return queueOverview.value.queues
        .filter(q => q.status === 'open')
        .map(q => ({
          id: q.id,
          name: q.name,
          waiting: q.waiting_count || 0,
          status: q.status,
        }))
    })

    const appointments = computed(() => {
      return appointmentsData.value.appointments.map(a => ({
        id: a.id,
        time: formatTime(a.start_at),
        client: a.user_name || `Usuário #${a.user_id}`,
        service: a.service_name || '-',
        status: a.status,
      }))
    })

    // ===== STATUS LABELS =====
    const getStatusLabel = (status) => {
      const labels = {
        booked: 'Agendado',
        checked_in: 'Check-in',
        in_progress: 'Em andamento',
        completed: 'Concluído',
        no_show: 'Não compareceu',
        cancelled: 'Cancelado'
      }
      return labels[status] || status
    }

    const formatTime = (dateString) => {
      if (!dateString) return '-'
      const date = new Date(dateString)
      return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    }

    // ===== CARREGAR DADOS =====
    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          userRole.value = response.data.data.user.role
        }
      } catch {
        userRole.value = null
      }
    }

    const fetchQueueOverview = async () => {
      try {
        const response = await api.get('/dashboard/queue-overview')
        if (response.data?.success) {
          queueOverview.value = response.data.data
        }
      } catch (err) {
        console.error('Erro ao buscar overview de filas:', err)
      }
    }

    const fetchAppointments = async () => {
      try {
        const response = await api.get('/dashboard/appointments-list')
        if (response.data?.success) {
          appointmentsData.value = response.data.data
        }
      } catch (err) {
        console.error('Erro ao buscar agendamentos:', err)
      }
    }

    const loadDashboard = async () => {
      loading.value = true
      try {
        await fetchUserRole()

        if (canManage.value) {
          await Promise.all([fetchQueueOverview(), fetchAppointments()])
        } else {
          // Para clientes, buscar apenas dados de fila pública
          try {
            const response = await api.get('/queues')
            if (response.data?.success) {
              const allQueues = response.data.data?.queues || response.data.data || []
              queueOverview.value = {
                queues: allQueues.map(q => ({ ...q, waiting_count: q.waiting_count || 0 })),
                totals: {
                  served_today: 0,
                  no_show_today: 0,
                  currently_waiting: allQueues.reduce((sum, q) => sum + (q.waiting_count || 0), 0),
                }
              }
            }
          } catch {
            // silently fail
          }
          try {
            const response = await api.get('/appointments')
            if (response.data?.success) {
              const appts = response.data.data?.appointments || response.data.data || []
              appointmentsData.value = { appointments: appts, total: appts.length }
            }
          } catch {
            // silently fail
          }
        }
      } catch (error) {
        console.error('Erro ao carregar dashboard:', error)
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      loadDashboard()
    })

    return {
      loading,
      stats,
      queues,
      appointments,
      getStatusLabel
    }
  }
})
</script>

<style lang="scss" scoped>
// ===== DASHBOARD PAGE =====
.dashboard-page {
  padding: 0 1.5rem 1.5rem;
}

// ===== PAGE HEADER =====
.page-header {
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0;
}

// ===== LOADING STATE =====
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--qm-text-muted);
  text-align: center;

  p {
    margin: 1rem 0 0;
    font-size: 0.875rem;
  }
}

// ===== STATS GRID =====
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  margin-bottom: 1.5rem;

  @media (max-width: 1200px) {
    grid-template-columns: repeat(2, 1fr);
  }

  @media (max-width: 600px) {
    grid-template-columns: 1fr;
  }
}

.stat-card {
  padding: 1.25rem;
}

.stat-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.stat-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.stat-label {
  font-size: 0.875rem;
  color: var(--qm-text-secondary);
  font-weight: 500;
}

.stat-value-row {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  display: flex;
  align-items: center;
  justify-content: center;
}

// ===== MAIN GRID =====
.main-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
  }
}

// ===== CONTENT CARDS =====
.content-card {
  padding: 0;
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem;
  border-bottom: 1px solid var(--qm-border);
}

.card-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin: 0;
}

.card-body {
  padding: 1.25rem;
}

// ===== EMPTY STATE =====
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  text-align: center;
}

.empty-icon {
  color: var(--qm-text-disabled);
  margin-bottom: 1rem;
}

.empty-text {
  color: var(--qm-text-muted);
  margin: 0;
  font-size: 0.875rem;
}

// ===== QUEUE LIST =====
.queue-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.queue-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--qm-bg-tertiary);
  border-radius: 0.75rem;
}

.queue-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.queue-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.queue-count {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.queue-status {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;

  &.open {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
  }

  &.closed {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
  }
}

// ===== APPOINTMENT LIST =====
.appointment-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.appointment-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  background: var(--qm-bg-tertiary);
  border-radius: 0.75rem;
}

.appointment-time {
  min-width: 50px;
}

.time {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.appointment-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.appointment-client {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--qm-text-primary);
}

.appointment-service {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.appointment-status {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  &.booked {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
  }

  &.checked_in {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
  }

  &.in_progress {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
  }

  &.completed {
    background: var(--qm-bg-tertiary);
    color: var(--qm-text-muted);
  }

  &.no_show, &.cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
  }
}
</style>
