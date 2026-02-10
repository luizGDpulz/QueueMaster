<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <div>
          <h1 class="page-title">{{ queue?.name || 'Carregando...' }}</h1>
          <p class="page-subtitle">Detalhes da fila</p>
        </div>
      </div>
      <div class="header-right">
        <q-btn
          v-if="queue?.status === 'open'"
          color="primary"
          icon="person_add"
          label="Entrar na Fila"
          no-caps
          @click="joinQueue"
        />
        <q-btn v-if="canManage" flat icon="edit" label="Editar" no-caps @click="openEdit" />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="queue">
      <!-- Info Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Nome</span>
            <span class="detail-value">{{ queue.name }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Estabelecimento</span>
            <span class="detail-value">{{ queue.establishment_name || 'N/A' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Serviço</span>
            <span class="detail-value">{{ queue.service_name || 'Geral' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Status</span>
            <q-badge :color="queue.status === 'open' ? 'positive' : 'grey'">
              {{ queue.status === 'open' ? 'Aberta' : 'Fechada' }}
            </q-badge>
          </div>
          <div class="detail-item">
            <span class="detail-label">Pessoas Aguardando</span>
            <span class="detail-value">{{ queue.waiting_count || 0 }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Criada em</span>
            <span class="detail-value">{{ formatDate(queue.created_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Statistics Card -->
      <div v-if="queueStatus" class="soft-card q-mb-lg">
        <h2 class="section-title">Estatísticas</h2>
        <div class="stats-grid">
          <div class="stat-box">
            <span class="stat-number">{{ queueStatus.statistics?.total_waiting || 0 }}</span>
            <span class="stat-text">Aguardando</span>
          </div>
          <div class="stat-box">
            <span class="stat-number">{{ queueStatus.statistics?.total_being_served || 0 }}</span>
            <span class="stat-text">Sendo atendidos</span>
          </div>
          <div class="stat-box">
            <span class="stat-number">{{ queueStatus.statistics?.total_completed_today || 0 }}</span>
            <span class="stat-text">Concluídos hoje</span>
          </div>
          <div class="stat-box">
            <span class="stat-number">{{ queueStatus.statistics?.average_wait_time_minutes || 0 }} min</span>
            <span class="stat-text">Tempo médio</span>
          </div>
        </div>

        <!-- User Position -->
        <div v-if="queueStatus.user_entry" class="highlight-box q-mt-md">
          <q-icon name="person" size="20px" />
          <span>Você está na posição <strong>{{ queueStatus.user_entry.position }}</strong></span>
          <span style="color: var(--qm-text-muted); font-size: 0.8125rem;">(~{{ queueStatus.user_entry.estimated_wait_minutes || '?' }} min)</span>
        </div>
      </div>

      <!-- Actions Card (for managers) -->
      <div v-if="canManage && queue.status === 'open'" class="soft-card">
        <h2 class="section-title">Ações</h2>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
          <q-btn
            v-if="queue.waiting_count > 0"
            color="warning"
            icon="campaign"
            label="Chamar Próximo"
            no-caps
            @click="callNext"
          />
          <q-btn flat icon="delete" label="Excluir Fila" no-caps color="negative" @click="showDeleteConfirm = true" />
        </div>
      </div>
    </template>

    <!-- Edit Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Fila</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" label="Nome da Fila *" outlined dense />
          <q-select v-model="editForm.status" label="Status" outlined dense :options="statusOptions" emit-value map-options class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveQueue" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete Confirm -->
    <q-dialog v-model="showDeleteConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Confirmar Exclusão</div>
        </q-card-section>
        <q-card-section>
          <p>Tem certeza que deseja excluir a fila <strong>{{ queue?.name }}</strong>?</p>
          <p style="color: var(--qm-error); font-size: 0.8125rem;">Esta ação não pode ser desfeita.</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteConfirm = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteQueue" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'QueueDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const queue = ref(null)
    const queueStatus = ref(null)
    const loading = ref(true)
    const saving = ref(false)
    const deleting = ref(false)
    const userRole = ref(null)
    const showEditDialog = ref(false)
    const showDeleteConfirm = ref(false)
    const editForm = ref({ name: '', status: 'open' })

    const statusOptions = [
      { label: 'Aberta', value: 'open' },
      { label: 'Fechada', value: 'closed' }
    ]

    const canManage = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))

    const goBack = () => router.push('/app/queues')

    const fetchQueue = async () => {
      loading.value = true
      try {
        // The list endpoint may not have a single-get. Try status endpoint.
        const response = await api.get(`/queues/${route.params.id}/status`)
        if (response.data?.success) {
          queueStatus.value = response.data.data
          // Build a queue object from status if individual endpoint doesn't exist
          if (response.data.data?.queue) {
            queue.value = response.data.data.queue
          }
        }
      } catch {
        // Fallback: try list and find by ID
        try {
          const listRes = await api.get('/queues')
          const queues = listRes.data?.data?.queues || listRes.data?.data || []
          queue.value = queues.find(q => q.id == route.params.id) || null
          if (!queue.value) {
            $q.notify({ type: 'negative', message: 'Fila não encontrada' })
            goBack()
          }
        } catch (err) {
          console.error('Erro ao buscar fila:', err)
          $q.notify({ type: 'negative', message: 'Erro ao carregar fila' })
          goBack()
        }
      } finally {
        loading.value = false
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          userRole.value = response.data.data.user.role
        }
      } catch { /* ignore */ }
    }

    const joinQueue = async () => {
      try {
        await api.post(`/queues/${route.params.id}/join`)
        $q.notify({ type: 'positive', message: 'Você entrou na fila com sucesso!' })
        fetchQueue()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao entrar na fila' })
      }
    }

    const callNext = async () => {
      try {
        const payload = { establishment_id: queue.value?.establishment_id }
        const response = await api.post(`/queues/${route.params.id}/call-next`, payload)
        if (response.data?.success && response.data?.data?.called) {
          const called = response.data.data.called
          $q.notify({ type: 'positive', message: `Chamando: ${called.user_name || 'Usuário #' + called.user_id}`, timeout: 5000 })
        } else {
          $q.notify({ type: 'info', message: 'Próximo da fila foi chamado' })
        }
        fetchQueue()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao chamar próximo' })
      }
    }

    const openEdit = () => {
      editForm.value = {
        name: queue.value?.name || '',
        status: queue.value?.status || 'open'
      }
      showEditDialog.value = true
    }

    const saveQueue = async () => {
      if (!editForm.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }
      saving.value = true
      try {
        await api.put(`/queues/${route.params.id}`, editForm.value)
        $q.notify({ type: 'positive', message: 'Fila atualizada com sucesso' })
        showEditDialog.value = false
        fetchQueue()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const deleteQueue = async () => {
      deleting.value = true
      try {
        await api.delete(`/queues/${route.params.id}`)
        $q.notify({ type: 'positive', message: 'Fila excluída com sucesso' })
        goBack()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally {
        deleting.value = false
      }
    }

    const formatDate = (d) => d ? new Date(d).toLocaleString('pt-BR') : '-'

    onMounted(async () => {
      await fetchUserRole()
      fetchQueue()
    })

    return {
      queue, queueStatus, loading, saving, deleting, canManage,
      showEditDialog, showDeleteConfirm, editForm, statusOptions,
      goBack, joinQueue, callNext, openEdit, saveQueue, deleteQueue, formatDate
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';
</style>
