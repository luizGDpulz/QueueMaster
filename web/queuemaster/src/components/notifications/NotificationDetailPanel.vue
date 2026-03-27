<template>
  <div class="notification-detail-panel soft-card">
    <div v-if="loading" class="notification-detail-panel__loading">
      <q-spinner-dots color="primary" size="28px" />
    </div>

    <template v-else-if="notification">
      <div class="notification-detail-panel__header">
        <div>
          <q-btn
            v-if="showBack"
            flat
            dense
            no-caps
            icon="arrow_back"
            label="Voltar"
            class="notification-detail-panel__back"
            @click="$emit('back')"
          />
          <h4>{{ notification.title }}</h4>
          <p>{{ notification.body || 'Sem descrição adicional.' }}</p>
        </div>

        <div class="notification-detail-panel__badges">
          <q-badge color="primary" :label="typeLabel" />
          <q-badge
            :color="notification.workflow?.status_color || 'grey-7'"
            :label="notification.workflow?.status_label || 'Atualizado'"
          />
          <q-badge
            :color="notification.read_at ? 'positive' : 'warning'"
            :label="notification.read_at ? 'Lida' : 'Não lida'"
          />
        </div>
      </div>

      <div class="notification-detail-panel__meta-grid">
        <div class="notification-detail-panel__meta-card">
          <span>Recebida</span>
          <strong>{{ formatDateTime(notification.sent_at || notification.created_at) }}</strong>
          <small>{{ notification.workflow?.status_hint || 'Atualização disponível.' }}</small>
        </div>

        <div v-if="details.business_name" class="notification-detail-panel__meta-card">
          <span>Negócio</span>
          <strong>{{ details.business_name }}</strong>
          <small v-if="details.establishment_name">{{ details.establishment_name }}</small>
          <small v-else>Sem estabelecimento vinculado</small>
        </div>

        <div v-if="ownerLabel" class="notification-detail-panel__meta-card">
          <span>{{ ownerLabel }}</span>
          <strong>{{ ownerName }}</strong>
          <small v-if="ownerEmail">{{ ownerEmail }}</small>
          <small v-else>Sem e-mail informado</small>
        </div>
      </div>

      <div v-if="details.request_message" class="notification-detail-panel__section">
        <span class="notification-detail-panel__section-label">Contexto da solicitação</span>
        <pre>{{ details.request_message }}</pre>
      </div>

      <div v-if="details.motivation" class="notification-detail-panel__section">
        <span class="notification-detail-panel__section-label">Motivação</span>
        <p>{{ details.motivation }}</p>
      </div>

      <div v-if="details.notes" class="notification-detail-panel__section">
        <span class="notification-detail-panel__section-label">Observações</span>
        <p>{{ details.notes }}</p>
      </div>

      <div v-if="details.decision_note" class="notification-detail-panel__section notification-detail-panel__section--decision">
        <span class="notification-detail-panel__section-label">Observação da decisão</span>
        <p>{{ details.decision_note }}</p>
      </div>

      <q-separator class="notification-detail-panel__separator" />

      <div class="notification-detail-panel__actions">
        <q-btn
          v-if="!notification.read_at"
          flat
          no-caps
          icon="done"
          label="Marcar como lida"
          :loading="isActionLoading('read')"
          @click="$emit('mark-read', notification)"
        />

        <q-btn
          v-if="canAcceptInvitation"
          color="positive"
          no-caps
          icon="check_circle"
          label="Aceitar"
          :loading="isActionLoading('accept')"
          @click="$emit('accept-invitation', notification)"
        />

        <q-btn
          v-if="canRejectInvitation"
          outline
          color="negative"
          no-caps
          icon="cancel"
          label="Recusar"
          :loading="isActionLoading('reject')"
          @click="openRejectDialog('invitation')"
        />

        <q-btn
          v-if="canApproveManagerRequest"
          color="positive"
          no-caps
          icon="verified"
          label="Aprovar solicitação"
          :loading="isActionLoading('approve-manager')"
          @click="$emit('approve-manager-request', notification)"
        />

        <q-btn
          v-if="canRejectManagerRequest"
          outline
          color="negative"
          no-caps
          icon="gpp_bad"
          label="Recusar solicitação"
          :loading="isActionLoading('reject-manager')"
          @click="openRejectDialog('manager')"
        />

        <q-btn
          v-if="canOpenBusiness"
          color="primary"
          no-caps
          icon="open_in_new"
          label="Ir ao negócio"
          @click="$emit('open-business', notification)"
        />

        <q-btn
          v-else-if="notification.workflow?.status_code === 'access_removed'"
          outline
          disable
          no-caps
          icon="block"
          label="Acesso ao negócio removido"
        />
      </div>
    </template>

    <div v-else class="notification-detail-panel__empty">
      <q-icon name="ads_click" size="44px" />
      <p>Selecione uma notificação para ver os detalhes e agir com segurança.</p>
    </div>

    <q-dialog v-model="showRejectDialog">
      <q-card class="notification-detail-panel__dialog">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ rejectDialogTitle }}</div>
        </q-card-section>

        <q-card-section>
          <p class="notification-detail-panel__dialog-copy">
            Essa observação será enviada na notificação de retorno. Se você deixar em branco, usamos um texto padrão amigável.
          </p>

          <q-input
            v-model="rejectNote"
            outlined
            dense
            type="textarea"
            autogrow
            maxlength="2000"
            label="Observação opcional"
            placeholder="Explique brevemente o motivo da recusa."
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" @click="closeRejectDialog" />
          <q-btn
            color="negative"
            no-caps
            label="Confirmar recusa"
            :loading="rejectDialogLoading"
            @click="submitRejectDialog"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import { computed, defineComponent, ref } from 'vue'

