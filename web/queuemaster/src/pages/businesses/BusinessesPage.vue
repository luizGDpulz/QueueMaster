<template>
  <q-page class="businesses-page">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Negócios</h1>
      </div>
      <div class="header-right">
        <q-btn
          v-if="canCreateBusiness"
          color="primary"
          icon="add"
          label="Novo negócio"
          no-caps
          @click="openCreateDialog"
        />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Explore negócios e gerencie os vínculos já associados ao seu perfil.</p>
      </div>
    </div>

    <div class="soft-card page-card">
      <q-tabs
        v-model="activeTab"
        dense
        class="main-tabs"
        active-color="primary"
        indicator-color="primary"
        align="left"
        narrow-indicator
      >
        <q-tab name="linked" icon="business_center" label="Meus vínculos" no-caps />
        <q-tab name="explore" icon="travel_explore" label="Explorar" no-caps />
      </q-tabs>

      <q-separator style="margin-top: 10px;" />

      <q-tab-panels v-model="activeTab" animated class="tab-panels">
        <q-tab-panel name="linked" class="tab-panel">
          <div class="panel-header">
            <div>
              <h3>Meus negócios</h3>
              <p>Negócios onde você já atua ou gerência.</p>
            </div>
            <q-input
              v-model="searchQuery"
              outlined
              dense
              placeholder="Filtrar vínculos..."
              class="search-input"
            >
              <template #prepend>
                <q-icon name="search" />
              </template>
            </q-input>
          </div>

          <div v-if="loading" class="loading-state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando vínculos...</p>
          </div>

          <div v-else-if="filteredBusinesses.length === 0" class="empty-state">
            <q-icon name="business" size="56px" />
            <h3>Nenhum vínculo encontrado</h3>
            <p v-if="searchQuery">Tente ajustar o filtro.</p>
            <p v-else>Use a aba Explorar para navegar pelos negócios ativos.</p>
          </div>

          <div v-else class="business-grid">
            <article
              v-for="business in filteredBusinesses"
              :key="business.id"
              class="business-card soft-card"
              @click="openBusiness(business.id)"
            >
              <div class="business-card__header">
                <div>
                  <h4>{{ business.name }}</h4>
                  <p>{{ business.slug || 'Sem slug configurado' }}</p>
                </div>
                <q-badge :color="getRoleColor(business.user_role)" :label="getRoleLabel(business.user_role)" />
              </div>

              <p class="business-card__desc">{{ business.description || 'Sem descrição cadastrada.' }}</p>

              <div class="business-card__meta">
                <span>{{ business.is_active ? 'Ativo' : 'Inativo' }}</span>
                <span>{{ formatDate(business.created_at) }}</span>
              </div>

              <div class="business-card__actions" @click.stop>
                <q-btn flat no-caps icon="open_in_new" label="Abrir" @click="openBusiness(business.id)" />
                <q-btn
                  v-if="canCreateBusiness"
                  flat
                  no-caps
                  icon="edit"
                  label="Editar"
                  @click="editBusiness(business)"
                />
              </div>
            </article>
          </div>
        </q-tab-panel>

        <q-tab-panel name="explore" class="tab-panel">
          <div class="panel-header panel-header--stack">
            <div>
              <h3>Explorar negócios</h3>
              <p>Pesquise negócios ativos. Ao abrir um negócio, você poderá navegar pelos estabelecimentos vinculados a ele.</p>
            </div>

            <div class="explore-toolbar">
              <q-input
                v-model="discoverQuery"
                outlined
                dense
                placeholder="Nome do negócio..."
                class="search-input search-input--wide"
                @keyup.enter="searchDiscover"
              >
                <template #prepend>
                  <q-icon name="search" />
                </template>
                <template #append>
                  <q-btn flat dense round icon="send" :loading="searchingDiscover" :disable="searchingDiscover" @click="searchDiscover" />
                </template>
              </q-input>
            </div>
          </div>

          <div v-if="searchingDiscover" class="loading-state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Buscando resultados...</p>
          </div>

          <div v-else-if="discoverSearched && discoverBusinesses.length === 0" class="empty-state">
            <q-icon name="search_off" size="56px" />
            <h3>Nenhum resultado encontrado</h3>
            <p>Tente um termo diferente ou mais amplo.</p>
          </div>

          <template v-else>
            <section class="discover-section">
              <div class="discover-section__header">
                <h4>Negócios</h4>
                <span>{{ discoverBusinesses.length }}</span>
              </div>

              <div v-if="discoverBusinesses.length === 0" class="empty-state-sm">
                <p>Nenhum negócio listado para o filtro atual.</p>
              </div>

              <div v-else class="business-grid">
                <article
                  v-for="business in discoverBusinesses"
                  :key="`biz-${business.id}`"
                  class="business-card soft-card"
                  @click="openBusiness(business.id)"
                >
                  <div class="business-card__header">
                    <div>
                      <h4>{{ business.name }}</h4>
                      <p>{{ business.establishment_count || 0 }} estabelecimento(s)</p>
                    </div>
                    <q-badge
                      :color="business.is_linked ? 'positive' : undefined"
                      :label="business.is_linked ? 'Vinculado' : 'Explorar'"
                      :class="{ 'neutral-badge': !business.is_linked }"
                    />
                  </div>
                  <p class="business-card__desc">{{ business.description || 'Sem descrição cadastrada.' }}</p>
                  <div class="business-card__actions" @click.stop>
                    <q-btn flat no-caps icon="visibility" label="Ver detalhes" @click="openBusiness(business.id)" />
                  </div>
                </article>
              </div>
            </section>
          </template>
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">{{ isEditing ? 'Editar negócio' : 'Novo negócio' }}</div>
          <q-btn flat round dense icon="close" @click="showDialog = false" />
        </q-card-section>

        <q-card-section class="dialog-body">
          <q-input
            v-model="form.name"
            label="Nome do negócio *"
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
            hint="Ex: meu-negócio"
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
          <q-btn color="primary" :label="isEditing ? 'Salvar' : 'Criar'" no-caps :loading="saving" @click="saveBusiness" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { computed, defineComponent, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'
import { useRemoteSearch } from 'src/composables/useRemoteSearch'

export default defineComponent({
  name: 'BusinessesPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    const loading = ref(false)
    const saving = ref(false)
    const businesses = ref([])
    const currentUser = ref(JSON.parse(localStorage.getItem('user') || '{}'))
    const activeTab = ref('linked')
    const searchQuery = ref('')
    const showDialog = ref(false)
    const isEditing = ref(false)
    const editingId = ref(null)
    const discoverQuery = ref('')
    const discoverSearch = useRemoteSearch({
      search: ({ query, signal }) => api.get('/businesses/search', {
        params: { q: query, limit: 20 },
        signal,
      }),
      mapResults: (response) => response.data?.data?.businesses || [],
    })

    const discoverBusinesses = discoverSearch.results
    const discoverSearched = discoverSearch.searched
    const searchingDiscover = discoverSearch.loading

    const form = ref({
      name: '',
      slug: '',
      description: '',
    })

    const canCreateBusiness = computed(() => ['manager', 'admin'].includes(currentUser.value?.role))
    const filteredBusinesses = computed(() => {
      if (!searchQuery.value) return businesses.value
      const query = searchQuery.value.toLowerCase()
      return businesses.value.filter((business) => (
        business.name?.toLowerCase().includes(query)
        || business.slug?.toLowerCase().includes(query)
        || business.description?.toLowerCase().includes(query)
      ))
    })

    const fetchCurrentUser = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          currentUser.value = response.data.data?.user || currentUser.value
          localStorage.setItem('user', JSON.stringify(currentUser.value))
        }
      } catch {
        // ignore
      }
    }

    const fetchBusinesses = async () => {
      loading.value = true
      try {
        const response = await api.get('/businesses')
        if (response.data?.success) {
          businesses.value = response.data.data?.businesses || []
        }
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar negócios' })
      } finally {
        loading.value = false
      }
    }

    const notifyDiscoverError = (error) => {
      $q.notify({ type: 'negative', message: error.response?.data?.error?.message || 'Erro ao buscar resultados' })
    }

    const searchDiscover = async ({ force = false } = {}) => {
      try {
        await discoverSearch.run(discoverQuery.value, { force })
      } catch (error) {
        notifyDiscoverError(error)
      }
    }

    const openBusiness = (id) => {
      router.push(`/app/businesses/${id}`)
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
        description: business.description || '',
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
          description: form.value.description?.trim() || undefined,
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
        const message = err.response?.data?.error?.message || 'Erro ao salvar negócio'
        $q.notify({ type: 'negative', message })
      } finally {
        saving.value = false
      }
    }

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('pt-BR')
    }

    const getRoleLabel = (role) => ({
      owner: 'Proprietário',
      manager: 'Gerente',
      professional: 'Profissional',
      admin: 'Administrador',
    }[role] || 'Vinculado')

    const getRoleColor = (role) => ({
      owner: 'positive',
      manager: 'info',
      professional: 'warning',
      admin: 'negative',
    }[role] || 'grey')

    watch(discoverQuery, (value, previousValue) => {
      if (value === previousValue || activeTab.value !== 'explore') {
        return
      }

      discoverSearch.schedule(value, {
        onError: notifyDiscoverError,
      })
    })

    watch(activeTab, (value) => {
      if (value !== 'explore') {
        discoverSearch.clear({
          keepSearched: true,
          value: discoverBusinesses.value,
        })
        return
      }

      if (discoverSearched.value) {
        return
      }

      searchDiscover()
    })

    onMounted(async () => {
      await Promise.all([fetchCurrentUser(), fetchBusinesses()])
    })

    return {
      activeTab,
      loading,
      saving,
      businesses,
      currentUser,
      canCreateBusiness,
      searchQuery,
      filteredBusinesses,
      showDialog,
      isEditing,
      form,
      discoverQuery,
      discoverBusinesses,
      discoverSearched,
      searchingDiscover,
      openBusiness,
      openCreateDialog,
      editBusiness,
      saveBusiness,
      searchDiscover,
      formatDate,
      getRoleLabel,
      getRoleColor,
    }
  },
})
</script>

