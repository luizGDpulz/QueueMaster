<template>
  <q-page class="dashboard-page">
    
    <!-- Título da Página -->
    <div class="page-header">
      <h1 class="page-title">Dashboard</h1>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
      
      <div class="stat-card soft-card" v-for="stat in stats" :key="stat.label">
        <div class="stat-content">
          <div class="stat-info">
            <span class="stat-label">{{ stat.label }}</span>
            <div class="stat-value-row">
              <span class="stat-value">{{ stat.value }}</span>
              <span 
                class="stat-change" 
                :class="stat.positive ? 'positive' : 'negative'"
              >
                {{ stat.change }}
              </span>
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
          <q-btn flat dense icon="more_vert" class="card-menu-btn" />
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
          <q-btn flat dense icon="more_vert" class="card-menu-btn" />
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

  </q-page>
</template>

<script>
import { defineComponent, ref, onMounted } from 'vue'
// import { api } from 'boot/axios' // TODO: usar quando conectar com API real

export default defineComponent({
  name: 'DashboardPage',

  setup() {
    // ===== ESTATÍSTICAS =====
    const stats = ref([
      { label: "Clientes Hoje", value: "0", change: "+0%", positive: true, icon: "people" },
      { label: "Na Fila", value: "0", change: "+0%", positive: true, icon: "format_list_numbered" },
      { label: "Agendamentos", value: "0", change: "+0%", positive: true, icon: "event" },
      { label: "Tempo Médio", value: "0 min", change: "-0%", positive: true, icon: "schedule" }
    ])

    // ===== FILAS =====
    const queues = ref([])

    // ===== AGENDAMENTOS =====
    const appointments = ref([])

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

    // ===== CARREGAR DADOS =====
    const loadDashboard = async () => {
      try {
        // Carregar dados do dashboard da API
        // Por enquanto vamos usar dados de exemplo
        
        // Exemplo de filas
        queues.value = [
          { id: 1, name: 'Atendimento Geral', waiting: 5, status: 'open' },
          { id: 2, name: 'Prioridade', waiting: 2, status: 'open' }
        ]

        // Exemplo de agendamentos
        appointments.value = [
          { id: 1, time: '09:00', client: 'João Silva', service: 'Consulta', status: 'completed' },
          { id: 2, time: '10:30', client: 'Maria Santos', service: 'Retorno', status: 'in_progress' },
          { id: 3, time: '14:00', client: 'Pedro Costa', service: 'Consulta', status: 'booked' }
        ]

        // Atualizar estatísticas
        stats.value = [
          { label: "Clientes Hoje", value: "24", change: "+12%", positive: true, icon: "people" },
          { label: "Na Fila", value: "7", change: "+3%", positive: true, icon: "format_list_numbered" },
          { label: "Agendamentos", value: "18", change: "+8%", positive: true, icon: "event" },
          { label: "Tempo Médio", value: "15 min", change: "-5%", positive: true, icon: "schedule" }
        ]

      } catch (error) {
        console.error('Erro ao carregar dashboard:', error)
      }
    }

    onMounted(() => {
      loadDashboard()
    })

    return {
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

.stat-change {
  font-size: 0.75rem;
  font-weight: 600;

  &.positive {
    color: #22c55e;
  }

  &.negative {
    color: #ef4444;
  }
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-secondary);
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

.card-menu-btn {
  color: var(--qm-text-muted);
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
