<template>
  <q-page class="reports-page">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Relatórios</h1>
      </div>
      <div class="header-right">
        <q-btn flat label="Limpar filtros" no-caps @click="resetFilters" :disable="loadingReports" />
        <q-btn color="primary" icon="analytics" label="Atualizar" no-caps :loading="loadingReports" @click="fetchReports" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">
          Visão geral de filas e agendamentos com escopo automático conforme seus vínculos.
        </p>
      </div>
    </div>

    <div v-if="pageLoading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando relatórios...</p>
    </div>

    <template v-else>
      <div class="soft-card q-mb-lg">
        <div class="section-head q-mb-md">
          <h2 class="section-title">Filtros</h2>
          <p class="text-muted">
            Os filtros exibem somente negócios, estabelecimentos, filas, serviços e profissionais aos quais você tem vínculo.
          </p>
        </div>

        <div v-if="loadingFilters" class="loading-state-sm">
          <q-spinner-dots color="primary" size="24px" />
        </div>

        <div v-else class="filters-grid">
          <q-select v-model="filters.business_id" :options="businessOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Negócio" @update:model-value="handleBusinessChange" />
          <q-select v-model="filters.establishment_id" :options="establishmentOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Estabelecimento" @update:model-value="handleEstablishmentChange" />
          <q-select v-model="filters.queue_id" :options="queueOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Fila" />
          <q-select v-model="filters.service_id" :options="serviceOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Serviço" />
          <q-select v-model="filters.queue_professional_user_id" :options="queueProfessionalOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Profissional da fila" />
          <q-select v-model="filters.appointment_professional_id" :options="appointmentProfessionalOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Profissional do agendamento" />
          <q-select v-model="filters.period" :options="periodOptions" option-label="label" option-value="value" emit-value map-options outlined dense label="Período" />
          <q-select v-model="filters.queue_statuses" :options="queueStatusOptions" option-label="label" option-value="value" emit-value map-options multiple use-chips clearable outlined dense label="Status das filas" />
          <q-select v-model="filters.appointment_statuses" :options="appointmentStatusOptions" option-label="label" option-value="value" emit-value map-options multiple use-chips clearable outlined dense label="Status dos agendamentos" />
          <q-input v-if="filters.period === 'custom'" v-model="filters.date_from" outlined dense type="date" label="Data inicial" />
          <q-input v-if="filters.period === 'custom'" v-model="filters.date_to" outlined dense type="date" label="Data final" />
        </div>
      </div>

      <div v-if="loadingReports" class="loading-state">
        <q-spinner-dots color="primary" size="32px" />
        <p>Atualizando indicadores...</p>
      </div>

      <template v-else>
        <div class="report-grid q-mb-lg">
          <div v-for="card in overviewCards" :key="card.label" class="soft-card metric-card">
            <span class="metric-card__value">{{ card.value }}</span>
            <span class="metric-card__label">{{ card.label }}</span>
          </div>
        </div>

        <div class="report-layout q-mb-lg">
          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Escopo</h2>
              <p class="text-muted">Resumo do recorte aplicado.</p>
            </div>
            <div class="meta-grid">
              <div class="meta-item">
                <span class="detail-label">Perfil</span>
                <span class="detail-value">{{ roleLabel }}</span>
              </div>
              <div class="meta-item">
                <span class="detail-label">Período</span>
                <span class="detail-value">{{ reportRangeLabel }}</span>
              </div>
              <div class="meta-item">
                <span class="detail-label">Gerado em</span>
                <span class="detail-value">{{ formatDateTime(reportData.export_meta.generated_at) }}</span>
              </div>
              <div class="meta-item">
                <span class="detail-label">Pronto para</span>
                <span class="detail-value">{{ exportReadyLabel }}</span>
              </div>
            </div>
          </div>

          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Movimento diário</h2>
              <p class="text-muted">Comparativo diário entre filas e agendamentos.</p>
            </div>
            <div v-if="combinedDailyRows.length === 0" class="empty-state-sm">
              <p>Sem movimentação no período selecionado.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Data</th>
                  <th class="text-right">Filas</th>
                  <th class="text-right">Agendamentos</th>
                  <th class="text-right">Concluídos</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in combinedDailyRows" :key="row.date">
                  <td>{{ formatDateOnly(row.date) }}</td>
                  <td class="text-right">{{ row.queue_total }}</td>
                  <td class="text-right">{{ row.appointment_total }}</td>
                  <td class="text-right">{{ row.completed_total }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>
        </div>

        <div class="report-layout q-mb-lg">
          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Resumo de filas</h2>
              <p class="text-muted">Indicadores operacionais de atendimento em fila.</p>
            </div>
            <div class="summary-chip-grid">
              <div class="summary-chip">
                <span class="summary-chip__label">Entradas</span>
                <strong>{{ reportData.queue.summary.total_entries || 0 }}</strong>
              </div>
              <div class="summary-chip">
                <span class="summary-chip__label">Concluídos</span>
                <strong>{{ reportData.queue.summary.total_completed || 0 }}</strong>
              </div>
              <div class="summary-chip">
                <span class="summary-chip__label">Aguardando</span>
                <strong>{{ reportData.queue.summary.total_waiting || 0 }}</strong>
              </div>
              <div class="summary-chip">
                <span class="summary-chip__label">Espera média</span>
                <strong>{{ formatMinutes(reportData.queue.summary.avg_wait_minutes) }}</strong>
              </div>
            </div>
          </div>

          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Resumo de agendamentos</h2>
              <p class="text-muted">Indicadores de agenda dentro do mesmo recorte.</p>
            </div>
            <div class="summary-chip-grid">
              <div class="summary-chip">
                <span class="summary-chip__label">Agendamentos</span>
                <strong>{{ reportData.appointment.summary.total_appointments || 0 }}</strong>
              </div>
              <div class="summary-chip">
                <span class="summary-chip__label">Concluídos</span>
                <strong>{{ reportData.appointment.summary.total_completed || 0 }}</strong>
              </div>
              <div class="summary-chip">
                <span class="summary-chip__label">Próximos</span>
                <strong>{{ reportData.appointment.summary.total_upcoming || 0 }}</strong>
              </div>
              <div class="summary-chip">
                <span class="summary-chip__label">Duração média</span>
                <strong>{{ formatMinutes(reportData.appointment.summary.avg_duration_minutes) }}</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="report-layout q-mb-lg">
          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Filas por unidade</h2>
              <p class="text-muted">Onde o volume de filas está concentrado.</p>
            </div>
            <div v-if="queueEstablishmentBreakdown.length === 0" class="empty-state-sm">
              <p>Nenhum dado de filas no recorte atual.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Estabelecimento</th>
                  <th class="text-right">Entradas</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">Não compareceu</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in queueEstablishmentBreakdown" :key="row.id">
                  <td>{{ row.name }}</td>
                  <td class="text-right">{{ row.total_entries }}</td>
                  <td class="text-right">{{ row.total_completed }}</td>
                  <td class="text-right">{{ row.total_no_show }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>

          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Agendamentos por serviço</h2>
              <p class="text-muted">Distribuição da agenda por serviço.</p>
            </div>
            <div v-if="appointmentServiceBreakdown.length === 0" class="empty-state-sm">
              <p>Nenhum serviço com agendamentos no período.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Serviço</th>
                  <th class="text-right">Agendamentos</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">Não compareceu</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in appointmentServiceBreakdown" :key="row.id || row.name">
                  <td>{{ row.name || 'Sem serviço' }}</td>
                  <td class="text-right">{{ row.total_appointments }}</td>
                  <td class="text-right">{{ row.total_completed }}</td>
                  <td class="text-right">{{ row.total_no_show }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>
        </div>

        <div class="report-layout q-mb-lg">
          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Filas</h2>
              <p class="text-muted">Agregado por fila para drill-down operacional.</p>
            </div>
            <div v-if="queueBreakdown.length === 0" class="empty-state-sm">
              <p>Nenhuma fila no recorte atual.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Fila</th>
                  <th class="text-left">Estabelecimento</th>
                  <th class="text-right">Entradas</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">Espera média</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in queueBreakdown" :key="row.id" class="cursor-pointer" @click="openQueue(row.id)">
                  <td>{{ row.name }}</td>
                  <td>{{ row.establishment_name }}</td>
                  <td class="text-right">{{ row.total_entries }}</td>
                  <td class="text-right">{{ row.total_completed }}</td>
                  <td class="text-right">{{ formatMinutes(row.avg_wait_minutes) }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>

          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Agendamentos por profissional</h2>
              <p class="text-muted">Produtividade da agenda por profissional.</p>
            </div>
            <div v-if="appointmentProfessionalBreakdown.length === 0" class="empty-state-sm">
              <p>Nenhum profissional com agendamentos no período.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Profissional</th>
                  <th class="text-right">Agendamentos</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">Não compareceu</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in appointmentProfessionalBreakdown" :key="row.id || row.name">
                  <td>{{ row.name || 'Sem profissional' }}</td>
                  <td class="text-right">{{ row.total_appointments }}</td>
                  <td class="text-right">{{ row.total_completed }}</td>
                  <td class="text-right">{{ row.total_no_show }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>
        </div>

        <div class="report-layout q-mb-lg">
          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Base detalhada de filas</h2>
              <p class="text-muted">Últimos registros do recorte para análise operacional.</p>
            </div>
            <q-table flat :rows="reportData.queue.table_rows" :columns="queueTableColumns" row-key="id" :pagination="{ rowsPerPage: 10 }">
              <template #body-cell-created_at="props">
                <q-td :props="props">{{ formatDateTime(props.row.created_at) }}</q-td>
              </template>
              <template #body-cell-person="props">
                <q-td :props="props">{{ queuePersonLabel(props.row) }}</q-td>
              </template>
              <template #body-cell-status="props">
                <q-td :props="props">
                  <q-badge color="primary" text-color="white">{{ queueStatusLabel(props.row.status) }}</q-badge>
                </q-td>
              </template>
              <template #body-cell-wait_minutes="props">
                <q-td :props="props">{{ formatMinutes(props.row.wait_minutes) }}</q-td>
              </template>
              <template #body-cell-service_minutes="props">
                <q-td :props="props">{{ formatMinutes(props.row.service_minutes) }}</q-td>
              </template>
            </q-table>
          </div>

          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Base detalhada de agendamentos</h2>
              <p class="text-muted">Últimos agendamentos do recorte aplicado.</p>
            </div>
            <q-table flat :rows="reportData.appointment.table_rows" :columns="appointmentTableColumns" row-key="id" :pagination="{ rowsPerPage: 10 }">
              <template #body-cell-start_at="props">
                <q-td :props="props">{{ formatDateTime(props.row.start_at) }}</q-td>
              </template>
              <template #body-cell-status="props">
                <q-td :props="props">
                  <q-badge color="secondary" text-color="white">{{ appointmentStatusLabel(props.row.status) }}</q-badge>
                </q-td>
              </template>
              <template #body-cell-duration_minutes="props">
                <q-td :props="props">{{ formatMinutes(props.row.duration_minutes) }}</q-td>
              </template>
            </q-table>
          </div>
        </div>
      </template>
    </template>
  </q-page>
</template>

<script>
import { computed, defineComponent, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'boot/axios'

const createDefaultFilters = () => ({
  business_id: null,
  establishment_id: null,
  queue_id: null,
  service_id: null,
  queue_professional_user_id: null,
  appointment_professional_id: null,
  period: '7d',
  date_from: '',
  date_to: '',
  queue_statuses: [],
  appointment_statuses: [],
})

const createDefaultReport = () => ({
  scope: {
    role: '',
  },
  overview: {
    total_records: 0,
    total_completed: 0,
    total_no_show: 0,
    total_cancelled: 0,
    active_now: 0,
    upcoming_appointments: 0,
  },
  queue: {
    summary: {
      total_entries: 0,
      total_completed: 0,
      total_no_show: 0,
      total_cancelled: 0,
      total_waiting: 0,
      total_serving: 0,
      avg_wait_minutes: 0,
      avg_service_minutes: 0,
    },
    series: { daily: [] },
    breakdowns: { queue: [], establishment: [], professional: [] },
    table_rows: [],
  },
  appointment: {
    summary: {
      total_appointments: 0,
      total_completed: 0,
      total_no_show: 0,
      total_cancelled: 0,
      total_in_progress: 0,
      total_upcoming: 0,
      avg_duration_minutes: 0,
    },
    series: { daily: [] },
    breakdowns: { service: [], establishment: [], professional: [] },
    table_rows: [],
  },
  export_meta: {
    generated_at: null,
    ready_for: [],
  },
})

function formatDateOnly(value) {
  if (!value) return '-'
  return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR')
}

function formatDateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('pt-BR')
}

function formatMinutes(value) {
  if (value === null || value === undefined || value === '') return '-'
  return `${Number(value)} min`
}

function queueStatusLabel(status) {
  return {
    waiting: 'Aguardando',
    called: 'Chamado',
    serving: 'Em atendimento',
    done: 'Concluído',
    no_show: 'Não compareceu',
    cancelled: 'Cancelado',
  }[status] || status || '-'
}

function appointmentStatusLabel(status) {
  return {
    booked: 'Agendado',
    confirmed: 'Confirmado',
    checked_in: 'Check-in',
    in_progress: 'Em andamento',
    completed: 'Concluído',
    no_show: 'Não compareceu',
    cancelled: 'Cancelado',
  }[status] || status || '-'
}

function queuePersonLabel(row) {
  return row.user_name || row.guest_name || (row.user_id ? `Usuário #${row.user_id}` : `Entrada #${row.id}`)
}

export default defineComponent({
  name: 'QueueReportsPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const pageLoading = ref(true)
    const loadingFilters = ref(false)
    const loadingReports = ref(false)
    const filters = ref(createDefaultFilters())
    const filterMeta = ref({
      businesses: [],
      establishments: [],
      queues: [],
      queue_professionals: [],
      appointment_professionals: [],
      services: [],
      queue_statuses: [],
      appointment_statuses: [],
      periods: [],
    })
    const reportData = ref(createDefaultReport())

    const queueTableColumns = [
      { name: 'created_at', label: 'Entrada', field: 'created_at', align: 'left', sortable: true },
      { name: 'business_name', label: 'Negócio', field: row => row.business_name || '-', align: 'left' },
      { name: 'establishment_name', label: 'Estabelecimento', field: row => row.establishment_name || '-', align: 'left' },
      { name: 'queue_name', label: 'Fila', field: row => row.queue_name || '-', align: 'left' },
      { name: 'person', label: 'Pessoa', field: row => queuePersonLabel(row), align: 'left' },
      { name: 'status', label: 'Status', field: row => row.status, align: 'left' },
      { name: 'professional_name', label: 'Profissional', field: row => row.professional_name || '-', align: 'left' },
      { name: 'wait_minutes', label: 'Espera', field: row => row.wait_minutes, align: 'right' },
      { name: 'service_minutes', label: 'Atendimento', field: row => row.service_minutes, align: 'right' },
    ]

    const appointmentTableColumns = [
      { name: 'start_at', label: 'Início', field: 'start_at', align: 'left', sortable: true },
      { name: 'business_name', label: 'Negócio', field: row => row.business_name || '-', align: 'left' },
      { name: 'establishment_name', label: 'Estabelecimento', field: row => row.establishment_name || '-', align: 'left' },
      { name: 'user_name', label: 'Cliente', field: row => row.user_name || '-', align: 'left' },
      { name: 'professional_name', label: 'Profissional', field: row => row.professional_name || '-', align: 'left' },
      { name: 'service_name', label: 'Serviço', field: row => row.service_name || '-', align: 'left' },
      { name: 'status', label: 'Status', field: row => row.status, align: 'left' },
      { name: 'duration_minutes', label: 'Duração', field: row => row.duration_minutes, align: 'right' },
    ]

    const businessOptions = computed(() => filterMeta.value.businesses || [])
    const queueProfessionalOptions = computed(() => filterMeta.value.queue_professionals || [])
    const appointmentProfessionalOptions = computed(() => filterMeta.value.appointment_professionals || [])
    const queueStatusOptions = computed(() => filterMeta.value.queue_statuses || [])
    const appointmentStatusOptions = computed(() => filterMeta.value.appointment_statuses || [])
    const periodOptions = computed(() => filterMeta.value.periods || [])
    const establishmentOptions = computed(() => {
      const items = filterMeta.value.establishments || []
      if (!filters.value.business_id) return items
      return items.filter(item => Number(item.business_id) === Number(filters.value.business_id))
    })
    const queueOptions = computed(() => {
      let items = filterMeta.value.queues || []
      if (filters.value.business_id) {
        items = items.filter(item => Number(item.business_id) === Number(filters.value.business_id))
      }
      if (filters.value.establishment_id) {
        items = items.filter(item => Number(item.establishment_id) === Number(filters.value.establishment_id))
      }
      return items
    })
    const serviceOptions = computed(() => {
      let items = filterMeta.value.services || []
      if (filters.value.establishment_id) {
        items = items.filter(item => Number(item.establishment_id) === Number(filters.value.establishment_id))
      }
      return items
    })

    const overviewCards = computed(() => [
      { label: 'Registros totais', value: reportData.value.overview.total_records || 0 },
      { label: 'Concluídos', value: reportData.value.overview.total_completed || 0 },
      { label: 'Não compareceu', value: reportData.value.overview.total_no_show || 0 },
      { label: 'Cancelados', value: reportData.value.overview.total_cancelled || 0 },
      { label: 'Em andamento agora', value: reportData.value.overview.active_now || 0 },
      { label: 'Próximos agendamentos', value: reportData.value.overview.upcoming_appointments || 0 },
    ])

    const combinedDailyRows = computed(() => {
      const map = new Map()

      for (const row of reportData.value.queue.series?.daily || []) {
        map.set(row.date, {
          date: row.date,
          queue_total: Number(row.total || 0),
          appointment_total: 0,
          completed_total: Number(row.completed || 0),
        })
      }

      for (const row of reportData.value.appointment.series?.daily || []) {
        const current = map.get(row.date) || {
          date: row.date,
          queue_total: 0,
          appointment_total: 0,
          completed_total: 0,
        }
        current.appointment_total = Number(row.total || 0)
        current.completed_total += Number(row.completed || 0)
        map.set(row.date, current)
      }

      return Array.from(map.values()).sort((a, b) => String(a.date).localeCompare(String(b.date)))
    })

    const queueBreakdown = computed(() => reportData.value.queue.breakdowns?.queue || [])
    const queueEstablishmentBreakdown = computed(() => reportData.value.queue.breakdowns?.establishment || [])
    const appointmentServiceBreakdown = computed(() => reportData.value.appointment.breakdowns?.service || [])
    const appointmentProfessionalBreakdown = computed(() => reportData.value.appointment.breakdowns?.professional || [])
    const reportRangeLabel = computed(() => {
      const current = reportData.value.filters || filters.value
      if (!current.date_from || !current.date_to) return '-'
      return `${formatDateOnly(current.date_from)} até ${formatDateOnly(current.date_to)}`
    })
    const roleLabel = computed(() => {
      const role = reportData.value.scope?.role
      return {
        admin: 'Administrador',
        manager: 'Gerente',
        professional: 'Profissional',
        client: 'Cliente',
      }[role] || 'Usuário'
    })
    const exportReadyLabel = computed(() => {
      const items = reportData.value.export_meta?.ready_for || []
      return items.length ? items.join(', ').toUpperCase() : '-'
    })

    const parseNullableInt = (value) => {
      if (value === null || value === undefined || value === '') return null
      const parsed = Number(Array.isArray(value) ? value[0] : value)
      return Number.isFinite(parsed) && parsed > 0 ? parsed : null
    }

    const parseArrayQuery = (value) => {
      const items = Array.isArray(value) ? value : (value ? String(value).split(',') : [])
      return items.map(item => String(item).trim()).filter(Boolean)
    }

    const applyRouteFilters = () => {
      const query = route.query || {}
      filters.value = {
        business_id: parseNullableInt(query.business_id),
        establishment_id: parseNullableInt(query.establishment_id),
        queue_id: parseNullableInt(query.queue_id),
        service_id: parseNullableInt(query.service_id),
        queue_professional_user_id: parseNullableInt(query.queue_professional_user_id),
        appointment_professional_id: parseNullableInt(query.appointment_professional_id),
        period: query.period ? String(query.period) : '7d',
        date_from: query.date_from ? String(query.date_from) : '',
        date_to: query.date_to ? String(query.date_to) : '',
        queue_statuses: parseArrayQuery(query.queue_statuses),
        appointment_statuses: parseArrayQuery(query.appointment_statuses),
      }
    }

    const alignFilters = () => {
      if (filters.value.business_id && !establishmentOptions.value.some(item => Number(item.id) === Number(filters.value.establishment_id))) {
        filters.value.establishment_id = null
      }

      if (filters.value.establishment_id && !queueOptions.value.some(item => Number(item.id) === Number(filters.value.queue_id))) {
        filters.value.queue_id = null
      }

      if (filters.value.establishment_id && !serviceOptions.value.some(item => Number(item.id) === Number(filters.value.service_id))) {
        filters.value.service_id = null
      }

      if (filters.value.queue_professional_user_id && !queueProfessionalOptions.value.some(item => Number(item.id) === Number(filters.value.queue_professional_user_id))) {
        filters.value.queue_professional_user_id = null
      }

      if (filters.value.appointment_professional_id && !appointmentProfessionalOptions.value.some(item => Number(item.id) === Number(filters.value.appointment_professional_id))) {
        filters.value.appointment_professional_id = null
      }
    }

    const buildParams = () => {
      const params = { period: filters.value.period }
      if (filters.value.business_id) params.business_id = filters.value.business_id
      if (filters.value.establishment_id) params.establishment_id = filters.value.establishment_id
      if (filters.value.queue_id) params.queue_id = filters.value.queue_id
      if (filters.value.service_id) params.service_id = filters.value.service_id
      if (filters.value.queue_professional_user_id) params.queue_professional_user_id = filters.value.queue_professional_user_id
      if (filters.value.appointment_professional_id) params.appointment_professional_id = filters.value.appointment_professional_id
      if (filters.value.period === 'custom') {
        if (filters.value.date_from) params.date_from = filters.value.date_from
        if (filters.value.date_to) params.date_to = filters.value.date_to
      }
      if (filters.value.queue_statuses.length) params.queue_statuses = filters.value.queue_statuses
      if (filters.value.appointment_statuses.length) params.appointment_statuses = filters.value.appointment_statuses
      return params
    }

    const syncRouteFilters = async () => {
      const query = {}
      if (filters.value.business_id) query.business_id = String(filters.value.business_id)
      if (filters.value.establishment_id) query.establishment_id = String(filters.value.establishment_id)
      if (filters.value.queue_id) query.queue_id = String(filters.value.queue_id)
      if (filters.value.service_id) query.service_id = String(filters.value.service_id)
      if (filters.value.queue_professional_user_id) query.queue_professional_user_id = String(filters.value.queue_professional_user_id)
      if (filters.value.appointment_professional_id) query.appointment_professional_id = String(filters.value.appointment_professional_id)
      if (filters.value.period && filters.value.period !== '7d') query.period = filters.value.period
      if (filters.value.period === 'custom') {
        if (filters.value.date_from) query.date_from = filters.value.date_from
        if (filters.value.date_to) query.date_to = filters.value.date_to
      }
      if (filters.value.queue_statuses.length) query.queue_statuses = filters.value.queue_statuses.join(',')
      if (filters.value.appointment_statuses.length) query.appointment_statuses = filters.value.appointment_statuses.join(',')
      await router.replace({ name: 'queue-reports', query })
    }

    const fetchFilterMetadata = async () => {
      loadingFilters.value = true
      try {
        const { data } = await api.get('/reports/filters')
        if (data?.success) {
          filterMeta.value = data.data?.filters || filterMeta.value
        }
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar filtros dos relatórios.' })
      } finally {
        loadingFilters.value = false
      }
    }

    const fetchReports = async () => {
      alignFilters()
      loadingReports.value = true
      try {
        const params = buildParams()
        const { data } = await api.get('/reports', { params })
        if (data?.success) {
          reportData.value = { ...createDefaultReport(), ...data.data }
          await syncRouteFilters()
        }
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar relatórios.' })
      } finally {
        loadingReports.value = false
      }
    }

    const handleBusinessChange = () => {
      alignFilters()
    }

    const handleEstablishmentChange = () => {
      alignFilters()
    }

    const resetFilters = async () => {
      filters.value = createDefaultFilters()
      await fetchReports()
    }

    const openQueue = (queueId) => {
      if (!queueId) return
      router.push({ name: 'queue-detail', params: { id: queueId } })
    }

    onMounted(async () => {
      applyRouteFilters()

      try {
        await fetchFilterMetadata()
        alignFilters()
        await fetchReports()
      } finally {
        pageLoading.value = false
      }
    })

    return {
      pageLoading,
      loadingFilters,
      loadingReports,
      filters,
      reportData,
      businessOptions,
      establishmentOptions,
      queueOptions,
      serviceOptions,
      queueProfessionalOptions,
      appointmentProfessionalOptions,
      queueStatusOptions,
      appointmentStatusOptions,
      periodOptions,
      overviewCards,
      combinedDailyRows,
      queueBreakdown,
      queueEstablishmentBreakdown,
      appointmentServiceBreakdown,
      appointmentProfessionalBreakdown,
      reportRangeLabel,
      roleLabel,
      exportReadyLabel,
      queueTableColumns,
      appointmentTableColumns,
      fetchReports,
      resetFilters,
      handleBusinessChange,
      handleEstablishmentChange,
      openQueue,
      formatDateOnly,
      formatDateTime,
      formatMinutes,
      queueStatusLabel,
      appointmentStatusLabel,
      queuePersonLabel,
    }
  },
})
</script>

<style lang="scss" scoped>
.reports-page {
  padding: 0 1.5rem 1.5rem;
}

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

.header-right {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
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

.soft-card {
  background: var(--qm-surface);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--qm-shadow);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.report-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 1rem;
}

.report-layout {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1rem;
}

.metric-card {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.metric-card__value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
}

.metric-card__label {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

.section-head {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.section-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin: 0;
}

.text-muted {
  color: var(--qm-text-muted);
  margin: 0;
  font-size: 0.8125rem;
}

.meta-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 1rem;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
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

.summary-chip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 0.75rem;
}

.summary-chip {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.875rem 1rem;
  border-radius: 0.75rem;
  background: var(--qm-bg-secondary);
}

.summary-chip__label {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.loading-state,
.loading-state-sm,
.empty-state-sm {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--qm-text-muted);
}

.loading-state {
  padding: 3rem 1rem;
}

.loading-state-sm,
.empty-state-sm {
  padding: 2rem 1rem;
}

@media (max-width: 768px) {
  .header-right {
    width: 100%;
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .report-layout {
    grid-template-columns: 1fr;
  }
}
</style>
