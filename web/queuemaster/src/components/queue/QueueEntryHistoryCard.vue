<template>
  <div class="history-card soft-card" @click="$emit('open', item)">
    <div class="history-card__header">
      <div class="history-card__identity">
        <div class="history-card__title-row">
          <h3>{{ item.queue?.name || 'Fila' }}</h3>
          <StatusPill
            :label="getQueueEntryStatusLabel(item.status)"
            :variant="getQueueEntryStatusVariant(item.status)"
          />
        </div>
        <p class="history-card__subtitle">
          {{ subtitle }}
        </p>
      </div>

      <q-btn
        v-if="showQueueAction"
        flat
        dense
        no-caps
        color="primary"
        :label="queueActionLabel"
        @click.stop="$emit('open-queue', item)"
      />
    </div>

    <div class="history-card__body">
      <div class="history-card__event-line">
        <StatusPill
          :label="getQueueEntryEventLabel(item.last_event_type)"
          :variant="getQueueEntryEventVariant(item.last_event_type)"
        />
        <span class="history-card__time">{{ formatQueueEntryDateTime(item.last_event_at || item.joined_at) }}</span>
      </div>

      <p class="history-card__message">
        {{ buildQueueEntryEventMessage(item.last_event_type, item) }}
      </p>
    </div>

    <div class="history-card__footer">
      <span>{{ item.events_count || 0 }} evento(s)</span>
      <span>{{ item.entry_public_id }}</span>
    </div>
  </div>
</template>

<script>
import { computed, defineComponent } from 'vue'
import StatusPill from 'src/components/ui/StatusPill.vue'
import {
  buildQueueEntryEventMessage,
  formatQueueEntryDateTime,
  getQueueEntryEventLabel,
  getQueueEntryEventVariant,
  getQueueEntryStatusLabel,
  getQueueEntryStatusVariant,
} from 'src/composables/useQueueEntryHistory'

export default defineComponent({
  name: 'QueueEntryHistoryCard',

  components: {
    StatusPill,
  },

  props: {
    item: {
      type: Object,
      required: true,
    },
    showQueueAction: {
      type: Boolean,
      default: false,
    },
    queueActionLabel: {
      type: String,
      default: 'Abrir fila',
    },
  },

  emits: ['open', 'open-queue'],

  setup(props) {
    const subtitle = computed(() => {
      return [
        props.item.establishment?.name,
        props.item.service_name,
      ].filter(Boolean).join(' - ') || 'Historico da participacao'
    })

    return {
      subtitle,
      buildQueueEntryEventMessage,
      formatQueueEntryDateTime,
      getQueueEntryEventLabel,
      getQueueEntryEventVariant,
      getQueueEntryStatusLabel,
      getQueueEntryStatusVariant,
    }
  },
})
</script>

<style lang="scss" scoped>
.history-card {
  padding: 1.25rem;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: var(--qm-shadow-lg);
  }
}

.history-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.history-card__identity {
  min-width: 0;
  flex: 1;
}

.history-card__title-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;

  h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--qm-text-primary);
  }
}

.history-card__subtitle {
  margin: 0.4rem 0 0;
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

.history-card__body {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.history-card__event-line {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;
}

.history-card__time {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.history-card__message {
  margin: 0;
  font-size: 0.875rem;
  color: var(--qm-text-secondary);
  line-height: 1.5;
}

.history-card__footer {
  margin-top: 1rem;
  padding-top: 0.875rem;
  border-top: 1px solid var(--qm-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  flex-wrap: wrap;
}
</style>
