<template>
  <q-page class="detail-page">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Relatórios</h1>
      </div>
      <div class="header-right">
        <q-btn flat label="Limpar filtros" no-caps @click="resetFilters" :disable="loadingReports" />
        <q-btn color="primary" icon="analytics" label="Atualizar" no-caps :loading="loadingReports" @click="fetchReports" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Base genérica para indicadores operacionais. Nesta etapa, o foco está em filas e a estrutura já fica pronta para receber relatórios de agendamentos.</p>
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
          <p class="text-muted">Filtros do domínio de filas disponíveis para refinar o relatório consolidado.</p>
        </div>

        <div v-if="loadingFilters" class="loading-state-sm">
          <q-spinner-dots color="primary" size="24px" />
        </div>

        <div v-else class="filters-grid">
          <q-select v-model="filters.business_id" :options="businessOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Negócio" @update:model-value="handleBusinessChange" />
          <q-select v-model="filters.establishment_id" :options="establishmentOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Estabelecimento" @update:model-value="handleEstablishmentChange" />
          <q-select v-model="filters.queue_id" :options="queueOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Fila" />
          <q-select v-model="filters.professional_id" :options="professionalOptions" option-label="name" option-value="id" emit-value map-options clearable outlined dense label="Profissional" />
          <q-select v-model="filters.period" :options="periodOptions" option-label="label" option-value="value" emit-value map-options outlined dense label="Período" />
          <q-select v-model="filters.statuses" :options="statusOptions" option-label="label" option-value="value" emit-value map-options multiple use-chips clearable outlined dense label="Status / desfecho" />
          <q-select v-model="filters.priorities" :options="priorityOptions" option-label="label" option-value="value" emit-value map-options multiple use-chips clearable outlined dense label="Prioridade" />
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
          <div v-for="card in metricCards" :key="card.label" class="soft-card metric-card">
            <span class="metric-card__value">{{ card.value }}</span>
            <span class="metric-card__label">{{ card.label }}</span>
          </div>
        </div>

        <div class="report-layout q-mb-lg">
          <div class="soft-card">
            <div class="section-head">
              <h2 class="section-title">Exportação futura</h2>
              <p class="text-muted">Metadados estáveis do payload para reaproveitamento em PDF.</p>
            </div>
            <div class="meta-grid">
              <div class="meta-item">
                <span class="detail-label">Escopo</span>
                <span class="detail-value">{{ exportScopeLabel }}</span>
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
              <h2 class="section-title">Resumo diário</h2>
              <p class="text-muted">Base compacta para indicadores por dia.</p>
            </div>
            <div v-if="dailyRows.length === 0" class="empty-state-sm">
              <p>Sem movimentação no período selecionado.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Data</th>
                  <th class="text-right">Total</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">No-show</th>
                  <th class="text-right">Cancelados</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in dailyRows" :key="row.date">
                  <td>{{ formatDateOnly(row.date) }}</td>
                  <td class="text-right">{{ row.total }}</td>
                  <td class="text-right">{{ row.completed }}</td>
                  <td class="text-right">{{ row.no_show }}</td>
                  <td class="text-right">{{ row.cancelled }}</td>
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
                  <th class="text-right">Entradas</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">No-show</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in queueBreakdown" :key="row.id" class="cursor-pointer" @click="openQueue(row.id)">
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
              <h2 class="section-title">Profissionais</h2>
              <p class="text-muted">Visão agregada para responsável/atendente quando houver.</p>
            </div>
            <div v-if="professionalBreakdown.length === 0" class="empty-state-sm">
              <p>Nenhum profissional com atendimentos no período.</p>
            </div>
            <q-markup-table v-else flat separator="horizontal">
              <thead>
                <tr>
                  <th class="text-left">Profissional</th>
                  <th class="text-right">Entradas</th>
                  <th class="text-right">Concluídos</th>
                  <th class="text-right">Atendimento médio</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in professionalBreakdown" :key="row.id || row.name">
                  <td>{{ row.name || 'Sem profissional' }}</td>
                  <td class="text-right">{{ row.total_entries }}</td>
                  <td class="text-right">{{ row.total_completed }}</td>
                  <td class="text-right">{{ formatMinutes(row.avg_service_minutes) }}</td>
                </tr>
              </tbody>
            </q-markup-table>
          </div>
        </div>

        <div class="soft-card">
          <div class="section-head">
            <h2 class="section-title">Base detalhada</h2>
            <p class="text-muted">Estrutura pronta para tabelas analíticas e reaproveitamento em exportação.</p>
          </div>
          <q-table flat :rows="reportData.table_rows" :columns="tableColumns" row-key="id" :pagination="{ rowsPerPage: 10 }">
            <template #body-cell-created_at="props">
              <q-td :props="props">{{ formatDateTime(props.row.created_at) }}</q-td>
            </template>
            <template #body-cell-person="props">
              <q-td :props="props">{{ personLabel(props.row) }}</q-td>
            </template>
            <template #body-cell-status="props">
              <q-td :props="props">
                <q-badge color="primary" text-color="white">{{ statusLabel(props.row.status) }}</q-badge>
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
      </template>
    </template>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'boot/axios'

