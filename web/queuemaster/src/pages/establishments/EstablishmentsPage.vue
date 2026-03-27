<template>
  <q-page class="establishments-page">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Estabelecimentos</h1>
      </div>
      <div class="header-right">
        <q-btn
          v-if="canManage && activeTab === 'linked'"
          color="primary"
          icon="add"
          label="Novo estabelecimento"
          no-caps
          @click="openCreateDialog"
        />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Explore estabelecimentos e gerencie os vínculos já associados ao seu perfil.</p>
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
        <q-tab name="linked" icon="storefront" label="Meus vínculos" no-caps />
        <q-tab name="explore" icon="travel_explore" label="Explorar" no-caps />
      </q-tabs>

      <q-separator style="margin-top: 10px;" />

      <q-tab-panels v-model="activeTab" animated class="tab-panels">
        <q-tab-panel name="linked" class="tab-panel">
          <div class="panel-header">
            <div>
              <h3>Meus estabelecimentos</h3>
              <p>Locais onde você já atua, gerencia ou possui acesso.</p>
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

          <div v-else-if="filteredEstablishments.length === 0" class="empty-state">
            <q-icon name="store" size="56px" />
            <h3>Nenhum vínculo encontrado</h3>
            <p v-if="searchQuery">Tente ajustar o filtro.</p>
            <p v-else>Use a aba Explorar para navegar pelos estabelecimentos ativos.</p>
          </div>

          <div v-else class="establishment-grid">
            <article
              v-for="establishment in filteredEstablishments"
              :key="establishment.id"
              class="establishment-card soft-card"
              @click="openEstablishment(establishment.id)"
            >
              <div class="establishment-card__header">
                <div class="establishment-card__title">
                  <div class="establishment-avatar">
                    <q-icon name="store" size="20px" />
                  </div>
                  <div>
                    <h4>{{ establishment.name }}</h4>
                    <p>{{ establishment.business_name || 'Negócio não informado' }}</p>
                  </div>
                </div>
                <q-badge class="timezone-badge timezone-badge--neutral">
                  {{ establishment.timezone || 'America/Sao_Paulo' }}
                </q-badge>
              </div>

              <p class="establishment-card__desc">
                {{ establishment.address || establishment.description || 'Sem endereço informado.' }}
              </p>

              <div class="establishment-card__meta">
                <span>{{ establishment.phone || 'Sem telefone' }}</span>
                <span>{{ formatDate(establishment.created_at) }}</span>
              </div>

              <div class="establishment-card__actions" @click.stop>
                <q-btn flat no-caps icon="visibility" label="Ver detalhes" @click="openEstablishment(establishment.id)" />
                <q-btn flat no-caps icon="business" label="Abrir negócio" @click="openBusiness(establishment.business_id)" />
                <q-btn
                  v-if="canManage"
                  flat
                  no-caps
                  icon="edit"
                  label="Editar"
                  @click="editEstablishment(establishment)"
                />
              </div>
            </article>
          </div>
        </q-tab-panel>

        <q-tab-panel name="explore" class="tab-panel">
          <div class="panel-header panel-header--stack">
            <div>
              <h3>Explorar estabelecimentos</h3>
              <p>Pesquise estabelecimentos ativos. Se quiser contexto completo, abra o negócio e navegue pelos estabelecimentos vinculados.</p>
            </div>

            <div class="explore-toolbar">
              <q-input
                v-model="discoverQuery"
                outlined
                dense
                placeholder="Nome do estabelecimento, negócio ou endereço..."
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

          <div v-else-if="discoverSearched && discoverEstablishments.length === 0" class="empty-state">
            <q-icon name="search_off" size="56px" />
            <h3>Nenhum resultado encontrado</h3>
            <p>Tente um termo diferente ou mais amplo.</p>
          </div>

          <template v-else>
            <section class="discover-section">
              <div class="discover-section__header">
                <h4>Estabelecimentos</h4>
                <span>{{ discoverEstablishments.length }}</span>
              </div>

              <div v-if="discoverEstablishments.length === 0" class="empty-state-sm">
                <p>Nenhum estabelecimento listado para o filtro atual.</p>
              </div>

              <div v-else class="establishment-grid">
                <article
                  v-for="establishment in discoverEstablishments"
                  :key="`discover-${establishment.id}`"
                  class="establishment-card soft-card"
                  @click="openEstablishment(establishment.id)"
                >
                  <div class="establishment-card__header">
                    <div>
                      <h4>{{ establishment.name }}</h4>
                      <p>{{ establishment.business_name || 'Negócio não informado' }}</p>
                    </div>
                    <q-badge
                      :color="isLinkedEstablishment(establishment.id) ? 'positive' : undefined"
                      :label="isLinkedEstablishment(establishment.id) ? 'Vinculado' : 'Explorar'"
                      :class="{ 'neutral-badge': !isLinkedEstablishment(establishment.id) }"
                    />
                  </div>

                  <p class="establishment-card__desc">
                    {{ establishment.address || establishment.description || 'Sem endereço informado.' }}
                  </p>

                  <div class="establishment-card__meta">
                    <span>{{ establishment.phone || 'Sem telefone' }}</span>
                    <span>{{ establishment.timezone || 'America/Sao_Paulo' }}</span>
                  </div>

                  <div class="establishment-card__actions" @click.stop>
                    <q-btn flat no-caps icon="visibility" label="Ver detalhes" @click="openEstablishment(establishment.id)" />
                    <q-btn flat no-caps icon="business" label="Abrir negócio" @click="openBusiness(establishment.business_id)" />
                  </div>
                </article>
              </div>
            </section>
          </template>
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <q-dialog v-model="showDialog" persistent>
      <q-card class="dialog-card dialog-card-wide">
        <q-card-section class="dialog-header">
          <h3>{{ isEditing ? 'Editar estabelecimento' : 'Novo estabelecimento' }}</h3>
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
              <q-input v-model="form.phone" label="Telefone" outlined dense />
            </div>
            <div class="col-6">
              <q-input v-model="form.email" label="Email" outlined dense />
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
              <q-input v-model="form.opens_at" label="Abre às" outlined dense type="time" />
            </div>
            <div class="col-6">
              <q-input v-model="form.closes_at" label="Fecha às" outlined dense type="time" />
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

    <q-dialog v-model="showDeleteDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Confirmar exclusão</h3>
        </q-card-section>

        <q-card-section class="dialog-content">
          <p>Tem certeza que deseja excluir o estabelecimento <strong>{{ selectedEstablishment?.name }}</strong>?</p>
          <p class="delete-warning">Esta ação não pode ser desfeita.</p>
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
import { computed, defineComponent, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'
import { useRemoteSearch } from 'src/composables/useRemoteSearch'

export default defineComponent({
  name: 'EstablishmentsPage',

  setup() {
    const $q = useQuasar()
    const router = useRouter()

    const establishments = ref([])
    const businesses = ref([])
    const loading = ref(true)
    const saving = ref(false)
    const deleting = ref(false)

    const activeTab = ref('linked')
    const searchQuery = ref('')
    const discoverQuery = ref('')
    const userRole = ref(null)

    const discoverSearch = useRemoteSearch({
      search: ({ query, signal }) => api.get('/establishments/search', {
        params: { q: query, limit: 20 },
        signal,
      }),
      mapResults: (response) => response.data?.data?.establishments || [],
    })

    const discoverEstablishments = discoverSearch.results
    const searchingDiscover = discoverSearch.loading
    const discoverSearched = discoverSearch.searched

    const showDialog = ref(false)
    const showDeleteDialog = ref(false)
    const isEditing = ref(false)
    const selectedEstablishment = ref(null)

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
      closes_at: '',
    })

    const timezoneOptions = [
      'America/Sao_Paulo',
      'America/Fortaleza',
      'America/Manaus',
      'America/Rio_Branco',
      'America/Noronha',
    ]

    const canManage = computed(() => ['admin', 'manager'].includes(userRole.value))
    const linkedEstablishmentIds = computed(() => new Set(establishments.value.map((item) => Number(item.id))))
    const businessOptions = computed(() => businesses.value.map((business) => ({
      label: business.name,
      value: business.id,
    })))
    const filteredEstablishments = computed(() => {
      if (!searchQuery.value) return establishments.value
      const query = searchQuery.value.toLowerCase()
      return establishments.value.filter((establishment) => (
        establishment.name?.toLowerCase().includes(query)
        || establishment.business_name?.toLowerCase().includes(query)
        || establishment.address?.toLowerCase().includes(query)
        || establishment.phone?.includes(query)
      ))
    })

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

    const notifyDiscoverError = (error) => {
      $q.notify({ type: 'negative', message: error.response?.data?.error?.message || 'Erro ao buscar estabelecimentos' })
    }

    const searchDiscover = async ({ force = false } = {}) => {
      try {
        await discoverSearch.run(discoverQuery.value, { force })
      } catch (error) {
        notifyDiscoverError(error)
      }
    }

    const openEstablishment = (id) => {
      router.push(`/app/establishments/${id}`)
    }

    const openBusiness = (businessId) => {
      if (!businessId) return
      router.push(`/app/businesses/${businessId}`)
    }

    const isLinkedEstablishment = (id) => linkedEstablishmentIds.value.has(Number(id))

    const openCreateDialog = () => {
      isEditing.value = false
      form.value = {
        business_id: businesses.value.length === 1 ? businesses.value[0].id : null,
        name: '',
        slug: '',
        description: '',
        address: '',
        phone: '',
        email: '',
        timezone: 'America/Sao_Paulo',
        opens_at: '',
        closes_at: '',
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
        Object.entries(form.value).forEach(([key, value]) => {
          if (value !== '' && value !== null && value !== undefined) {
            payload[key] = value
          }
        })

        if (isEditing.value) {
          delete payload.business_id
          await api.put(`/establishments/${selectedEstablishment.value.id}`, payload)
          $q.notify({ type: 'positive', message: 'Estabelecimento atualizado com sucesso' })
        } else {
          await api.post('/establishments', payload)
          $q.notify({ type: 'positive', message: 'Estabelecimento criado com sucesso' })
        }

        closeDialog()
        await fetchEstablishments()
      } catch (err) {
        console.error('Erro ao salvar:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const editEstablishment = (establishment) => {
      isEditing.value = true
      selectedEstablishment.value = establishment
      form.value = {
        business_id: establishment.business_id || null,
        name: establishment.name || '',
        slug: establishment.slug || '',
        description: establishment.description || '',
        address: establishment.address || '',
        phone: establishment.phone || '',
        email: establishment.email || '',
        timezone: establishment.timezone || 'America/Sao_Paulo',
        opens_at: establishment.opens_at || '',
        closes_at: establishment.closes_at || '',
      }
      showDialog.value = true
    }

    const confirmDelete = (establishment) => {
      selectedEstablishment.value = establishment
      showDeleteDialog.value = true
    }

    const deleteEstablishment = async () => {
      deleting.value = true
      try {
        await api.delete(`/establishments/${selectedEstablishment.value.id}`)
        $q.notify({ type: 'positive', message: 'Estabelecimento excluído com sucesso' })
        showDeleteDialog.value = false
        await fetchEstablishments()
      } catch (err) {
        const message = err.response?.data?.error?.message || 'Erro ao excluir'
        $q.notify({ type: 'negative', message })
      } finally {
        deleting.value = false
      }
    }

    const formatDate = (dateString) => {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('pt-BR')
    }

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
          value: discoverEstablishments.value,
        })
        return
      }

      if (discoverSearched.value) {
        return
      }

      searchDiscover()
    })

    onMounted(async () => {
      await fetchUserRole()
      await Promise.all([
        fetchEstablishments(),
        canManage.value ? fetchBusinesses() : Promise.resolve(),
      ])

      if (!canManage.value && establishments.value.length === 0) {
        activeTab.value = 'explore'
      }
    })

    return {
      activeTab,
      discoverEstablishments,
      loading,
      saving,
      deleting,
      searchingDiscover,
      discoverSearched,
      searchQuery,
      discoverQuery,
      showDialog,
      showDeleteDialog,
      isEditing,
      selectedEstablishment,
      form,
      timezoneOptions,
      canManage,
      businessOptions,
      filteredEstablishments,
      openEstablishment,
      openBusiness,
      isLinkedEstablishment,
      openCreateDialog,
      editEstablishment,
      confirmDelete,
      deleteEstablishment,
      closeDialog,
      saveEstablishment,
      searchDiscover,
      formatDate,
    }
  },
})
</script>

