<template>
  <div class="client-queues-hub">
    <div class="stats-row">
      <div class="stat-card soft-card">
        <div class="stat-icon open">
          <q-icon name="lock_open" size="24px" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ openQueuesCount }}</span>
          <span class="stat-label">Filas abertas</span>
        </div>
      </div>

      <div class="stat-card soft-card">
        <div class="stat-icon active">
          <q-icon name="notifications_active" size="24px" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ activeEntry ? 1 : 0 }}</span>
          <span class="stat-label">Fluxo ativo</span>
        </div>
      </div>

      <div class="stat-card soft-card">
        <div class="stat-icon history">
          <q-icon name="history" size="24px" />
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ historyCount }}</span>
          <span class="stat-label">Participacoes</span>
        </div>
      </div>
    </div>

    <div v-if="activeEntry" class="active-flow soft-card">
      <div class="active-flow__content">
        <div>
          <div class="active-flow__title-row">
            <h2>{{ activeEntry.queue?.name || 'Fila ativa' }}</h2>
            <StatusPill
              :label="getQueueEntryStatusLabel(activeEntry.status)"
              :variant="getQueueEntryStatusVariant(activeEntry.status)"
            />
          </div>
          <p class="active-flow__subtitle">
            {{ activeEntry.establishment?.name || 'Sua fila ativa no momento' }}
          </p>
        </div>

        <q-btn
          v-if="activeEntry.queue?.id"
          color="primary"
          no-caps
          label="Abrir fila atual"
          @click="openQueue(activeEntry.queue.id)"
        />
      </div>
    </div>

    <div class="soft-card hub-card">
      <q-tabs
        v-model="activeTab"
        dense
        align="left"
        active-color="primary"
        indicator-color="primary"
        class="hub-tabs"
      >
        <q-tab name="browse" icon="format_list_numbered" label="Filas" no-caps />
        <q-tab name="history" icon="history" label="Historico" no-caps />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="activeTab" animated class="hub-panels">
        <q-tab-panel name="browse" class="hub-panel">
          <div class="filters-row">
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
              placeholder="Buscar fila..."
              class="search-input"
            >
              <template #prepend>
                <q-icon name="search" />
              </template>
            </q-input>
          </div>

          <div v-if="queuesLoading" class="state-block">
            <q-spinner-dots color="primary" size="36px" />
            <p>Carregando filas disponiveis...</p>
          </div>

          <div v-else-if="filteredQueues.length === 0" class="state-block">
            <q-icon name="format_list_numbered" size="48px" />
            <p>Nenhuma fila encontrada com esses filtros.</p>
          </div>

          <div v-else class="queue-grid">
            <div
              v-for="queue in filteredQueues"
              :key="queue.id"
              class="queue-card soft-card"
              @click="openQueue(queue.id)"
            >
              <div class="queue-card__header">
                <div>
                  <h3>{{ queue.name }}</h3>
                  <p>{{ queue.establishment_name || 'Estabelecimento' }}</p>
                </div>
                <StatusPill
                  :label="queue.status === 'open' ? 'Aberta' : 'Fechada'"
                  :variant="queue.status === 'open' ? 'positive' : 'grey'"
                />
              </div>

              <div class="queue-card__meta">
                <span>{{ queue.service_name || 'Fila geral' }}</span>
                <span>{{ queue.waiting_count || 0 }} aguardando</span>
              </div>

              <div class="queue-card__actions">
                <q-btn
                  color="primary"
                  flat
                  no-caps
                  label="Abrir fila"
                  @click.stop="openQueue(queue.id)"
                />
              </div>
            </div>
          </div>
        </q-tab-panel>

        <q-tab-panel name="history" class="hub-panel">
          <div class="filters-row filters-row--history">
            <q-btn-toggle
              v-model="historyState"
              no-caps
              unelevated
              toggle-color="primary"
              color="grey-3"
              text-color="dark"
              :options="historyStateOptions"
              @update:model-value="refreshHistory"
            />
            <q-btn flat dense no-caps icon="refresh" label="Atualizar" @click="refreshHistory" />
          </div>

          <div v-if="historyLoading" class="state-block">
            <q-spinner-dots color="primary" size="36px" />
            <p>Carregando historico...</p>
          </div>

          <div v-else-if="historyItems.length === 0" class="state-block">
            <q-icon name="history" size="48px" />
            <p>Voce ainda nao participou de nenhuma fila.</p>
          </div>

          <div v-else class="history-grid">
            <QueueEntryHistoryCard
              v-for="item in historyItems"
              :key="item.entry_public_id"
              :item="item"
              :show-queue-action="Boolean(item.can_join_again && item.queue?.status === 'open' && item.queue?.id)"
              queue-action-label="Abrir fila"
              @open="openHistory"
              @open-queue="openHistoryQueue"
            />
          </div>
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <q-dialog v-model="showHistoryDialog">
      <q-card class="history-dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Fluxo da fila</div>
          <q-btn flat round dense icon="close" @click="closeHistoryDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <QueueEntryHistoryPanel
            :entry="timelineEntry"
            :events="timelineEvents"
            :loading="timelineLoading"
            :error="timelineError"
            :show-primary-action="Boolean(timelineEntry?.queue?.id && (timelineEntry?.is_active || timelineEntry?.queue?.status === 'open'))"
            :primary-action-label="timelineEntry?.is_active ? 'Abrir fila atual' : 'Abrir fila'"
            @primary-action="openTimelineQueue"
          />
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import { computed, defineComponent, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import QueueEntryHistoryCard from 'src/components/queue/QueueEntryHistoryCard.vue'
import QueueEntryHistoryPanel from 'src/components/queue/QueueEntryHistoryPanel.vue'
import StatusPill from 'src/components/ui/StatusPill.vue'
import { api } from 'boot/axios'
import {
  fetchCurrentQueueEntry,
  fetchQueueEntryHistory,
  fetchQueueEntryTimeline,
  getQueueEntryStatusLabel,
  getQueueEntryStatusVariant,
} from 'src/composables/useQueueEntryHistory'

export default defineComponent({
  name: 'ClientQueuesHub',

  components: {
    QueueEntryHistoryCard,
    QueueEntryHistoryPanel,
    StatusPill,
  },

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    const activeTab = ref('browse')
    const searchQuery = ref('')
    const filterStatus = ref(null)
    const historyState = ref('all')

    const queues = ref([])
    const historyItems = ref([])
    const activeEntry = ref(null)

    const queuesLoading = ref(true)
    const historyLoading = ref(true)

    const showHistoryDialog = ref(false)
    const timelineLoading = ref(false)
    const timelineError = ref('')
    const timelineEntry = ref(null)
    const timelineEvents = ref([])

    const statusOptions = [
      { label: 'Aberta', value: 'open' },
      { label: 'Fechada', value: 'closed' },
    ]

    const historyStateOptions = [
      { label: 'Tudo', value: 'all' },
      { label: 'Ativas', value: 'active' },
      { label: 'Finalizadas', value: 'finished' },
    ]

    const openQueuesCount = computed(() => (
      queues.value.filter((queue) => queue.status === 'open').length
    ))

    const historyCount = computed(() => historyItems.value.length)

    const filteredQueues = computed(() => {
      let result = [...queues.value]

      if (filterStatus.value) {
        result = result.filter((queue) => queue.status === filterStatus.value)
      }

      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        result = result.filter((queue) => (
          queue.name?.toLowerCase().includes(query)
          || queue.establishment_name?.toLowerCase().includes(query)
          || queue.service_name?.toLowerCase().includes(query)
        ))
      }

      return result
    })

    const fetchQueues = async () => {
      queuesLoading.value = true
      try {
        const response = await api.get('/queues')
        queues.value = response.data?.data?.queues || response.data?.data || []
      } catch (error) {
        $q.notify({
          type: 'negative',
          message: error?.response?.data?.error?.message || 'Nao foi possivel carregar as filas.',
        })
      } finally {
        queuesLoading.value = false
      }
    }

    const refreshHistory = async () => {
      historyLoading.value = true
      try {
        const response = await fetchQueueEntryHistory({
          state: historyState.value === 'all' ? undefined : historyState.value,
        })
        historyItems.value = response.items || []
      } catch (error) {
        $q.notify({
          type: 'negative',
          message: error?.response?.data?.error?.message || 'Nao foi possivel carregar o historico.',
        })
      } finally {
        historyLoading.value = false
      }
    }

    const refreshActiveEntry = async () => {
      try {
        activeEntry.value = await fetchCurrentQueueEntry()
      } catch (error) {
        $q.notify({
          type: 'negative',
          message: error?.response?.data?.error?.message || 'Nao foi possivel verificar sua fila ativa.',
        })
      }
    }

    const refreshAll = async () => {
      await Promise.all([
        fetchQueues(),
        refreshHistory(),
        refreshActiveEntry(),
      ])
    }

    const openQueue = (queueId) => {
      if (!queueId) return
      router.push(`/app/queues/${queueId}`)
    }

    const openHistoryQueue = (item) => {
      openQueue(item?.queue?.id)
    }

    const closeHistoryDialog = () => {
      showHistoryDialog.value = false
      timelineLoading.value = false
      timelineError.value = ''
      timelineEntry.value = null
      timelineEvents.value = []
    }

    const openHistory = async (item) => {
      showHistoryDialog.value = true
      timelineLoading.value = true
      timelineError.value = ''
      timelineEntry.value = null
      timelineEvents.value = []

      try {
        const response = await fetchQueueEntryTimeline(item.entry_public_id, item.queue?.id)
        timelineEntry.value = response.entry
        timelineEvents.value = response.events
      } catch (error) {
        timelineError.value = error?.response?.data?.error?.message || 'Nao foi possivel carregar o fluxo.'
      } finally {
        timelineLoading.value = false
      }
    }

    const openTimelineQueue = () => {
      openQueue(timelineEntry.value?.queue?.id)
    }

    onMounted(() => {
      refreshAll()
    })

    return {
      activeTab,
      searchQuery,
      filterStatus,
      historyState,
      historyItems,
      activeEntry,
      queuesLoading,
      historyLoading,
      showHistoryDialog,
      timelineLoading,
      timelineError,
      timelineEntry,
      timelineEvents,
      statusOptions,
      historyStateOptions,
      openQueuesCount,
      historyCount,
      filteredQueues,
      refreshHistory,
      openQueue,
      openHistoryQueue,
      closeHistoryDialog,
      openHistory,
      openTimelineQueue,
      getQueueEntryStatusLabel,
      getQueueEntryStatusVariant,
    }
  },
})
</script>