export default defineComponent({
  name: 'NotificationDetailPanel',

  props: {
    notification: {
      type: Object,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    showBack: {
      type: Boolean,
      default: false,
    },
    typeLabel: {
      type: String,
      default: 'Notificação',
    },
    actionState: {
      type: Object,
      default: () => ({ id: null, action: '' }),
    },
  },

  emits: [
    'back',
    'mark-read',
    'accept-invitation',
    'reject-invitation',
    'approve-manager-request',
    'reject-manager-request',
    'open-business',
  ],

  setup(props, { emit }) {
    const showRejectDialog = ref(false)
    const rejectNote = ref('')
    const rejectMode = ref('invitation')

    const details = computed(() => props.notification?.details || {})
    const availableActions = computed(() => props.notification?.workflow?.available_actions || [])
    const canAcceptInvitation = computed(() => availableActions.value.includes('accept_invitation'))
    const canRejectInvitation = computed(() => availableActions.value.includes('reject_invitation'))
    const canApproveManagerRequest = computed(() => availableActions.value.includes('approve_manager_request'))
    const canRejectManagerRequest = computed(() => availableActions.value.includes('reject_manager_request'))
    const canOpenBusiness = computed(() => Boolean(props.notification?.workflow?.can_open_business))

    const ownerLabel = computed(() => {
      if (details.value.kind === 'manager_request') return 'Solicitante'
      if (details.value.kind === 'invitation') {
        return details.value.direction === 'professional_to_business' ? 'Profissional' : 'Responsável'
      }
      return ''
    })

    const ownerName = computed(() => {
      if (details.value.kind === 'manager_request') return details.value.requester_name
      if (details.value.kind === 'invitation') {
        return details.value.direction === 'professional_to_business'
          ? details.value.from_user_name
          : details.value.from_user_name
      }
      return ''
    })

    const ownerEmail = computed(() => {
      if (details.value.kind === 'manager_request') return details.value.requester_email
      if (details.value.kind === 'invitation') {
        return details.value.direction === 'professional_to_business'
          ? details.value.from_user_email
          : details.value.from_user_email
      }
      return ''
    })

    const rejectDialogTitle = computed(() => (
      rejectMode.value === 'manager' ? 'Recusar solicitação de gerência' : 'Recusar vínculo profissional'
    ))

    const rejectDialogLoading = computed(() => (
      rejectMode.value === 'manager'
        ? isActionLoading('reject-manager')
        : isActionLoading('reject')
    ))

    function isActionLoading(action) {
      return props.notification?.id === props.actionState?.id && props.actionState?.action === action
    }

    function formatDateTime(value) {
      if (!value) return 'Não informado'

      return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    }

    function openRejectDialog(mode) {
      rejectMode.value = mode
      rejectNote.value = ''
      showRejectDialog.value = true
    }

    function closeRejectDialog() {
      showRejectDialog.value = false
      rejectNote.value = ''
    }

    function submitRejectDialog() {
      if (!props.notification) return

      const payload = {
        notification: props.notification,
        note: rejectNote.value.trim(),
      }

      closeRejectDialog()

      if (rejectMode.value === 'manager') {
        emit('reject-manager-request', payload)
      } else {
        emit('reject-invitation', payload)
      }
    }

    return {
      details,
      canAcceptInvitation,
      canRejectInvitation,
      canApproveManagerRequest,
      canRejectManagerRequest,
      canOpenBusiness,
      ownerLabel,
      ownerName,
      ownerEmail,
      showRejectDialog,
      rejectNote,
      rejectDialogTitle,
      rejectDialogLoading,
      isActionLoading,
      formatDateTime,
      openRejectDialog,
      closeRejectDialog,
      submitRejectDialog,
    }
  },
})
</script>

<style lang="scss" scoped>
.notification-detail-panel {
  min-height: 100%;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  border: 1px solid color-mix(in srgb, var(--qm-brand) 10%, var(--qm-border));
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--qm-brand) 4%, var(--qm-surface)), var(--qm-surface)),
    var(--qm-surface);
}