const createDefaultFilters = () => ({
  business_id: null,
  establishment_id: null,
  queue_id: null,
  professional_id: null,
  period: '7d',
  date_from: '',
  date_to: '',
  statuses: [],
  priorities: [],
})

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
      professionals: [],
      statuses: [],
      priorities: [],
      periods: [],
    })
    const reportData = ref({
      filters: {},
      summary: {
        total_entries: 0,
        total_completed: 0,
        total_no_show: 0,
        total_cancelled: 0,
        total_waiting: 0,
        total_serving: 0,
        avg_wait_minutes: 0,
        avg_service_minutes: 0,
        completion_rate: 0,
      },
      series: { daily: [], hourly: [] },
      breakdowns: {
        queue: [],
        professional: [],
      },
      table_rows: [],
      export_meta: {
        generated_at: null,
        scope: 'global',
        ready_for: [],
      },
    })

    const tableColumns = [
      { name: 'created_at', label: 'Entrada', field: 'created_at', align: 'left', sortable: true },
      { name: 'business_name', label: 'Negócio', field: row => row.business_name || '-', align: 'left' },
      { name: 'establishment_name', label: 'Estabelecimento', field: row => row.establishment_name || '-', align: 'left' },
      { name: 'queue_name', label: 'Fila', field: row => row.queue_name || '-', align: 'left' },
      { name: 'person', label: 'Pessoa', field: row => personLabel(row), align: 'left' },
      { name: 'status', label: 'Status', field: row => row.status, align: 'left' },
      { name: 'professional_name', label: 'Profissional', field: row => row.professional_name || '-', align: 'left' },
      { name: 'wait_minutes', label: 'Espera', field: row => row.wait_minutes, align: 'right' },
      { name: 'service_minutes', label: 'Atendimento', field: row => row.service_minutes, align: 'right' },
    ]

    const businessOptions = computed(() => filterMeta.value.businesses || [])
    const professionalOptions = computed(() => filterMeta.value.professionals || [])
    const statusOptions = computed(() => filterMeta.value.statuses || [])
    const priorityOptions = computed(() => filterMeta.value.priorities || [])
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

    const metricCards = computed(() => {
      const summary = reportData.value.summary || {}
      return [
        { label: 'Entradas', value: summary.total_entries || 0 },
        { label: 'Concluídos', value: summary.total_completed || 0 },
        { label: 'Não compareceu', value: summary.total_no_show || 0 },
        { label: 'Cancelados', value: summary.total_cancelled || 0 },
        { label: 'Espera média', value: formatMinutes(summary.avg_wait_minutes) },
        { label: 'Atendimento médio', value: formatMinutes(summary.avg_service_minutes) },
        { label: 'Comparecimento', value: `${summary.completion_rate || 0}%` },
        { label: 'Em atendimento', value: summary.total_serving || 0 },
      ]
    })

    const dailyRows = computed(() => reportData.value.series?.daily || [])
    const queueBreakdown = computed(() => reportData.value.breakdowns?.queue || [])
    const professionalBreakdown = computed(() => reportData.value.breakdowns?.professional || [])
    const reportRangeLabel = computed(() => {
      const current = reportData.value.filters || {}
      if (!current.date_from || !current.date_to) return '-'
      return `${formatDateOnly(current.date_from)} até ${formatDateOnly(current.date_to)}`
    })
    const exportScopeLabel = computed(() => {
      const scope = reportData.value.export_meta?.scope
      if (scope === 'queue') return 'Fila específica'
      if (scope === 'empty') return 'Sem dados'
      return 'Consolidado'
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

    const parseArrayQuery = (value, transform = item => item) => {
      const items = Array.isArray(value) ? value : (value ? String(value).split(',') : [])
      return items
        .map(item => transform(String(item).trim()))
        .filter(item => item !== null && item !== undefined && item !== '')
    }

    const applyRouteFilters = () => {
      const query = route.query || {}
      filters.value = {
        business_id: parseNullableInt(query.business_id),
        establishment_id: parseNullableInt(query.establishment_id),
        queue_id: parseNullableInt(query.queue_id),
        professional_id: parseNullableInt(query.professional_id),
        period: query.period ? String(query.period) : '7d',
        date_from: query.date_from ? String(query.date_from) : '',
        date_to: query.date_to ? String(query.date_to) : '',
        statuses: parseArrayQuery(query.statuses),
        priorities: parseArrayQuery(query.priorities, item => {
          const parsed = Number(item)
          return Number.isFinite(parsed) ? parsed : null
        }),
      }
    }

    const alignFilters = () => {
      if (filters.value.business_id && !establishmentOptions.value.some(item => Number(item.id) === Number(filters.value.establishment_id))) {
        filters.value.establishment_id = null
      }
      if (filters.value.establishment_id && !queueOptions.value.some(item => Number(item.id) === Number(filters.value.queue_id))) {
        filters.value.queue_id = null
      }
    }

    const buildParams = () => {
      const params = { period: filters.value.period }
      if (filters.value.business_id) params.business_id = filters.value.business_id
      if (filters.value.establishment_id) params.establishment_id = filters.value.establishment_id
      if (filters.value.queue_id) params.queue_id = filters.value.queue_id
      if (filters.value.professional_id) params.professional_id = filters.value.professional_id
      if (filters.value.period === 'custom') {
        if (filters.value.date_from) params.date_from = filters.value.date_from
        if (filters.value.date_to) params.date_to = filters.value.date_to
      }
      if (filters.value.statuses.length) params.statuses = filters.value.statuses
      if (filters.value.priorities.length) params.priorities = filters.value.priorities
      return params
    }

    const syncRouteFilters = async () => {
      const query = {}
      if (filters.value.business_id) query.business_id = String(filters.value.business_id)
      if (filters.value.establishment_id) query.establishment_id = String(filters.value.establishment_id)
      if (filters.value.queue_id) query.queue_id = String(filters.value.queue_id)
      if (filters.value.professional_id) query.professional_id = String(filters.value.professional_id)
      if (filters.value.period && filters.value.period !== '7d') query.period = filters.value.period
      if (filters.value.period === 'custom') {
        if (filters.value.date_from) query.date_from = filters.value.date_from
        if (filters.value.date_to) query.date_to = filters.value.date_to
      }
      if (filters.value.statuses.length) query.statuses = filters.value.statuses.join(',')
      if (filters.value.priorities.length) query.priorities = filters.value.priorities.join(',')
      await router.replace({ name: 'queue-reports', query })
    }

    const fetchFilterMetadata = async () => {
      loadingFilters.value = true
      try {
        const { data } = await api.get('/reports/queues/filters')
        if (data?.success) {
          filterMeta.value = data.data?.filters || filterMeta.value
        }
      } finally {
        loadingFilters.value = false
      }
    }

    const fetchReports = async () => {
      alignFilters()
      loadingReports.value = true
      try {
        const { data } = await api.get('/reports/queues', { params: buildParams() })
        if (data?.success) {
          reportData.value = data.data
          await syncRouteFilters()
        }
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar relatórios.' })
      } finally {
        loadingReports.value = false
      }
    }

    const fetchUserRole = async () => {
      const { data } = await api.get('/auth/me')
      const role = data?.data?.user?.role || ''
      if (!['professional', 'manager', 'admin'].includes(role)) {
        $q.notify({ type: 'warning', message: 'Esta área é restrita à operação.' })
        await router.replace('/app')
        return false
      }
      return true
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
        const allowed = await fetchUserRole()
        if (!allowed) return
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
      professionalOptions,
      statusOptions,
      priorityOptions,
      periodOptions,
      metricCards,
      dailyRows,
      queueBreakdown,
      professionalBreakdown,
      reportRangeLabel,
      exportScopeLabel,
      exportReadyLabel,
      tableColumns,
      fetchReports,
      resetFilters,
      handleBusinessChange,
      handleEstablishmentChange,
      openQueue,
      formatDateOnly,
      formatDateTime,
      formatMinutes,
      statusLabel,
      personLabel,
    }
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

function statusLabel(status) {
  return {
    waiting: 'Aguardando',
    called: 'Chamado',
    serving: 'Em atendimento',
    done: 'Concluído',
    no_show: 'Não compareceu',
    cancelled: 'Cancelado',
  }[status] || status || '-'
}

function personLabel(row) {
  return row.user_name || row.guest_name || (row.user_id ? `Usuário #${row.user_id}` : `Entrada #${row.id}`)
}
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

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
</style>
