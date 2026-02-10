<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <div>
          <h1 class="page-title">{{ establishment?.name || 'Carregando...' }}</h1>
          <p class="page-subtitle">Detalhes do estabelecimento</p>
        </div>
      </div>
      <div class="header-right" v-if="canManage">
        <q-btn flat icon="edit" label="Editar" no-caps @click="openEdit" />
        <q-btn flat icon="delete" label="Excluir" no-caps color="negative" @click="showDeleteConfirm = true" />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="establishment">
      <!-- Info Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Nome</span>
            <span class="detail-value">{{ establishment.name }}</span>
          </div>
          <div class="detail-item" v-if="establishment.slug">
            <span class="detail-label">Slug</span>
            <span class="detail-value">{{ establishment.slug }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Endereço</span>
            <span class="detail-value">{{ establishment.address || 'Não informado' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Telefone</span>
            <span class="detail-value">{{ establishment.phone || 'Não informado' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value">{{ establishment.email || 'Não informado' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Timezone</span>
            <span class="detail-value">{{ establishment.timezone || 'America/Sao_Paulo' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Horário</span>
            <span class="detail-value">{{ establishment.opens_at || '—' }} - {{ establishment.closes_at || '—' }}</span>
          </div>
          <div class="detail-item" v-if="establishment.description">
            <span class="detail-label">Descrição</span>
            <span class="detail-value">{{ establishment.description }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Criado em</span>
            <span class="detail-value">{{ formatDate(establishment.created_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Services Card -->
      <div class="soft-card">
        <div class="section-header">
          <h2 class="section-title">Serviços</h2>
          <q-btn v-if="canManage" color="primary" icon="add" label="Novo Serviço" no-caps size="sm" @click="openServiceDialog" />
        </div>

        <div v-if="loadingServices" class="loading-state-sm">
          <q-spinner-dots color="primary" size="30px" />
        </div>
        <div v-else-if="services.length === 0" class="empty-state-sm">
          <q-icon name="build" size="40px" />
          <p>Nenhum serviço cadastrado</p>
        </div>
        <div v-else class="list-items">
          <div v-for="service in services" :key="service.id" class="list-item">
            <div class="list-item-info">
              <div class="list-item-avatar">
                <q-icon name="build" size="20px" />
              </div>
              <div class="list-item-details">
                <span class="list-item-name">{{ service.name }}</span>
                <span class="list-item-meta">
                  {{ service.duration_minutes || 30 }} min
                  <template v-if="service.price"> · R$ {{ Number(service.price).toFixed(2) }}</template>
                </span>
              </div>
            </div>
            <div class="list-item-side" v-if="canManage">
              <q-btn flat round dense icon="edit" size="sm" @click.stop="editService(service)" />
              <q-btn flat round dense icon="delete" size="sm" color="negative" @click.stop="confirmDeleteService(service)" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Establishment Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card" style="max-width: 600px;">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Estabelecimento</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" label="Nome *" outlined dense />
          <q-input v-model="editForm.slug" label="Slug" outlined dense class="q-mt-md" />
          <q-input v-model="editForm.description" label="Descrição" outlined dense type="textarea" class="q-mt-md" />
          <q-input v-model="editForm.address" label="Endereço" outlined dense class="q-mt-md" />
          <div class="row q-col-gutter-md q-mt-xs">
            <div class="col-6">
              <q-input v-model="editForm.phone" label="Telefone" outlined dense />
            </div>
            <div class="col-6">
              <q-input v-model="editForm.email" label="Email" outlined dense />
            </div>
          </div>
          <div class="row q-col-gutter-md q-mt-xs">
            <div class="col-6">
              <q-input v-model="editForm.opens_at" label="Abre às" outlined dense type="time" />
            </div>
            <div class="col-6">
              <q-input v-model="editForm.closes_at" label="Fecha às" outlined dense type="time" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveEstablishment" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Service Dialog -->
    <q-dialog v-model="showServiceDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">{{ isEditingService ? 'Editar Serviço' : 'Novo Serviço' }}</div>
          <q-btn flat round dense icon="close" @click="showServiceDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="serviceForm.name" label="Nome *" outlined dense />
          <q-input v-model="serviceForm.description" label="Descrição" outlined dense type="textarea" class="q-mt-md" />
          <div class="row q-col-gutter-md q-mt-xs">
            <div class="col-6">
              <q-input v-model.number="serviceForm.duration" label="Duração (min) *" outlined dense type="number" />
            </div>
            <div class="col-6">
              <q-input v-model.number="serviceForm.price" label="Preço (R$)" outlined dense type="number" step="0.01" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showServiceDialog = false" />
          <q-btn color="primary" :label="isEditingService ? 'Salvar' : 'Criar'" no-caps :loading="savingService" @click="saveService" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete Establish Confirm -->
    <q-dialog v-model="showDeleteConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Confirmar Exclusão</div>
        </q-card-section>
        <q-card-section>
          <p>Tem certeza que deseja excluir <strong>{{ establishment?.name }}</strong>?</p>
          <p style="color: var(--qm-error); font-size: 0.8125rem;">Esta ação não pode ser desfeita.</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteConfirm = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteEstablishment" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete Service Confirm -->
    <q-dialog v-model="showDeleteServiceConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Confirmar Exclusão do Serviço</div>
        </q-card-section>
        <q-card-section>
          <p>Excluir o serviço <strong>{{ selectedService?.name }}</strong>?</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteServiceConfirm = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deletingService" @click="deleteService" />
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
  name: 'EstablishmentDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const establishment = ref(null)
    const services = ref([])
    const loading = ref(true)
    const loadingServices = ref(false)
    const saving = ref(false)
    const savingService = ref(false)
    const deleting = ref(false)
    const deletingService = ref(false)
    const userRole = ref(null)

    const showEditDialog = ref(false)
    const showServiceDialog = ref(false)
    const showDeleteConfirm = ref(false)
    const showDeleteServiceConfirm = ref(false)
    const isEditingService = ref(false)
    const selectedService = ref(null)

    const editForm = ref({ name: '', slug: '', description: '', address: '', phone: '', email: '', opens_at: '', closes_at: '' })
    const serviceForm = ref({ name: '', description: '', duration: 30, price: null })

    const canManage = computed(() => ['admin', 'manager'].includes(userRole.value))

    const goBack = () => router.push('/app/establishments')

    const fetchEstablishment = async () => {
      loading.value = true
      try {
        // Try list and find by id
        const response = await api.get('/establishments')
        const list = response.data?.data?.establishments || response.data?.data || []
        establishment.value = list.find(e => e.id == route.params.id) || null
        if (!establishment.value) {
          $q.notify({ type: 'negative', message: 'Estabelecimento não encontrado' })
          goBack()
        }
      } catch (err) {
        console.error('Erro:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar' })
        goBack()
      } finally {
        loading.value = false
      }
    }

    const fetchServices = async () => {
      loadingServices.value = true
      try {
        const response = await api.get(`/establishments/${route.params.id}/services`)
        if (response.data?.success) {
          services.value = response.data.data?.services || []
        }
      } catch {
        services.value = []
      } finally {
        loadingServices.value = false
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) userRole.value = response.data.data.user.role
      } catch { /* ignore */ }
    }

    const openEdit = () => {
      const e = establishment.value
      editForm.value = {
        name: e?.name || '', slug: e?.slug || '', description: e?.description || '',
        address: e?.address || '', phone: e?.phone || '', email: e?.email || '',
        opens_at: e?.opens_at || '', closes_at: e?.closes_at || ''
      }
      showEditDialog.value = true
    }

    const saveEstablishment = async () => {
      if (!editForm.value.name) { $q.notify({ type: 'warning', message: 'Nome é obrigatório' }); return }
      saving.value = true
      try {
        const payload = {}
        Object.entries(editForm.value).forEach(([k, v]) => { if (v !== '' && v !== null) payload[k] = v })
        await api.put(`/establishments/${route.params.id}`, payload)
        $q.notify({ type: 'positive', message: 'Atualizado com sucesso' })
        showEditDialog.value = false
        fetchEstablishment()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally { saving.value = false }
    }

    const deleteEstablishment = async () => {
      deleting.value = true
      try {
        await api.delete(`/establishments/${route.params.id}`)
        $q.notify({ type: 'positive', message: 'Excluído com sucesso' })
        goBack()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally { deleting.value = false }
    }

    // Service CRUD
    const openServiceDialog = () => {
      isEditingService.value = false
      serviceForm.value = { name: '', description: '', duration: 30, price: null }
      showServiceDialog.value = true
    }

    const editService = (service) => {
      isEditingService.value = true
      selectedService.value = service
      serviceForm.value = {
        name: service.name || '', description: service.description || '',
        duration: service.duration_minutes || 30, price: service.price ? Number(service.price) : null
      }
      showServiceDialog.value = true
    }

    const confirmDeleteService = (service) => {
      selectedService.value = service
      showDeleteServiceConfirm.value = true
    }

    const saveService = async () => {
      if (!serviceForm.value.name) { $q.notify({ type: 'warning', message: 'Nome é obrigatório' }); return }
      savingService.value = true
      try {
        const payload = {
          name: serviceForm.value.name, duration: serviceForm.value.duration,
          establishment_id: parseInt(route.params.id)
        }
        if (serviceForm.value.description) payload.description = serviceForm.value.description
        if (serviceForm.value.price !== null && serviceForm.value.price !== '') payload.price = serviceForm.value.price

        if (isEditingService.value) {
          await api.put(`/services/${selectedService.value.id}`, payload)
          $q.notify({ type: 'positive', message: 'Serviço atualizado' })
        } else {
          await api.post('/services', payload)
          $q.notify({ type: 'positive', message: 'Serviço criado' })
        }
        showServiceDialog.value = false
        fetchServices()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally { savingService.value = false }
    }

    const deleteService = async () => {
      deletingService.value = true
      try {
        await api.delete(`/services/${selectedService.value.id}`)
        $q.notify({ type: 'positive', message: 'Serviço excluído' })
        showDeleteServiceConfirm.value = false
        fetchServices()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally { deletingService.value = false }
    }

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR') : '-'

    onMounted(async () => {
      await fetchUserRole()
      fetchEstablishment()
      fetchServices()
    })

    return {
      establishment, services, loading, loadingServices, saving, savingService,
      deleting, deletingService, canManage,
      showEditDialog, showServiceDialog, showDeleteConfirm, showDeleteServiceConfirm,
      isEditingService, selectedService, editForm, serviceForm,
      goBack, openEdit, saveEstablishment, deleteEstablishment,
      openServiceDialog, editService, confirmDeleteService, saveService, deleteService,
      formatDate
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';
</style>