.notification-detail-panel__loading,
.notification-detail-panel__empty {
  min-height: 320px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  color: var(--qm-text-muted);
  text-align: center;
}

.notification-detail-panel__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;

  h4 {
    margin: 0 0 0.35rem;
    font-size: 1.1rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    color: var(--qm-text-secondary);
    line-height: 1.5;
  }
}

.notification-detail-panel__back {
  margin-left: -0.5rem;
  margin-bottom: 0.25rem;
  color: var(--qm-text-primary);
}

.notification-detail-panel__badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.notification-detail-panel__meta-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
  margin-top: 1rem;
}

.notification-detail-panel__meta-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.9rem 1rem;
  border-radius: 14px;
  background: color-mix(in srgb, var(--qm-bg-secondary) 86%, var(--qm-surface));
  border: 1px solid color-mix(in srgb, var(--qm-brand) 8%, var(--qm-border));

  span,
  small {
    color: var(--qm-text-muted);
  }

  span {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.35px;
  }

  strong {
    color: var(--qm-text-primary);
    font-size: 0.95rem;
  }

  small {
    font-size: 0.78rem;
  }
}

.notification-detail-panel__section {
  margin-top: 1rem;
  padding: 1rem;
  border-radius: 14px;
  background: color-mix(in srgb, var(--qm-bg-secondary) 86%, var(--qm-surface));
  border: 1px solid color-mix(in srgb, var(--qm-brand) 8%, var(--qm-border));

  p,
  pre {
    margin: 0;
    color: var(--qm-text-secondary);
    line-height: 1.55;
    white-space: pre-wrap;
    font-family: inherit;
  }
}

.notification-detail-panel__section--decision {
  background: color-mix(in srgb, var(--q-negative) 8%, var(--qm-bg-secondary));
  border-color: color-mix(in srgb, var(--q-negative) 18%, var(--qm-border));
}

.notification-detail-panel__section-label {
  display: block;
  margin-bottom: 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--qm-text-muted);
}

.notification-detail-panel__separator {
  margin: 1rem 0;
}

.notification-detail-panel__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;

  :deep(.q-btn--flat) {
    color: var(--qm-text-primary);
  }
}

.notification-detail-panel__dialog {
  width: min(520px, calc(100vw - 2rem));
  border: 1px solid color-mix(in srgb, var(--qm-brand) 10%, var(--qm-border));
}

.notification-detail-panel__dialog-copy {
  margin: 0 0 0.85rem;
  color: var(--qm-text-secondary);
  line-height: 1.5;
}

.notification-detail-panel :deep(.q-separator) {
  opacity: 0.9;
}

.notification-detail-panel :deep(.q-badge.bg-grey-7),
.notification-detail-panel :deep(.q-badge.bg-grey),
.notification-detail-panel :deep(.q-badge[class*='bg-grey-']) {
  background: color-mix(in srgb, var(--qm-bg-tertiary) 92%, var(--qm-surface)) !important;
  color: var(--qm-text-primary) !important;
}

@media (max-width: 900px) {
  .notification-detail-panel {
    padding: 1rem;
  }

  .notification-detail-panel__header {
    flex-direction: column;
  }

  .notification-detail-panel__badges {
    justify-content: flex-start;
  }

  .notification-detail-panel__actions {
    :deep(.q-btn) {
      width: 100%;
    }
  }
}
</style>