<style lang="scss" scoped>
.establishments-page {
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

.establishment-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1rem;
}

.establishment-card {
  padding: 1.1rem;
  cursor: pointer;
  border-radius: 16px;
  transition: transform 0.18s ease, box-shadow 0.18s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: var(--qm-shadow-lg);
  }
}

.establishment-card__header {
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

.establishment-card__title {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-width: 0;
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
  flex-shrink: 0;
}

.establishment-card__desc {
  margin: 0 0 0.875rem;
  color: var(--qm-text-secondary);
  font-size: 0.875rem;
  min-height: 2.6em;
}

.establishment-card__meta {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  color: var(--qm-text-muted);
  font-size: 0.75rem;
}

.establishment-card__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
  flex-wrap: wrap;
}

.timezone-badge {
  font-size: 0.6875rem;
  flex-shrink: 0;
}

.timezone-badge--neutral,
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

.dialog-card-wide {
  min-width: 520px;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dialog-content {
  padding-top: 0;
}

.dialog-actions {
  gap: 0.5rem;
}

.delete-warning {
  color: var(--qm-error);
  font-size: 0.8125rem;
  margin-top: 0.5rem;
}

@media (max-width: 768px) {
  .panel-header {
    flex-direction: column;
  }

  .search-input {
    width: 100%;
  }

  .dialog-card,
  .dialog-card-wide {
    min-width: 0;
    width: calc(100vw - 24px);
  }
}

@media (max-width: 560px) {
  .establishment-card__header,
  .establishment-card__meta {
    flex-direction: column;
  }

  .establishment-card__actions {
    justify-content: stretch;

    :deep(.q-btn) {
      width: 100%;
    }
  }
}
</style>