<style lang="scss" scoped>
.client-queues-hub {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
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

  &.active {
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
  }

  &.history {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
  }
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

.active-flow {
  padding: 1.25rem;
}

.active-flow__content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.active-flow__title-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;

  h2 {
    margin: 0;
    font-size: 1.125rem;
    color: var(--qm-text-primary);
  }
}

.active-flow__subtitle {
  margin: 0.5rem 0 0;
  color: var(--qm-text-muted);
  font-size: 0.875rem;
}

.hub-card {
  padding: 0;
  overflow: hidden;
}

.hub-tabs {
  padding: 0.75rem 1rem 0;
}

.hub-panel {
  padding: 1.25rem;
}

.filters-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.filters-row--history {
  justify-content: space-between;
}

.filter-select {
  min-width: 140px;
}

.search-input {
  min-width: 240px;
  flex: 1;
}

.state-block {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  color: var(--qm-text-muted);
  text-align: center;
}

.queue-grid,
.history-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
}

.queue-card {
  padding: 1.25rem;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: var(--qm-shadow-lg);
  }
}

.queue-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;

  h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0.4rem 0 0;
    font-size: 0.8125rem;
    color: var(--qm-text-muted);
  }
}

.queue-card__meta {
  margin-top: 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  font-size: 0.8125rem;
  color: var(--qm-text-secondary);
}

.queue-card__actions {
  margin-top: 1rem;
  padding-top: 0.875rem;
  border-top: 1px solid var(--qm-border);
  display: flex;
  justify-content: flex-end;
}

.history-dialog-card {
  width: min(760px, calc(100vw - 32px));
  max-width: 760px;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--qm-border);
}

.dialog-content {
  padding: 1.25rem;
}

@media (max-width: 720px) {
  .search-input {
    min-width: 100%;
  }

  .filters-row--history {
    align-items: flex-start;
  }
}
</style>
