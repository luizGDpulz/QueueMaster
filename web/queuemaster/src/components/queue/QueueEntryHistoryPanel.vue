<template>
  <div class="history-panel">
    <div v-if="loading" class="history-panel__state">
      <q-spinner-dots color="primary" size="32px" />
      <span>Carregando linha do tempo...</span>
    </div>

    <div v-else-if="error" class="history-panel__state history-panel__state--error">
      <q-icon name="error_outline" size="28px" />
      <span>{{ error }}</span>
    </div>

    <div v-else-if="!entry" class="history-panel__state">
      <q-icon name="history" size="28px" />
      <span>Nenhum fluxo encontrado.</span>
    </div>

    <template v-else>
      <div class="history-panel__hero">
        <div>
          <div class="history-panel__title-row">
            <h3>{{ entry.queue?.name || 'Fluxo da fila' }}</h3>
            <StatusPill
              :label="getQueueEntryStatusLabel(entry.status)"
              :variant="getQueueEntryStatusVariant(entry.status)"
            />
          </div>
          <p class="history-panel__subtitle">
            {{ subtitle }}
          </p>
        </div>

        <q-btn
          v-if="showPrimaryAction"
          color="primary"
          no-caps
          :label="primaryActionLabel"
          @click="$emit('primary-action')"
        />
      </div>

      <div class="history-panel__meta-grid">
        <div class="history-panel__meta-card">
          <span>Entrada</span>
          <strong>{{ formatQueueEntryDateTime(entry.joined_at) }}</strong>
        </div>
        <div v-if="entry.called_at" class="history-panel__meta-card">
          <span>Chamada</span>
          <strong>{{ formatQueueEntryDateTime(entry.called_at) }}</strong>
        </div>
        <div v-if="entry.served_at" class="history-panel__meta-card">
          <span>Atendimento</span>
          <strong>{{ formatQueueEntryDateTime(entry.served_at) }}</strong>
        </div>
        <div v-if="entry.completed_at" class="history-panel__meta-card">
          <span>Encerramento</span>
          <strong>{{ formatQueueEntryDateTime(entry.completed_at) }}</strong>
        </div>
      </div>

      <div v-if="entry.is_active" class="history-panel__live">
        <div class="history-panel__live-card">
          <span>Posicao</span>
          <strong>{{ entry.position ?? '-' }}</strong>
        </div>
        <div class="history-panel__live-card">
          <span>Pessoas a frente</span>
          <strong>{{ entry.people_ahead ?? 0 }}</strong>
        </div>
        <div class="history-panel__live-card">
          <span>Espera estimada</span>
          <strong>{{ waitLabel }}</strong>
        </div>
      </div>

      <div class="history-panel__timeline">
        <div
          v-for="(event, index) in orderedEvents"
          :key="`${event.type}-${event.occurred_at || index}`"
          class="history-panel__event"
        >
          <div class="history-panel__event-icon">
            <q-icon :name="getQueueEntryEventIcon(event.type)" size="18px" />
          </div>
          <div class="history-panel__event-content">
            <div class="history-panel__event-header">
              <StatusPill
                :label="getQueueEntryEventLabel(event.type)"
                :variant="getQueueEntryEventVariant(event.type)"
              />
              <span>{{ formatQueueEntryDateTime(event.occurred_at) }}</span>
            </div>
            <p>{{ buildQueueEntryEventMessage(event.type, { ...entry, payload: event.payload }) }}</p>
            <small v-if="event.actor_user_name">
              Atualizado por {{ event.actor_user_name }}
            </small>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, defineComponent } from 'vue'
import StatusPill from 'src/components/ui/StatusPill.vue'
import {
  buildQueueEntryEventMessage,
  formatQueueEntryDateTime,
  getQueueEntryEventIcon,
  getQueueEntryEventLabel,
  getQueueEntryEventVariant,
  getQueueEntryStatusLabel,
  getQueueEntryStatusVariant,
} from 'src/composables/useQueueEntryHistory'

export default defineComponent({
  name: 'QueueEntryHistoryPanel',

  components: {
    StatusPill,
  },

  props: {
    entry: {
      type: Object,
      default: null,
    },
    events: {
      type: Array,
      default: () => [],
    },
    loading: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
    showPrimaryAction: {
      type: Boolean,
      default: false,
    },
    primaryActionLabel: {
      type: String,
      default: 'Abrir fila',
    },
  },

  emits: ['primary-action'],

  setup(props) {
    const subtitle = computed(() => {
      return [
        props.entry?.establishment?.name,
        props.entry?.service_name,
      ].filter(Boolean).join(' - ') || 'Linha do tempo da entrada'
    })

    const orderedEvents = computed(() => {
      return [...props.events].sort((a, b) => {
        const left = new Date(a?.occurred_at || 0).getTime()
        const right = new Date(b?.occurred_at || 0).getTime()
        return left - right
      })
    })

    const waitLabel = computed(() => {
      const minutes = Number(props.entry?.estimated_wait_minutes)
      if (!Number.isFinite(minutes) || minutes < 0) return '-'
      if (minutes === 0) return 'Menos de 1 min'
      if (minutes < 60) return `${minutes} min`
      const hours = Math.floor(minutes / 60)
      const rest = minutes % 60
      return rest > 0 ? `${hours}h ${rest}min` : `${hours}h`
    })

    return {
      subtitle,
      orderedEvents,
      waitLabel,
      buildQueueEntryEventMessage,
      formatQueueEntryDateTime,
      getQueueEntryEventIcon,
      getQueueEntryEventLabel,
      getQueueEntryEventVariant,
      getQueueEntryStatusLabel,
      getQueueEntryStatusVariant,
    }
  },
})
</script>

<style lang="scss" scoped>
.history-panel {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.history-panel__state {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  color: var(--qm-text-muted);
  text-align: center;
}

.history-panel__state--error {
  color: #dc2626;
}

.history-panel__hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.history-panel__title-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;

  h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--qm-text-primary);
  }
}

.history-panel__subtitle {
  margin: 0.45rem 0 0;
  color: var(--qm-text-muted);
  font-size: 0.875rem;
}

.history-panel__meta-grid,
.history-panel__live {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.875rem;
}

.history-panel__meta-card,
.history-panel__live-card {
  padding: 1rem;
  border-radius: 14px;
  background: var(--qm-bg-secondary);
  display: flex;
  flex-direction: column;
  gap: 0.35rem;

  span {
    font-size: 0.75rem;
    color: var(--qm-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  strong {
    font-size: 0.95rem;
    color: var(--qm-text-primary);
  }
}

.history-panel__timeline {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.history-panel__event {
  display: flex;
  gap: 0.875rem;
  align-items: flex-start;
}

.history-panel__event-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.history-panel__event-content {
  flex: 1;
  min-width: 0;
  padding: 0.25rem 0 1rem;
  border-bottom: 1px solid var(--qm-border);

  p {
    margin: 0.6rem 0 0;
    color: var(--qm-text-secondary);
    line-height: 1.55;
    font-size: 0.875rem;
  }

  small {
    display: inline-block;
    margin-top: 0.5rem;
    color: var(--qm-text-muted);
    font-size: 0.75rem;
  }
}

.history-panel__event-header {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;

  span {
    font-size: 0.75rem;
    color: var(--qm-text-muted);
  }
}
</style>