<style lang="scss" scoped>
.businesses-page {
  padding: 0 1.5rem 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  column-gap: 1rem;
  row-gap: 0.25rem;
}

.header-left {
  flex: 1;
  min-height: 40px;
  display: flex;
  align-items: center;
}

.header-right {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.header-bottom {
  flex-basis: 100%;
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
  margin: 0;
}

.page-card {
  padding: 0;
  overflow: hidden;
}

.main-tabs {
  margin-top: 10px;
  padding: 0 1rem;

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

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;

  h3 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    color: var(--qm-text-muted);
    font-size: 0.875rem;
  }
}

.panel-header--stack {
  flex-direction: column;
}

.explore-toolbar {
  width: 100%;
}

.search-input {
  width: 280px;
}

.search-input--wide {
  width: 100%;
}

.loading-state,
.empty-state,
.empty-state-sm {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--qm-text-muted);
  text-align: center;
}

.loading-state,
.empty-state {
  padding: 4rem 2rem;

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

.empty-state-sm {
  padding: 2rem 1rem;

  p {
    margin: 0;
    font-size: 0.875rem;
  }
}

.discover-section__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.875rem;

  h4 {
    margin: 0;
    font-size: 1rem;
    color: var(--qm-text-primary);
  }

  span {
    font-size: 0.75rem;
    color: var(--qm-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
}

.business-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1rem;
}

.business-card {
  padding: 1.1rem;
  cursor: pointer;
  border-radius: 16px;
  transition: transform 0.18s ease, box-shadow 0.18s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: var(--qm-shadow-lg);
  }
}

.business-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 0.75rem;

  h4 {
    margin: 0 0 0.2rem;
    font-size: 1rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--qm-text-muted);
  }
}

.business-card__desc {
  margin: 0 0 0.875rem;
  color: var(--qm-text-secondary);
  font-size: 0.875rem;
  min-height: 2.6em;
}

.business-card__meta {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  color: var(--qm-text-muted);
  font-size: 0.75rem;
}

.business-card__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
  flex-wrap: wrap;
}

.neutral-badge {
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-secondary);
  border: 1px solid var(--qm-border);
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

@media (max-width: 768px) {
  .panel-header {
    flex-direction: column;
  }

  .search-input {
    width: 100%;
  }

  .dialog-card {
    min-width: 0;
    width: calc(100vw - 24px);
  }
}
</style>
