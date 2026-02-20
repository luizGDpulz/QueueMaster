<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <h1 class="page-title">{{ isNew ? 'Novo Profissional' : (editForm.name || '\u00A0') }}</h1>
      </div>
      <div class="header-right" v-if="canManage && (isNew || hasChanges)">
        <q-btn flat label="Cancelar" no-caps @click="isNew ? goBack() : cancelChanges()" />
        <q-btn color="primary" :label="isNew ? 'Criar' : 'Salvar'" no-caps :loading="saving" @click="save" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">{{ isNew ? 'Cadastrar novo profissional' : 'Detalhes do profissional' }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="!isNew && loadingProfessional" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else>
      <!-- Form Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações do Profissional</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Nome *</span>
            <q-input v-model="editForm.name" :readonly="!canManage" dense outlined class="detail-value-input" />
          </div>
          <div class="detail-item">
            <span class="detail-label">Especialização</span>
            <q-input v-model="editForm.specialization" :readonly="!canManage" dense outlined class="detail-value-input" />
          </div>
        </div>
      </div>

      <!-- Delete button -->
      <div v-if="!isNew && canManage" class="q-mt-lg">
        <q-btn
          outline
          color="negative"
          icon="delete"
          label="Excluir Profissional"
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
          <p>Excluir o profissional <strong>{{ editForm.name }}</strong>?</p>
          <p style="color: var(--qm-error); font-size: 0.8125rem;">Esta ação não pode ser desfeita.</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteConfirm = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteProfessional" />
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
  name: 'ProfessionalDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const isNew = computed(() => route.params.professionalId === 'new')
    const establishmentId = computed(() => route.params.id)

    const professional = ref(null)
    const originalForm = ref(null)
    const loadingProfessional = ref(false)
    const saving = ref(false)
    const deleting = ref(false)
    const userRole = ref(null)
    const showDeleteConfirm = ref(false)

    const editForm = ref({
      name: '',
      specialization: ''
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
        specialization: data?.specialization || ''
      }
      editForm.value = { ...formData }
      originalForm.value = { ...formData }
    }

    const fetchProfessional = async () => {
      if (isNew.value) return
      loadingProfessional.value = true
      try {
        const response = await api.get(`/professionals/${route.params.professionalId}`)
        if (response.data?.success) {
          professional.value = response.data.data?.professional || response.data.data
          setFormData(professional.value)
        }
      } catch (err) {
        console.error('Erro:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar profissional' })
        goBack()
      } finally {
        loadingProfessional.value = false
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) userRole.value = response.data.data.user.role
      } catch { /* ignore */ }
    }

    const cancelChanges = () => {
      setFormData(professional.value)
    }

    const save = async () => {
      if (!editForm.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }

      saving.value = true
      try {
        const payload = {
          name: editForm.value.name,
          establishment_id: parseInt(establishmentId.value)
        }
        if (editForm.value.specialization) payload.specialization = editForm.value.specialization

        if (isNew.value) {
          await api.post('/professionals', payload)
          $q.notify({ type: 'positive', message: 'Profissional criado com sucesso' })
        } else {
          await api.put(`/professionals/${route.params.professionalId}`, payload)
          $q.notify({ type: 'positive', message: 'Profissional atualizado com sucesso' })
        }
        goBack()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const deleteProfessional = async () => {
      deleting.value = true
      try {
        await api.delete(`/professionals/${route.params.professionalId}`)
        $q.notify({ type: 'positive', message: 'Profissional excluído com sucesso' })
        goBack()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally {
        deleting.value = false
      }
    }

    onMounted(async () => {
      await fetchUserRole()
      await fetchProfessional()
    })

    return {
      isNew, professional, loadingProfessional, saving, deleting, canManage, hasChanges,
      editForm, showDeleteConfirm,
      goBack, cancelChanges, save, deleteProfessional
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
