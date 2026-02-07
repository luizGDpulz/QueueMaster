<template>
  <q-page class="establishments-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Estabelecimentos</h1>
        <p class="page-subtitle">Gerencie todos os estabelecimentos cadastrados</p>
      </div>
      <div class="header-right">
        <q-btn
          v-if="isAdmin"
          color="primary"
          icon="add"
          label="Novo Estabelecimento"
          no-caps
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card soft-card">
      <div class="table-header">
        <h2 class="table-title">Lista de Estabelecimentos</h2>
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
        <p>Carregando estabelecimentos...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredEstablishments.length === 0" class="empty-state">
        <q-icon name="store" size="64px" />
        <h3>Nenhum estabelecimento encontrado</h3>
        <p v-if="searchQuery">Tente ajustar sua busca</p>
        <p v-else>Comece adicionando um novo estabelecimento</p>
      </div>

      <!-- Table -->
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="th-establishment">Estabelecimento</th>
              <th class="th-address">Endereço</th>
              <th class="th-timezone">Timezone</th>
              <th class="th-created">Criado em</th>
              <th class="th-actions"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="establishment in filteredEstablishments" :key="establishment.id">
              <td>
                <div class="establishment-info">
                  <div class="establishment-avatar">
                    <q-icon name="store" size="20px" />
                  </div>
                  <div class="establishment-details">
                    <span class="establishment-name">{{ establishment.name }}</span>
                    <span class="establishment-id">ID: {{ establishment.id }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="address-text">{{ establishment.address || 'Não informado' }}</span>
              </td>
              <td>
                <q-badge color="grey-7" class="timezone-badge">
                  {{ establishment.timezone || 'America/Sao_Paulo' }}
                </q-badge>
              </td>
              <td>
                <span class="date-text">{{ formatDate(establishment.created_at) }}</span>
              </td>
              <td>
                <div class="row-actions">
                  <q-btn flat round dense icon="visibility" size="sm" @click="viewEstablishment(establishment)">
                    <q-tooltip>Ver detalhes</q-tooltip>
                  </q-btn>
                  <q-btn v-if="isAdmin" flat round dense icon="edit" size="sm" @click="editEstablishment(establishment)">
                    <q-tooltip>Editar</q-tooltip>
                  </q-btn>
                  <q-btn v-if="isAdmin" flat round dense icon="delete" size="sm" color="negative" @click="confirmDelete(establishment)">
                    <q-tooltip>Excluir</q-tooltip>
                  </q-btn>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>{{ isEditing ? 'Editar Estabelecimento' : 'Novo Estabelecimento' }}</h3>
          <q-btn flat round dense icon="close" @click="closeDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-input
            v-model="form.name"
            label="Nome *"
            outlined
            dense
            :rules="[val => !!val || 'Nome é© obrigatório']"
          />
          <q-input
            v-model="form.address"
            label="Endereço"
            outlined
            dense
            class="q-mt-md"
          />
          <q-select
            v-model="form.timezone"
            label="Timezone"
            outlined
            dense
            :options="timezoneOptions"
            class="q-mt-md"
          />
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeDialog" />
          <q-btn 
            color="primary" 
            :label="isEditing ? 'Salvar' : 'Criar'" 
            no-caps 
            :loading="saving"
            @click="saveEstablishment" 
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- View Dialog -->
    <q-dialog v-model="showViewDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Detalhes do Estabelecimento</h3>
          <q-btn flat round dense icon="close" @click="showViewDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-content" v-if="selectedEstablishment">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Nome</span>
              <span class="detail-value">{{ selectedEstablishment.name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Endereço</span>
              <span class="detail-value">{{ selectedEstablishment.address || 'Não informado' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Timezone</span>
              <span class="detail-value">{{ selectedEstablishment.timezone }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Criado em</span>
              <span class="detail-value">{{ formatDate(selectedEstablishment.created_at) }}</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Fechar" no-caps @click="showViewDialog = false" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Delete Confirmation Dialog -->
    <q-dialog v-model="showDeleteDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Confirmar Exclusão</h3>
        </q-card-section>

        <q-card-section class="dialog-content">
          <p>Tem certeza que deseja excluir o estabelecimento <strong>{{ selectedEstablishment?.name }}</strong>?</p>
          <p class="delete-warning">Esta ação né£o pode ser desfeita.</p>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteDialog = false" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deleting" @click="deleteEstablishment" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'EstablishmentsPage',

  setup() {
    const $q = useQuasar()

    // State
    const establishments = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const deleting = ref(false)
    const searchQuery = ref('')
    const userRole = ref(null)

    // Dialogs
    const showDialog = ref(false)
    const showViewDialog = ref(false)
    const showDeleteDialog = ref(false)
    const isEditing = ref(false)
    const selectedEstablishment = ref(null)

    // Form
    const form = ref({
      name: '',
      address: '',
      timezone: 'America/Sao_Paulo'
    })

    const timezoneOptions = [
      'America/Sao_Paulo',
      'America/Fortaleza',
      'America/Manaus',
      'America/Rio_Branco',
      'America/Noronha'
    ]

    // Computed
    const isAdmin = computed(() => userRole.value === 'admin')

    const filteredEstablishments = computed(() => {
      if (!searchQuery.value) return establishments.value
      const query = searchQuery.value.toLowerCase()
      return establishments.value.filter(e => 
        e.name.toLowerCase().includes(query) ||
        (e.address && e.address.toLowerCase().includes(query))
      )
    })

    // Methods
    const fetchEstablishments = async () => {
      loading.value = true
      try {
        const response = await api.get('/establishments')
        if (response.data?.success) {
          establishments.value = response.data.data?.establishments || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar estabelecimentos:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar estabelecimentos' })
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
      } catch (err) {
        console.error('Erro ao buscar role:', err)
      }
    }

    const openCreateDialog = () => {
      isEditing.value = false
      form.value = { name: '', address: '', timezone: 'America/Sao_Paulo' }
      showDialog.value = true
    }

    const editEstablishment = (establishment) => {
      isEditing.value = true
      selectedEstablishment.value = establishment
      form.value = {
        name: establishment.name,
        address: establishment.address || '',
        timezone: establishment.timezone || 'America/Sao_Paulo'
      }
      showDialog.value = true
    }

    const viewEstablishment = (establishment) => {
      selectedEstablishment.value = establishment
      showViewDialog.value = true
    }

    const confirmDelete = (establishment) => {
      selectedEstablishment.value = establishment
      showDeleteDialog.value = true
    }

    const closeDialog = () => {
      showDialog.value = false
      form.value = { name: '', address: '', timezone: 'America/Sao_Paulo' }
    }

    const saveEstablishment = async () => {
      if (!form.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é© obrigatório' })
        return
      }

      saving.value = true
      try {
        if (isEditing.value) {
          await api.put(`/establishments/${selectedEstablishment.value.id}`, form.value)
          $q.notify({ type: 'positive', message: 'Estabelecimento atualizado com sucesso' })
        } else {
          await api.post('/establishments', form.value)
          $q.notify({ type: 'positive', message: 'Estabelecimento criado com sucesso' })
        }
        closeDialog()
        fetchEstablishments()
      } catch (err) {
        console.error('Erro ao salvar:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const deleteEstablishment = async () => {
      deleting.value = true
      try {
        await api.delete(`/establishments/${selectedEstablishment.value.id}`)
        $q.notify({ type: 'positive', message: 'Estabelecimento exclué­do com sucesso' })
        showDeleteDialog.value = false
        fetchEstablishments()
      } catch (err) {
        console.error('Erro ao excluir:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir' })
      } finally {
        deleting.value = false
      }
    }

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('pt-BR')
    }

    // Lifecycle
    onMounted(() => {
      fetchUserRole()
      fetchEstablishments()
    })

    return {
      establishments,
      loading,
      saving,
      deleting,
      searchQuery,
      showDialog,
      showViewDialog,
      showDeleteDialog,
      isEditing,
      selectedEstablishment,
      form,
      timezoneOptions,
      isAdmin,
      filteredEstablishments,
      openCreateDialog,
      editEstablishment,
      viewEstablishment,
      confirmDelete,
      closeDialog,
      saveEstablishment,
      deleteEstablishment,
      formatDate
    }
  }
})
</script>

<style lang="scss" scoped>
.establishments-page {
  padding: 0 1.5rem 1.5rem;
}

// Header
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0 0 0.25rem;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--qm-text-muted);
  margin: 0;
}

// Table Card
.table-card {
  padding: 0;
  overflow: hidden;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--qm-border);
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
  width: 250px;

  @media (max-width: 600px) {
    width: 100%;
  }
}

// Loading & Empty States
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--qm-text-muted);
  text-align: center;

  h3 {
    margin: 1rem 0 0.5rem;
    font-size: 1.125rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.875rem;
  }
}

