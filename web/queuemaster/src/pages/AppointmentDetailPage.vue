<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <div>
          <h1 class="page-title">Agendamento #{{ route.params.id }}</h1>
          <p class="page-subtitle">Detalhes do agendamento</p>
        </div>
      </div>
      <div class="header-right" v-if="appointment">
        <q-btn v-if="canEdit" flat icon="edit" label="Editar" no-caps @click="openEdit" />
        <q-btn-dropdown
          v-if="canChangeStatus"
          color="primary"
          label="Alterar Status"
          no-caps
          icon="swap_horiz"
        >
          <q-list dense>
            <q-item clickable v-close-popup @click="updateStatus('checked_in')" v-if="appointment.status === 'booked'">
              <q-item-section>Check-in</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="updateStatus('in_progress')" v-if="appointment.status === 'checked_in'">
              <q-item-section>Iniciar</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="updateStatus('completed')" v-if="appointment.status === 'in_progress'">
              <q-item-section>Concluir</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="updateStatus('no_show')" v-if="appointment.status === 'booked'">
              <q-item-section>Não compareceu</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="updateStatus('cancelled')" v-if="['booked', 'checked_in'].includes(appointment.status)">
              <q-item-section class="text-negative">Cancelar</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="appointment">
      <!-- Status Banner -->
      <div class="soft-card q-mb-lg" :style="{ borderLeft: '4px solid var(--q-' + getStatusColor(appointment.status) + ')' }">
        <div style="display: flex; align-items: center; gap: 1rem;">
          <q-badge :color="getStatusColor(appointment.status)" class="status-badge-lg">
            {{ getStatusLabel(appointment.status) }}
          </q-badge>
          <span class="detail-value">{{ formatDateTime(appointment.start_at) }}</span>
        </div>
      </div>

      <!-- Info Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Paciente</span>
            <span class="detail-value">{{ appointment.user_name || 'Usuário #' + appointment.user_id }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Profissional</span>
            <span class="detail-value">{{ appointment.professional_name || 'Profissional #' + appointment.professional_id }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Serviço</span>
            <span class="detail-value">{{ appointment.service_name || 'Serviço #' + appointment.service_id }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Estabelecimento</span>
            <span class="detail-value">{{ appointment.establishment_name || 'ID: ' + appointment.establishment_id }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Início</span>
            <span class="detail-value">{{ formatDateTime(appointment.start_at) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Término</span>
            <span class="detail-value">{{ formatDateTime(appointment.end_at) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Criado em</span>
            <span class="detail-value">{{ formatDateTime(appointment.created_at) }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Agendamento</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.start_at" label="Data e Hora *" outlined dense type="datetime-local" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveAppointment" />
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
  name: 'AppointmentDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const appointment = ref(null)
    const loading = ref(true)
    const saving = ref(false)
    const userRole = ref(null)
    const userId = ref(null)
    const showEditDialog = ref(false)
    const editForm = ref({ start_at: '' })

    const canManage = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))
    const canEdit = computed(() => {
      if (canManage.value) return true
      return appointment.value?.user_id === userId.value && appointment.value?.status === 'booked'
    })
    const canChangeStatus = computed(() => {
      return canManage.value && appointment.value && !['completed', 'cancelled', 'no_show'].includes(appointment.value.status)
    })

    const goBack = () => router.push('/app/appointments')

    const fetchAppointment = async () => {
      loading.value = true
      try {
        const response = await api.get(`/appointments/${route.params.id}`)
        if (response.data?.success) {
          appointment.value = response.data.data?.appointment || response.data.data
        }
      } catch {
        // Fallback: fetch from list
        try {
          const listRes = await api.get('/appointments')
          const list = listRes.data?.data || []
          appointment.value = list.find(a => a.id == route.params.id) || null
          if (!appointment.value) {
            $q.notify({ type: 'negative', message: 'Agendamento não encontrado' })
            goBack()
          }
        } catch (err) {
          console.error('Erro ao buscar agendamento:', err)
          $q.notify({ type: 'negative', message: 'Erro ao carregar agendamento' })
          goBack()
        }
      } finally {
        loading.value = false
      }
    }

    const fetchUser = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          userRole.value = response.data.data.user.role
          userId.value = response.data.data.user.id
        }
      } catch { /* ignore */ }
    }

    const updateStatus = async (status) => {
      try {
        await api.put(`/appointments/${route.params.id}`, { status })
        $q.notify({ type: 'positive', message: 'Status atualizado com sucesso' })
        fetchAppointment()
      } catch {
        $q.notify({ type: 'negative', message: 'Erro ao atualizar status' })
      }
    }

    const openEdit = () => {
      editForm.value = {
        start_at: appointment.value?.start_at?.replace(' ', 'T').slice(0, 16) || ''
      }
      showEditDialog.value = true
    }

    const saveAppointment = async () => {
      if (!editForm.value.start_at) {
        $q.notify({ type: 'warning', message: 'Data é obrigatória' })
        return
      }
      saving.value = true
      try {
        await api.put(`/appointments/${route.params.id}`, {
          start_at: editForm.value.start_at.replace('T', ' ') + ':00'
        })
        $q.notify({ type: 'positive', message: 'Agendamento atualizado com sucesso' })
        showEditDialog.value = false
        fetchAppointment()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const getStatusColor = (status) => {
      const colors = { booked: 'blue', checked_in: 'orange', in_progress: 'purple', completed: 'positive', no_show: 'deep-orange', cancelled: 'negative' }
      return colors[status] || 'grey'
    }

    const getStatusLabel = (status) => {
      const labels = { booked: 'Agendado', checked_in: 'Check-in', in_progress: 'Em andamento', completed: 'Concluído', no_show: 'Não compareceu', cancelled: 'Cancelado' }
      return labels[status] || status
    }

    const formatDateTime = (d) => d ? new Date(d).toLocaleString('pt-BR') : '-'

    onMounted(async () => {
      await fetchUser()
      fetchAppointment()
    })

    return {
      route, appointment, loading, saving, userRole, userId,
      showEditDialog, editForm,
      canManage, canEdit, canChangeStatus,
      goBack, updateStatus, openEdit, saveAppointment,
      getStatusColor, getStatusLabel, formatDateTime
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

.status-badge-lg {
  font-size: 0.8125rem;
  padding: 0.375rem 0.875rem;
}
</style>
