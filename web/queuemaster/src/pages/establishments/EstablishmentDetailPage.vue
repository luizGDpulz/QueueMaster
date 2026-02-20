<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <h1 class="page-title">{{ editForm?.name || '\u00A0' }}</h1>
      </div>
      <div class="header-right" v-if="hasChanges && canManage && activeTab === 'info'">
        <q-btn flat label="Cancelar" no-caps @click="cancelChanges" />
        <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveEstablishment" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Detalhes do estabelecimento</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="establishment">
      <!-- Tabbed Navigation -->
      <div class="tabs-container soft-card">
        <q-tabs
          v-model="activeTab"
          dense
          class="establishment-tabs"
          active-color="primary"
          indicator-color="primary"
          align="left"
          narrow-indicator
        >
          <q-tab name="info" icon="info" label="Informações" no-caps />
          <q-tab name="services" icon="build" label="Serviços" no-caps />
          <q-tab name="professionals" icon="badge" label="Profissionais" no-caps />
        </q-tabs>

        <q-separator style="margin-top: 10px;" />

        <q-tab-panels v-model="activeTab" animated class="tab-panels">
          <!-- ================================================================ -->
          <!-- Tab: Informações -->
          <!-- ================================================================ -->
          <q-tab-panel name="info" class="tab-panel">
            <div class="panel-header">
              <div class="panel-header-left">
                <h3>Informações do Estabelecimento</h3>
                <p>Dados cadastrais e configurações</p>
              </div>
              <div class="panel-header-right" v-if="canManage">
                <q-btn
                  outline
                  color="negative"
                  icon="delete"
                  label="Excluir"
                  no-caps
                  size="sm"
                  @click="showDeleteConfirm = true"
                />
              </div>
            </div>

            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Nome</span>
                <q-input v-model="editForm.name" :readonly="!canManage" dense outlined class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Slug</span>
                <q-input v-model="editForm.slug" :readonly="!canManage" dense outlined class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Endereço</span>
                <q-input v-model="editForm.address" :readonly="!canManage" dense outlined class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Telefone</span>
                <q-input v-model="editForm.phone" :readonly="!canManage" dense outlined class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Email</span>
                <q-input v-model="editForm.email" :readonly="!canManage" dense outlined class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Timezone</span>
                <q-input v-model="editForm.timezone" :readonly="!canManage" dense outlined class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Horário</span>
                <div class="detail-value-group">
                    <q-input v-model="editForm.opens_at" :readonly="!canManage" dense outlined type="time" />
                    <span class="q-mx-sm">-</span>
                    <q-input v-model="editForm.closes_at" :readonly="!canManage" dense outlined type="time" />
                </div>
              </div>
              <div class="detail-item">
                <span class="detail-label">Descrição</span>
                <q-input v-model="editForm.description" :readonly="!canManage" dense outlined type="textarea" class="detail-value-input" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Criado em</span>
                <span class="detail-value">{{ formatDate(establishment.created_at) }}</span>
              </div>
            </div>
          </q-tab-panel>

          <!-- ================================================================ -->
          <!-- Tab: Serviços -->
          <!-- ================================================================ -->
          <q-tab-panel name="services" class="tab-panel">
            <div class="panel-header">
              <div class="panel-header-left">
                <h3>Serviços</h3>
                <p>Serviços oferecidos neste estabelecimento</p>
              </div>
              <div class="panel-header-right" v-if="canManage">
                <q-btn
                  color="primary"
                  icon="add"
                  label="Novo Serviço"
                  no-caps
                  size="sm"
                  @click="$router.push(`/app/establishments/${$route.params.id}/services/new`)"
                />
              </div>
            </div>

            <div v-if="loadingServices" class="loading-state-sm">
              <q-spinner-dots color="primary" size="30px" />
            </div>
            <div v-else-if="services.length === 0" class="empty-state-sm">
              <q-icon name="build" size="48px" />
              <p>Nenhum serviço cadastrado</p>
            </div>
            <div v-else class="list-items">
              <div
                v-for="service in services"
                :key="service.id"
                class="list-item clickable"
                @click="$router.push(`/app/establishments/${$route.params.id}/services/${service.id}`)"
              >
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
                <div class="list-item-side">
                  <q-icon name="chevron_right" class="chevron" />
                </div>
              </div>
            </div>
          </q-tab-panel>

          <!-- ================================================================ -->
          <!-- Tab: Profissionais -->
          <!-- ================================================================ -->
          <q-tab-panel name="professionals" class="tab-panel">
            <div class="panel-header">
              <div class="panel-header-left">
                <h3>Profissionais</h3>
                <p>Equipe vinculada a este estabelecimento</p>
              </div>
              <div class="panel-header-right" v-if="canManage">
                <q-btn
                  color="primary"
                  icon="add"
                  label="Novo Profissional"
                  no-caps
                  size="sm"
                  @click="$router.push(`/app/establishments/${$route.params.id}/professionals/new`)"
                />
              </div>
            </div>

            <div v-if="loadingProfessionals" class="loading-state-sm">
              <q-spinner-dots color="primary" size="30px" />
            </div>
            <div v-else-if="professionals.length === 0" class="empty-state-sm">
              <q-icon name="badge" size="48px" />
              <p>Nenhum profissional cadastrado</p>
            </div>
            <div v-else class="list-items">
              <div
                v-for="prof in professionals"
                :key="prof.id"
                class="list-item clickable"
                @click="$router.push(`/app/establishments/${$route.params.id}/professionals/${prof.id}`)"
              >
                <div class="list-item-info">
                  <div class="list-item-avatar">
                    <q-icon name="person" size="20px" />
                  </div>
                  <div class="list-item-details">
                    <span class="list-item-name">{{ prof.name }}</span>
                    <span class="list-item-meta" v-if="prof.specialization">{{ prof.specialization }}</span>
                  </div>
                </div>
                <div class="list-item-side">
                  <q-icon name="chevron_right" class="chevron" />
                </div>
              </div>
            </div>
          </q-tab-panel>
        </q-tab-panels>
      </div>
    </template>

    <!-- Delete Establishment Confirm -->
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
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'
import { isEqual } from 'lodash-es'

