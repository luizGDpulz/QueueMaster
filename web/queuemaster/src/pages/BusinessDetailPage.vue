<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <div>
          <h1 class="page-title">{{ business?.name || 'Carregando...' }}</h1>
          <p class="page-subtitle">Detalhes do negócio</p>
        </div>
      </div>
      <div class="header-right">
        <q-btn flat icon="edit" label="Editar" no-caps @click="openEdit" />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="business">
      <!-- Info Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Nome</span>
            <span class="detail-value">{{ business.name }}</span>
          </div>
          <div class="detail-item" v-if="business.slug">
            <span class="detail-label">Slug</span>
            <span class="detail-value">{{ business.slug }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Seu Papel</span>
            <q-badge :color="getRoleColor(business.user_role)" :label="getRoleLabel(business.user_role)" />
          </div>
          <div class="detail-item">
            <span class="detail-label">Status</span>
            <q-badge :color="business.is_active ? 'positive' : 'negative'" :label="business.is_active ? 'Ativo' : 'Inativo'" />
          </div>
          <div class="detail-item" v-if="business.description">
            <span class="detail-label">Descrição</span>
            <span class="detail-value">{{ business.description }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Criado em</span>
            <span class="detail-value">{{ formatDate(business.created_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Establishments Card -->
      <div class="soft-card">
        <div class="section-header">
          <h2 class="section-title">Estabelecimentos</h2>
        </div>

        <div v-if="loadingEstablishments" class="loading-state-sm">
          <q-spinner-dots color="primary" size="30px" />
        </div>
        <div v-else-if="establishments.length === 0" class="empty-state-sm">
          <q-icon name="store" size="40px" />
          <p>Nenhum estabelecimento vinculado</p>
        </div>
        <div v-else class="list-items">
          <div
            v-for="est in establishments"
            :key="est.id"
            class="list-item clickable"
            @click="$router.push(`/app/establishments/${est.id}`)"
          >
            <div class="list-item-info">
              <div class="list-item-avatar">
                <q-icon name="store" size="20px" />
              </div>
              <div class="list-item-details">
                <span class="list-item-name">{{ est.name }}</span>
                <span class="list-item-meta">{{ est.address || 'Sem endereço' }}</span>
              </div>
            </div>
            <div class="list-item-side">
              <q-badge :color="est.is_active ? 'positive' : 'grey'" :label="est.is_active ? 'Ativo' : 'Inativo'" />
              <q-icon name="chevron_right" size="20px" class="chevron" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Negócio</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" label="Nome do Negócio *" outlined dense />
          <q-input v-model="editForm.slug" label="Slug" outlined dense class="q-mt-md" />
          <q-input v-model="editForm.description" label="Descrição" outlined dense type="textarea" class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveBusiness" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'BusinessDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const business = ref(null)
    const establishments = ref([])
    const loading = ref(true)
    const loadingEstablishments = ref(false)
    const saving = ref(false)
    const showEditDialog = ref(false)
    const editForm = ref({ name: '', slug: '', description: '' })

    const goBack = () => router.push('/app/businesses')

    const fetchBusiness = async () => {
      loading.value = true
      try {
        const response = await api.get(`/businesses/${route.params.id}`)
        if (response.data?.success) {
          business.value = response.data.data?.business || response.data.data
        }
      } catch (err) {
        console.error('Erro ao buscar negócio:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar negócio' })
        goBack()
      } finally {
        loading.value = false
      }
    }

    const fetchEstablishments = async () => {
      loadingEstablishments.value = true
      try {
        const response = await api.get(`/businesses/${route.params.id}/establishments`)
        if (response.data?.success) {
          establishments.value = response.data.data?.establishments || []
        }
      } catch (err) {
        console.error('Erro ao buscar estabelecimentos:', err)
      } finally {
        loadingEstablishments.value = false
      }
    }

    const openEdit = () => {
      editForm.value = {
        name: business.value?.name || '',
        slug: business.value?.slug || '',
        description: business.value?.description || ''
      }
      showEditDialog.value = true
    }

    const saveBusiness = async () => {
      if (!editForm.value.name?.trim()) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }
      saving.value = true
      try {
        await api.put(`/businesses/${route.params.id}`, {
          name: editForm.value.name.trim(),
          slug: editForm.value.slug?.trim() || undefined,
          description: editForm.value.description?.trim() || undefined
        })
        $q.notify({ type: 'positive', message: 'Negócio atualizado com sucesso' })
        showEditDialog.value = false
        fetchBusiness()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR') : '-'
    const getRoleLabel = (r) => ({ owner: 'Proprietário', manager: 'Gerente' }[r] || r)
    const getRoleColor = (r) => ({ owner: 'positive', manager: 'info' }[r] || 'grey')

    onMounted(() => {
      fetchBusiness()
      fetchEstablishments()
    })

    return {
      business, establishments, loading, loadingEstablishments,
      saving, showEditDialog, editForm,
      goBack, openEdit, saveBusiness, formatDate, getRoleLabel, getRoleColor
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';
</style>