// Table
.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;

  th, td {
    padding: 0.875rem 1.5rem;
    text-align: left;
  }

  thead {
    tr {
      background: var(--qm-bg-secondary);
    }

    th {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--qm-text-muted);
      border-bottom: 1px solid var(--qm-border);
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid var(--qm-border);
      transition: background 0.2s ease;

      &:last-child {
        border-bottom: none;
      }

      &:hover {
        background: var(--qm-bg-secondary);
      }
    }

    td {
      font-size: 0.875rem;
      color: var(--qm-text-primary);
    }
  }
}

.th-establishment {
  min-width: 200px;
}

.th-address {
  min-width: 180px;
}

.th-timezone {
  min-width: 150px;
}

.th-created {
  min-width: 100px;
}

.th-actions {
  width: 120px;
}

// Establishment Info Cell
.establishment-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.establishment-avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  display: flex;
  align-items: center;
  justify-content: center;
}

.establishment-details {
  display: flex;
  flex-direction: column;
}

.establishment-name {
  font-weight: 600;
  font-size: 0.875rem;
}

.establishment-id {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.address-text {
  font-size: 0.8125rem;
  color: var(--qm-text-secondary);
}

.timezone-badge {
  font-size: 0.6875rem;
}

.date-text {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

.row-actions {
  display: flex;
  gap: 0.25rem;
}

// Dialog Styles
.dialog-card {
  width: 100%;
  max-width: 500px;
  border-radius: 16px;
  background: var(--qm-bg-primary);

  :deep(.q-btn) {
    min-height: 36px;
  }

  :deep(.q-btn__content) {
    color: inherit;
  }
}

.dialog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--qm-border);

  h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.dialog-content {
  padding: 1.5rem;
}

.dialog-actions {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--qm-border);
  gap: 0.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;

  @media (max-width: 500px) {
    grid-template-columns: 1fr;
  }
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 0.9375rem;
  color: var(--qm-text-primary);
  font-weight: 500;
}

.delete-warning {
  color: var(--qm-error);
  font-size: 0.8125rem;
  margin-top: 0.5rem;
}
</style>