export default defineComponent({
  name: 'EstablishmentDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const establishment = ref(null)
    const originalEstablishment = ref(null)
    const services = ref([])
    const professionals = ref([])
    const loading = ref(true)
    const loadingServices = ref(false)
    const loadingProfessionals = ref(false)
    const saving = ref(false)
    const deleting = ref(false)
    const userRole = ref(null)
    const activeTab = ref('info')

    const showDeleteConfirm = ref(false)

    const editForm = ref({ name: '', slug: '', description: '', address: '', phone: '', email: '', opens_at: '', closes_at: '', timezone: '' })

    const canManage = computed(() => ['admin', 'manager'].includes(userRole.value))
    const hasChanges = computed(() => {
      if (loading.value || originalEstablishment.value === null) {
        return false
      }
      return !isEqual(originalEstablishment.value, editForm.value)
    })

    const goBack = () => router.push('/app/establishments')

    const setFormData = (data) => {
      const formData = {
        name: data?.name || '',
        slug: data?.slug || '',
        description: data?.description || '',
        address: data?.address || '',
        phone: data?.phone || '',
        email: data?.email || '',
        opens_at: data?.opens_at || '',
        closes_at: data?.closes_at || '',
        timezone: data?.timezone || 'America/Sao_Paulo'
      }
      editForm.value = { ...formData }
      originalEstablishment.value = { ...formData }
    }

    const fetchEstablishment = async () => {
      loading.value = true
      try {
        const response = await api.get('/establishments')
        const list = response.data?.data?.establishments || response.data?.data || []
        const found = list.find(e => e.id == route.params.id) || null
        if (!found) {
          $q.notify({ type: 'negative', message: 'Estabelecimento não encontrado' })
          goBack()
          return
        }
        establishment.value = found
        setFormData(found)
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

    const fetchProfessionals = async () => {
      loadingProfessionals.value = true
      try {
        const response = await api.get(`/establishments/${route.params.id}/professionals`)
        if (response.data?.success) {
          professionals.value = response.data.data?.professionals || []
        }
      } catch {
        professionals.value = []
      } finally {
        loadingProfessionals.value = false
      }
    }

    const fetchUserRole = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) userRole.value = response.data.data.user.role
      } catch { /* ignore */ }
    }

    const cancelChanges = () => {
      setFormData(establishment.value)
    }

    const saveEstablishment = async () => {
      if (!editForm.value.name) { $q.notify({ type: 'warning', message: 'Nome é obrigatório' }); return }
      if (!hasChanges.value) { $q.notify({ type: 'info', message: 'Nenhuma alteração para salvar.' }); return }
      saving.value = true
      try {
        const payload = {}
        Object.keys(editForm.value).forEach(key => {
          if (editForm.value[key] !== originalEstablishment.value[key]) {
            payload[key] = editForm.value[key]
          }
        })

        await api.put(`/establishments/${route.params.id}`, payload)
        $q.notify({ type: 'positive', message: 'Atualizado com sucesso' })
        await fetchEstablishment()
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

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR') : '-'

    onMounted(async () => {
      await fetchUserRole()
      await fetchEstablishment()
      fetchServices()
      fetchProfessionals()
    })

    return {
      establishment, services, professionals,
      loading, loadingServices, loadingProfessionals, saving, deleting,
      canManage, hasChanges, activeTab,
      showDeleteConfirm, editForm,
      goBack, saveEstablishment, deleteEstablishment, cancelChanges,
      formatDate
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

.detail-value-input {
  flex-grow: 1;
}

.detail-value-group {
  display: flex;
  align-items: center;
  flex-grow: 1;
}

// Tabs container
.tabs-container {
  padding: 0;
  overflow: hidden;
}

.establishment-tabs {
  margin-top: 10px;
  padding: 0 1rem;

  :deep(.q-tab) {
    padding: 0 1rem;
    .q-focus-helper { border-radius: 15px; }
  }

  :deep(.q-tab__label) {
    font-weight: 500;
  }
}

.tab-panels {
  background: transparent;
}

.tab-panel {
  padding: 1.5rem;
}

// Panel header (same pattern as AdminPanelPage)
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;

  h3 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--qm-text-muted);
  }
}

.panel-header-left {
  flex: 1;
}

.panel-header-right {
  flex-shrink: 0;
}
</style>