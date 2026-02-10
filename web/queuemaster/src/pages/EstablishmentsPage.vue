<template>
  <q-page class="establishments-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Estabelecimentos</h1>
        <p class="page-subtitle">Gerencie todos os estabelecimentos e seus serviços</p>
      </div>
      <div class="header-right">
        <q-btn
          v-if="canManage"
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
              <th class="th-phone">Telefone</th>
              <th class="th-timezone">Timezone</th>
              <th class="th-created">Criado em</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="establishment in filteredEstablishments" :key="establishment.id" class="clickable-row" @click="router.push(`/app/establishments/${establishment.id}`)">
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
                <span class="phone-text">{{ establishment.phone || '-' }}</span>
              </td>
              <td>
                <q-badge color="grey-7" class="timezone-badge">
                  {{ establishment.timezone || 'America/Sao_Paulo' }}
                </q-badge>
              </td>
              <td>
                <span class="date-text">{{ formatDate(establishment.created_at) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Establishment Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card dialog-card-wide">
        <q-card-section class="dialog-header">
          <h3>{{ isEditing ? 'Editar Estabelecimento' : 'Novo Estabelecimento' }}</h3>
          <q-btn flat round dense icon="close" @click="closeDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <q-select
            v-if="!isEditing"
            v-model="form.business_id"
            label="Negócio *"
            outlined
            dense
            :options="businessOptions"
            emit-value
            map-options
            :rules="[val => !!val || 'Negócio é obrigatório']"
          />
          <q-input
            v-model="form.name"
            label="Nome *"
            outlined
            dense
            :class="{ 'q-mt-md': !isEditing }"
            :rules="[val => !!val || 'Nome é obrigatório']"
          />
          <q-input
            v-model="form.slug"
            label="Slug (URL amigável)"
            outlined
            dense
            class="q-mt-md"
            hint="Ex: meu-estabelecimento"
          />
          <q-input
            v-model="form.description"
            label="Descrição"
            outlined
            dense
            type="textarea"
            class="q-mt-md"
          />
          <q-input
            v-model="form.address"
            label="Endereço"
            outlined
            dense
            class="q-mt-md"
          />
          <div class="row q-col-gutter-md q-mt-xs">
            <div class="col-6">
              <q-input
                v-model="form.phone"
                label="Telefone"
                outlined
                dense
              />
            </div>
            <div class="col-6">
              <q-input
                v-model="form.email"
                label="Email"
                outlined
                dense
              />
            </div>
          </div>
          <q-select
            v-model="form.timezone"
            label="Timezone"
            outlined
            dense
            :options="timezoneOptions"
            class="q-mt-md"
          />
          <div class="row q-col-gutter-md q-mt-xs">
            <div class="col-6">
              <q-input
                v-model="form.opens_at"
                label="Abre às"
                outlined
                dense
                type="time"
              />
            </div>
            <div class="col-6">
              <q-input
                v-model="form.closes_at"
                label="Fecha às"
                outlined
                dense
                type="time"
              />
            </div>
          </div>
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

  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'EstablishmentsPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    // State
    const establishments = ref([])
    const businesses = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const searchQuery = ref('')
    const userRole = ref(null)

    // Establishment Dialogs
    const showDialog = ref(false)
    const isEditing = ref(false)
    const selectedEstablishment = ref(null)

    // Establishment Form
    const form = ref({
      business_id: null,
      name: '',
      slug: '',
      description: '',
      address: '',
      phone: '',
      email: '',
      timezone: 'America/Sao_Paulo',
      opens_at: '',
      closes_at: ''
    })

    const timezoneOptions = [
      'America/Sao_Paulo',
      'America/Fortaleza',
      'America/Manaus',
      'America/Rio_Branco',
      'America/Noronha'
    ]

    // Computed
    const canManage = computed(() => ['admin', 'manager'].includes(userRole.value))

    const businessOptions = computed(() => {
      return businesses.value.map(b => ({
        label: b.name,
        value: b.id
      }))
    })

    const filteredEstablishments = computed(() => {
      if (!searchQuery.value) return establishments.value
      const query = searchQuery.value.toLowerCase()
      return establishments.value.filter(e =>
        e.name.toLowerCase().includes(query) ||
        (e.address && e.address.toLowerCase().includes(query)) ||
        (e.phone && e.phone.includes(query))
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

    const fetchBusinesses = async () => {
      try {
        const response = await api.get('/businesses')
        if (response.data?.success) {
          businesses.value = response.data.data?.businesses || []
        }
      } catch (err) {
        console.error('Erro ao buscar negócios:', err)
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

    // Establishment CRUD
    const openCreateDialog = () => {
      isEditing.value = false
      form.value = {
        business_id: businesses.value.length === 1 ? businesses.value[0].id : null,
        name: '', slug: '', description: '', address: '',
        phone: '', email: '', timezone: 'America/Sao_Paulo',
        opens_at: '', closes_at: ''
      }
      showDialog.value = true
    }

    const closeDialog = () => {
      showDialog.value = false
    }

    const saveEstablishment = async () => {
      if (!form.value.name) {
        $q.notify({ type: 'warning', message: 'Nome é obrigatório' })
        return
      }

      if (!isEditing.value && !form.value.business_id) {
        $q.notify({ type: 'warning', message: 'Selecione um negócio' })
        return
      }

      saving.value = true
      try {
        const payload = {}
        // Include all non-empty fields
        Object.entries(form.value).forEach(([key, value]) => {
          if (value !== '' && value !== null && value !== undefined) {
            payload[key] = value
          }
        })

        if (isEditing.value) {
          // Don't send business_id on update
          delete payload.business_id
          await api.put(`/establishments/${selectedEstablishment.value.id}`, payload)
          $q.notify({ type: 'positive', message: 'Estabelecimento atualizado com sucesso' })
        } else {
          await api.post('/establishments', payload)
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

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('pt-BR')
    }

    // Lifecycle
    onMounted(async () => {
      await fetchUserRole()
      fetchEstablishments()
      if (['admin', 'manager'].includes(userRole.value)) {
        fetchBusinesses()
      }
    })

    return {
      establishments,
      businesses,
      loading,
      saving,
      searchQuery,
      showDialog,
      isEditing,
      selectedEstablishment,
      form,
      timezoneOptions,
      canManage,
      businessOptions,
      filteredEstablishments,
      openCreateDialog,
      closeDialog,
      saveEstablishment,
      formatDate,
      router
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

.th-establishment { min-width: 200px; }
.th-address { min-width: 180px; }
.th-phone { min-width: 120px; }
.th-timezone { min-width: 150px; }
.th-created { min-width: 100px; }

tr.clickable-row {
  cursor: pointer;
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

.address-text, .phone-text {
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
</style>
