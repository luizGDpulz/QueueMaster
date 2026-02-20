<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <h1 class="page-title">{{ isNew ? 'Novo Serviço' : (editForm.name || '\u00A0') }}</h1>
      </div>
      <div class="header-right" v-if="canManage && (isNew || hasChanges)">
        <q-btn flat label="Cancelar" no-caps @click="isNew ? goBack() : cancelChanges()" />
        <q-btn color="primary" :label="isNew ? 'Criar' : 'Salvar'" no-caps :loading="saving" @click="save" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">{{ isNew ? 'Cadastrar novo serviço' : 'Detalhes do serviço' }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="!isNew && loadingService" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else>
      <!-- Form Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações do Serviço</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Nome *</span>
            <q-input v-model="editForm.name" :readonly="!canManage" dense outlined class="detail-value-input" />
          </div>
          <div class="detail-item">
            <span class="detail-label">Duração (min) *</span>
            <q-input v-model.number="editForm.duration" :readonly="!canManage" dense outlined type="number" class="detail-value-input" />
          </div>
          <div class="detail-item">
            <span class="detail-label">Preço (R$)</span>
            <q-input v-model.number="editForm.price" :readonly="!canManage" dense outlined type="number" step="0.01" class="detail-value-input" />
          </div>
          <div class="detail-item" style="grid-column: 1 / -1;">
            <span class="detail-label">Descrição</span>
            <q-input v-model="editForm.description" :readonly="!canManage" dense outlined type="textarea" class="detail-value-input" />
          </div>
        </div>
      </div>

      <!-- Delete button -->
      <div v-if="!isNew && canManage" class="q-mt-lg">
        <q-btn
          outline
          color="negative"
          icon="delete"
          label="Excluir Serviço"
          no-caps
          @click="showDeleteConfirm = true"
        />
      </div>
    </template>

    <!-- Delete Confirm -->
    <q-dialog v-model="showDeleteConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Confirmar Exclusão</div>
        </q-card-section>
        <q-card-section>
          <p>Excluir o serviço <strong>{{ editForm.name }}</strong>?</p>
          <p style="color: var(--qm-error); font-size: 0.8125rem;">Esta ação não pode ser desfeita.</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteConfirm = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteService" />
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
import { isEqual } from 'lodash-es'

export default defineComponent({
  name: 'ServiceDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const isNew = computed(() => route.params.serviceId === 'new')
    const establishmentId = computed(() => route.params.id)

    const service = ref(null)
    const originalForm = ref(null)
    const loadingService = ref(false)
    const saving = ref(false)
    const deleting = ref(false)
    const userRole = ref(null)
    const showDeleteConfirm = ref(false)

    const editForm = ref({
      name: '',
      description: '',
      duration: 30,
      price: null
    })

    const canManage = computed(() => ['admin', 'manager'].includes(userRole.value))
    const hasChanges = computed(() => {
      if (originalForm.value === null) return false
      return !isEqual(originalForm.value, editForm.value)
    })

    const goBack = () => router.push({ name: 'establishment-detail', params: { id: establishmentId.value } })

    const setFormData = (data) => {
      const formData = {
        name: data?.name || '',
        description: data?.description || '',
        duration: data?.duration_minutes || 30,
        price: data?.price ? Number(data.price) : null
      }
      editForm.value = { ...formData }
      originalForm.value = { ...formData }
    }

    const fetchService = async () => {
      if (isNew.value) return
      loadingService.value = true
      try {
        const response = await api.get(`/services/${route.params.serviceId}`)
        if (response.data?.success) {
          service.value = response.data.data?.service || response.data.data
          setFormData(service.value)
        }
      } catch (err) {
        console.error('Erro:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar serviço' })
        goBack()
      } finally {
        loadingService.value = false
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) userRole.value = response.data.data.user.role
      } catch { /* ignore */ }
    }

    const cancelChanges = () => {
      setFormData(service.value)
    }

    const save = async () => {
      if (!editForm.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }
      if (!editForm.value.duration || editForm.value.duration <= 0) {
        $q.notify({ type: 'warning', message: 'Duração é obrigatória' })
        return
      }

      saving.value = true
      try {
        const payload = {
          name: editForm.value.name,
          duration: editForm.value.duration,
          establishment_id: parseInt(establishmentId.value)
        }
        if (editForm.value.description) payload.description = editForm.value.description
        if (editForm.value.price !== null && editForm.value.price !== '') payload.price = editForm.value.price

        if (isNew.value) {
          await api.post('/services', payload)
          $q.notify({ type: 'positive', message: 'Serviço criado com sucesso' })
        } else {
          await api.put(`/services/${route.params.serviceId}`, payload)
          $q.notify({ type: 'positive', message: 'Serviço atualizado com sucesso' })
        }
        goBack()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const deleteService = async () => {
      deleting.value = true
      try {
        await api.delete(`/services/${route.params.serviceId}`)
        $q.notify({ type: 'positive', message: 'Serviço excluído com sucesso' })
        goBack()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally {
        deleting.value = false
      }
    }

    onMounted(async () => {
      await fetchUserRole()
      await fetchService()
    })

    return {
      isNew, service, loadingService, saving, deleting, canManage, hasChanges,
      editForm, showDeleteConfirm,
      goBack, cancelChanges, save, deleteService
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

.detail-value-input {
  flex-grow: 1;
}
</style>
