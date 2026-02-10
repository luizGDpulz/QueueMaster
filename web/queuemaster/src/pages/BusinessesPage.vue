<template>
  <q-page class="businesses-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Negócios</h1>
        <p class="page-subtitle">Gerencie seus negócios e estabelecimentos</p>
      </div>
      <div class="header-right">
        <q-btn
          color="primary"
          icon="add"
          label="Novo Negócio"
          no-caps
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card soft-card">
      <div class="table-header">
        <h2 class="table-title">Seus Negócios</h2>
        <div class="table-actions">
          <q-input
            v-model="searchQuery"
            outlined
            dense
            placeholder="Buscar..."
            class="search-input"
          >
            <template v-slot:prepend>
              <q-icon name="search" />
            </template>
          </q-input>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <q-spinner-dots color="primary" size="40px" />
        <p>Carregando negócios...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredBusinesses.length === 0" class="empty-state">
        <q-icon name="business" size="64px" />
        <h3>Nenhum negócio encontrado</h3>
        <p v-if="searchQuery">Tente ajustar sua busca</p>
        <p v-else>Comece criando seu primeiro negócio</p>
      </div>

      <!-- Table -->
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="th-name">Negócio</th>
              <th class="th-role">Seu Papel</th>
              <th class="th-status">Status</th>
              <th class="th-created">Criado em</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="business in filteredBusinesses" :key="business.id" class="clickable-row" @click="router.push(`/app/businesses/${business.id}`)">
              <td>
                <div class="business-info">
                  <q-icon name="business" size="24px" class="business-icon" />
                  <div>
                    <span class="business-name">{{ business.name }}</span>
                    <span v-if="business.slug" class="business-slug">{{ business.slug }}</span>
                  </div>
                </div>
              </td>
              <td>
                <q-badge :color="getRoleColor(business.user_role)" :label="getRoleLabel(business.user_role)" />
              </td>
              <td>
                <q-badge :color="business.is_active ? 'positive' : 'negative'" :label="business.is_active ? 'Ativo' : 'Inativo'" />
              </td>
              <td class="td-date">{{ formatDate(business.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">{{ isEditing ? 'Editar Negócio' : 'Novo Negócio' }}</div>
          <q-btn flat round dense icon="close" @click="showDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-body">
          <q-input
            v-model="form.name"
            label="Nome do Negócio *"
            outlined
            dense
            :rules="[val => !!val || 'Nome é obrigatório']"
          />
          <q-input
            v-model="form.slug"
            label="Slug (URL amigável)"
            outlined
            dense
            class="q-mt-md"
            hint="Ex: meu-negocio"
          />
          <q-input
            v-model="form.description"
            label="Descrição"
            outlined
            dense
            type="textarea"
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDialog = false" />
          <q-btn
            color="primary"
            :label="isEditing ? 'Salvar' : 'Criar'"
            no-caps
            :loading="saving"
            @click="saveBusiness"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'BusinessesPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    // State
    const loading = ref(false)
    const saving = ref(false)
    const businesses = ref([])
    const searchQuery = ref('')
    const showDialog = ref(false)
    const isEditing = ref(false)
    const editingId = ref(null)

    const form = ref({
      name: '',
      slug: '',
      description: ''
    })

    // Computed
    const filteredBusinesses = computed(() => {
      if (!searchQuery.value) return businesses.value
      const q = searchQuery.value.toLowerCase()
      return businesses.value.filter(b =>
        b.name?.toLowerCase().includes(q) ||
        b.slug?.toLowerCase().includes(q)
      )
    })

    // Methods
    const fetchBusinesses = async () => {
      loading.value = true
      try {
        const response = await api.get('/businesses')
        if (response.data?.success) {
          businesses.value = response.data.data?.businesses || []
        }
      } catch (err) {
        console.error('Failed to fetch businesses:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar negócios' })
      } finally {
        loading.value = false
      }
    }

    const openCreateDialog = () => {
      isEditing.value = false
      editingId.value = null
      form.value = { name: '', slug: '', description: '' }
      showDialog.value = true
    }

    const editBusiness = (business) => {
      isEditing.value = true
      editingId.value = business.id
      form.value = {
        name: business.name || '',
        slug: business.slug || '',
        description: business.description || ''
      }
      showDialog.value = true
    }

    const saveBusiness = async () => {
      if (!form.value.name?.trim()) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }

      saving.value = true
      try {
        const payload = {
          name: form.value.name.trim(),
          slug: form.value.slug?.trim() || undefined,
          description: form.value.description?.trim() || undefined
        }

        if (isEditing.value) {
          await api.put(`/businesses/${editingId.value}`, payload)
          $q.notify({ type: 'positive', message: 'Negócio atualizado com sucesso' })
        } else {
          await api.post('/businesses', payload)
          $q.notify({ type: 'positive', message: 'Negócio criado com sucesso' })
        }

        showDialog.value = false
        await fetchBusinesses()
      } catch (err) {
        const msg = err.response?.data?.error?.message || 'Erro ao salvar negócio'
        $q.notify({ type: 'negative', message: msg })
      } finally {
        saving.value = false
      }
    }

    const formatDate = (dateStr) => {
      if (!dateStr) return '-'
      return new Date(dateStr).toLocaleDateString('pt-BR')
    }

    const roleLabels = { owner: 'Proprietário', manager: 'Gerente' }
    const roleColors = { owner: 'positive', manager: 'info' }

    const getRoleLabel = (role) => roleLabels[role] || role
    const getRoleColor = (role) => roleColors[role] || 'grey'

    // Lifecycle
    onMounted(() => {
      fetchBusinesses()
    })

    return {
      loading,
      saving,
      businesses,
      searchQuery,
      showDialog,
      isEditing,
      form,
      filteredBusinesses,
      openCreateDialog,
      editBusiness,
      saveBusiness,
      formatDate,
      getRoleLabel,
      getRoleColor,
      router
    }
  }
})
</script>

<style lang="scss" scoped>
.businesses-page {
  padding: 0 1.5rem 2rem;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--qm-text-muted);
  margin: 0.25rem 0 0;
}

.soft-card {
  background: var(--qm-surface);
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: var(--qm-shadow);
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.table-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin: 0;
}

.search-input {
  width: 220px;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--qm-text-muted);
}

.empty-state h3 {
  margin: 1rem 0 0.5rem;
  color: var(--qm-text-secondary);
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;

  th, td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--qm-border);
  }

  th {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--qm-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  td {
    font-size: 0.875rem;
    color: var(--qm-text-primary);
  }

  tbody tr:hover {
    background: var(--qm-bg-tertiary);
  }

  tbody tr.clickable-row {
    cursor: pointer;
  }
}

.business-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.business-icon {
  color: var(--qm-brand);
}

.business-name {
  display: block;
  font-weight: 600;
}

.business-slug {
  display: block;
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.td-date {
  white-space: nowrap;
}

.dialog-card {
  min-width: 400px;
  border-radius: 1rem;
  background: var(--qm-surface);
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dialog-body {
  padding-top: 0;
}

.detail-row {
  display: flex;
  gap: 1rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--qm-border);
}

.detail-label {
  font-weight: 600;
  color: var(--qm-text-secondary);
  min-width: 100px;
}

.detail-value {
  color: var(--qm-text-primary);
}

.section-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--qm-text-primary);
  margin: 0;
}
</style>
