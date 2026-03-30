import { api } from 'boot/axios'

export async function fetchQueueEntryHistory(params = {}) {
  const response = await api.get('/queue-entries/history', { params })
  return {
    items: response.data?.data || [],
    pagination: response.data?.meta?.pagination || null,
  }
}

export async function fetchCurrentQueueEntry() {
  try {
    const response = await api.get('/queue-entries/current')
    return response.data?.data?.entry || null
  } catch (error) {
    if (error?.response?.status === 404) {
      return null
    }
    throw error
  }
}

export async function fetchQueueEntryTimeline(publicId, queueId = null) {
  const endpoint = queueId
    ? `/queues/${queueId}/entries/${publicId}/events`
    : `/queue-entries/${publicId}/events`

  const response = await api.get(endpoint)
  return {
    entry: response.data?.data?.entry || null,
    events: response.data?.data?.events || [],
  }
}

export function getQueueEntryStatusLabel(status) {
  const map = {
    waiting: 'Aguardando',
    called: 'Chamado',
    serving: 'Em atendimento',
    done: 'Concluido',
    completed: 'Concluido',
    left: 'Saiu da fila',
    cancelled: 'Cancelado',
    no_show: 'Nao compareceu',
  }

  return map[status] || 'Atualizado'
}

export function getQueueEntryStatusVariant(status) {
  const map = {
    waiting: 'warning',
    called: 'info',
    serving: 'primary',
    done: 'positive',
    completed: 'positive',
    left: 'grey',
    cancelled: 'negative',
    no_show: 'negative',
  }

  return map[status] || 'grey'
}

export function getQueueEntryEventLabel(type) {
  const map = {
    joined: 'Entrada',
    next_up: 'Proximo',
    called: 'Chamado',
    serving_started: 'Atendimento',
    completed: 'Concluido',
    left: 'Saida',
    cancelled: 'Cancelada',
    no_show: 'Ausencia',
    requeued: 'Retorno',
    updated: 'Atualizada',
  }

  return map[type] || 'Atualizada'
}

export function getQueueEntryEventVariant(type) {
  const map = {
    joined: 'positive',
    next_up: 'warning',
    called: 'info',
    serving_started: 'primary',
    completed: 'positive',
    left: 'grey',
    cancelled: 'negative',
    no_show: 'negative',
    requeued: 'info',
    updated: 'grey',
  }

  return map[type] || 'grey'
}

export function getQueueEntryEventIcon(type) {
  const map = {
    joined: 'login',
    next_up: 'schedule',
    called: 'campaign',
    serving_started: 'headset_mic',
    completed: 'check_circle',
    left: 'logout',
    cancelled: 'cancel',
    no_show: 'person_off',
    requeued: 'replay',
    updated: 'notifications',
  }

  return map[type] || 'notifications'
}

export function buildQueueEntryEventMessage(type, context = {}) {
  const queueName = context.queue_name || context.queue?.name || 'esta fila'
  const professionalName = context.professional_name || context.professionalName
  const payload = context.payload && typeof context.payload === 'object'
    ? context.payload
    : {}

  switch (type) {
    case 'joined':
      return `Entrada confirmada em ${queueName}.`
    case 'next_up': {
      const peopleAhead = Number(payload.people_ahead)
      if (Number.isFinite(peopleAhead) && peopleAhead > 0) {
        return `Seu atendimento esta proximo em ${queueName}. Pessoas a frente: ${peopleAhead}.`
      }
      return `Seu atendimento esta proximo em ${queueName}.`
    }
    case 'called':
      return `Voce foi chamado em ${queueName}.`
    case 'serving_started':
      return professionalName
        ? `Atendimento iniciado em ${queueName} com ${professionalName}.`
        : `Atendimento iniciado em ${queueName}.`
    case 'completed':
      return `Atendimento concluido em ${queueName}.`
    case 'left':
      return `A participacao foi encerrada pelo cliente em ${queueName}.`
    case 'cancelled':
      return `A entrada foi cancelada pela equipe em ${queueName}.`
    case 'no_show':
      return `A entrada foi marcada como ausencia em ${queueName}.`
    case 'requeued':
      return `A entrada voltou para a fila em ${queueName}.`
    default:
      return `O fluxo desta fila foi atualizado em ${queueName}.`
  }
}

export function formatQueueEntryDateTime(value) {
  if (!value) return '-'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatQueueEntryDate(value) {
  if (!value) return '-'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  return date.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}
