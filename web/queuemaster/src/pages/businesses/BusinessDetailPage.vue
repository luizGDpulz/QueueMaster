<template>
  <q-page class="detail-page">
    <div class="page-header">
      <div class="header-back-row">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
      </div>
      <div class="header-left">
        <h1 class="page-title">{{ business?.name || '\u00A0' }}</h1>
      </div>
      <div class="header-right" v-if="canManage">
        <q-btn flat icon="edit" label="Editar" no-caps @click="openEdit" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Detalhes do negócio</p>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="business">
      <div class="soft-card main-card">
        <q-tabs
          v-model="mainTab"
          dense
          class="main-tabs"
          active-color="primary"
          indicator-color="primary"
          align="left"
          narrow-indicator
        >
          <q-tab name="info" icon="info" label="Informações" no-caps />
          <q-tab name="establishments" icon="store" label="Estabelecimentos" no-caps />
          <q-tab name="professionals" icon="badge" label="Profissionais" no-caps />
        </q-tabs>

        <q-separator />

        <q-tab-panels v-model="mainTab" animated class="tab-panels">
          <q-tab-panel name="info" class="tab-panel-padded">
            <div class="panel-header">
              <div class="panel-header-text">
                <h3>Informações</h3>
                <p>Dados gerais do negócio</p>
              </div>
            </div>

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
                <span class="detail-label">Seu papel</span>
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
          </q-tab-panel>

          <q-tab-panel name="establishments" class="tab-panel-padded">
            <div class="panel-header">
              <div class="panel-header-text">
                <h3>Estabelecimentos</h3>
                <p>Locais vinculados a este negócio</p>
              </div>
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
          </q-tab-panel>

          <q-tab-panel name="professionals" class="tab-panel-padded">
            <div class="panel-header">
              <div class="panel-header-text">
                <h3>{{ invitationView ? 'Solicitações e convites' : 'Profissionais do negócio' }}</h3>
                <p>{{ invitationView ? 'Aprove ou negue vínculos profissionais' : 'Equipe profissional vinculada a este negócio' }}</p>
              </div>
              <div class="panel-header-actions" v-if="canManage">
                <q-btn
                  flat
                  round
                  dense
                  icon="mail"
                  class="invitation-toggle-btn"
                  @click="toggleInvitationView"
                >
                  <q-badge v-if="pendingInvitationCount > 0" color="negative" floating rounded>{{ pendingInvitationCount }}</q-badge>
                  <q-tooltip>{{ invitationView ? 'Voltar aos profissionais' : 'Ver solicitações e convites' }}</q-tooltip>
                </q-btn>
                <q-btn
                  color="primary"
                  :icon="invitationView ? 'group' : 'person_add'"
                  :label="invitationView ? 'Profissionais' : 'Convidar por email'"
                  no-caps
                  size="sm"
                  @click="invitationView ? toggleInvitationView() : openInviteDialog()"
                />
              </div>
            </div>

            <div v-if="invitationView">
              <div v-if="loadingInvitations" class="loading-state-sm">
                <q-spinner-dots color="primary" size="30px" />
              </div>
              <div v-else-if="invitations.length === 0" class="empty-state-sm">
                <q-icon name="mail" size="40px" />
                <p>Nenhuma solicitação pendente</p>
              </div>
              <div v-else class="list-items">
                <div v-for="inv in invitations" :key="inv.id" class="list-item">
                  <div class="list-item-info">
                    <div class="list-item-avatar" :class="{ 'avatar-incoming': inv.direction === 'professional_to_business' }">
                      <q-icon :name="inv.direction === 'professional_to_business' ? 'person_add' : 'mail_outline'" size="20px" />
                    </div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ getInvitationTitle(inv) }}</span>
                      <span class="list-item-meta">
                        <q-badge :color="getInviteStatusColor(inv.status)" :label="getInviteStatusLabel(inv.status)" />
                        <span> · {{ getInvitationDirectionLabel(inv.direction) }}</span>
                        <span v-if="inv.establishment_name"> · {{ inv.establishment_name }}</span>
                      </span>
                    </div>
                  </div>
                  <div class="list-item-side">
                    <template v-if="inv.status === 'pending' && inv.direction === 'professional_to_business'">
                      <q-btn
                        flat
                        round
                        dense
                        icon="check_circle"
                        size="sm"
                        color="positive"
                        :loading="invitationActionId === inv.id && invitationAction === 'accept'"
                        @click.stop="acceptInvitation(inv)"
                      />
                      <q-btn
                        flat
                        round
                        dense
                        icon="cancel"
                        size="sm"
                        color="negative"
                        :loading="invitationActionId === inv.id && invitationAction === 'reject'"
                        @click.stop="rejectInvitation(inv)"
                      />
                    </template>
                    <q-btn
                      v-else-if="inv.status === 'pending'"
                      flat
                      round
                      dense
                      icon="cancel"
                      size="sm"
                      color="negative"
                      :loading="invitationActionId === inv.id && invitationAction === 'cancel'"
                      @click.stop="cancelInvitation(inv)"
                    />
                  </div>
                </div>
              </div>
            </div>

            <template v-else>
              <div v-if="loadingUsers" class="loading-state-sm">
                <q-spinner-dots color="primary" size="30px" />
              </div>
              <div v-else-if="professionalMembers.length === 0" class="empty-state-sm">
                <q-icon name="badge" size="40px" />
                <p>Nenhum profissional vinculado</p>
              </div>
              <div v-else class="list-items">
                <div v-for="u in professionalMembers" :key="u.id" class="list-item">
                  <div class="list-item-info">
                    <div class="list-item-avatar">
                      <q-icon name="person" size="20px" />
                    </div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ u.name || u.email }}</span>
                      <span class="list-item-meta">
                        {{ u.email }}
                        <span v-if="u.professional_establishments_label"> · {{ u.professional_establishments_label }}</span>
                      </span>
                    </div>
                  </div>
                  <div class="list-item-side">
                    <q-badge color="info" :label="getRoleLabel(u.business_role || u.role)" />
                    <q-btn v-if="canManage" flat round dense icon="remove_circle" size="sm" color="negative" @click.stop="confirmRemoveUser(u)" />
                  </div>
                </div>
              </div>
            </template>
          </q-tab-panel>
        </q-tab-panels>
      </div>
    </template>

    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar negócio</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" label="Nome do negócio *" outlined dense />
          <q-input v-model="editForm.slug" label="Slug" outlined dense class="q-mt-md" />
          <q-input v-model="editForm.description" label="Descrição" outlined dense type="textarea" class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveBusiness" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showRemoveUserConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Remover profissional</div>
        </q-card-section>
        <q-card-section>
          <p>Tem certeza que deseja remover <strong>{{ userToRemove?.name || userToRemove?.email }}</strong> deste negócio?</p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showRemoveUserConfirm = false" />
          <q-btn color="negative" label="Remover" no-caps :loading="removingUser" @click="removeUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showInviteDialog" persistent>
      <q-card class="dialog-card invite-dialog">
        <q-card-section class="dialog-header">
          <div class="text-h6">Convidar profissional</div>
          <q-btn flat round dense icon="close" @click="closeInviteDialog" />
        </q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input
            v-model="inviteForm.email"
            label="Email do profissional *"
            outlined
            dense
            type="email"
            @keyup.enter="searchInviteTarget"
          >
            <template #append>
              <q-btn flat round dense icon="search" :loading="searchingInviteTarget" @click="searchInviteTarget" />
            </template>
          </q-input>

          <div v-if="inviteSearchError" class="invite-search-error">{{ inviteSearchError }}</div>

          <div v-if="inviteTarget" class="invite-target-card">
            <div class="invite-target-card__identity">
              <div class="invite-target-card__avatar">
                <q-icon name="person" size="20px" />
              </div>
              <div class="invite-target-card__details">
                <span class="invite-target-card__name">{{ inviteTarget.name || inviteTarget.email }}</span>
                <span class="invite-target-card__meta">{{ inviteTarget.email }} · {{ getRoleLabel(inviteTarget.role) }}</span>
              </div>
            </div>
            <q-badge v-if="inviteAlreadyLinked" color="warning" label="Já vinculado ao negócio" />
          </div>

          <q-select
            v-model="inviteForm.establishment_id"
            outlined
            dense
            emit-value
            map-options
            :options="establishmentOptions"
            label="Estabelecimento da atuação *"
            :loading="loadingEstablishments"
          />

          <q-input v-model="inviteForm.message" outlined dense type="textarea" label="Mensagem (opcional)" autogrow />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeInviteDialog" />
          <q-btn color="primary" label="Enviar convite" no-caps :loading="sendingInvite" @click="sendInvitation" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { computed, defineComponent, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'

export default defineComponent({
  name: 'BusinessDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const mainTab = ref(route.query.tab === 'professionals' ? 'professionals' : 'info')
    const invitationView = ref(route.query.view === 'invitations')
    const business = ref(null)
    const currentUserRole = ref(null)
    const establishments = ref([])
    const businessUsers = ref([])
    const invitations = ref([])
    const loading = ref(true)
    const loadingEstablishments = ref(false)
    const loadingUsers = ref(false)
    const loadingInvitations = ref(false)
    const saving = ref(false)
    const showEditDialog = ref(false)
    const showRemoveUserConfirm = ref(false)
    const showInviteDialog = ref(false)
    const removingUser = ref(false)
    const sendingInvite = ref(false)
    const searchingInviteTarget = ref(false)
    const invitationActionId = ref(null)
    const invitationAction = ref('')

    const editForm = ref({ name: '', slug: '', description: '' })
    const userToRemove = ref(null)
    const inviteForm = ref({ email: '', establishment_id: null, message: '' })
    const inviteTarget = ref(null)
    const inviteAlreadyLinked = ref(false)
    const inviteSearchError = ref('')

    const canManage = computed(() => ['admin', 'manager'].includes(currentUserRole.value))
    const professionalMembers = computed(() => {
      return businessUsers.value
        .filter(user => (user.business_role || user.role) === 'professional')
        .sort((a, b) => (a.name || a.email || '').localeCompare(b.name || b.email || '', 'pt-BR'))
    })
    const pendingInvitationCount = computed(() => invitations.value.filter(inv => inv.status === 'pending').length)
    const establishmentOptions = computed(() => {
      return establishments.value.map(est => ({
        label: est.name,
        value: est.id,
      }))
    })

    const syncRouteQuery = () => {
      const nextQuery = { ...route.query }

      if (mainTab.value === 'professionals') nextQuery.tab = 'professionals'
      else delete nextQuery.tab

      if (mainTab.value === 'professionals' && invitationView.value) nextQuery.view = 'invitations'
      else delete nextQuery.view

      router.replace({ query: nextQuery })
    }

    watch(mainTab, async (tab) => {
      syncRouteQuery()
      if (tab === 'establishments' && establishments.value.length === 0) await fetchEstablishments()
      if (tab === 'professionals' && businessUsers.value.length === 0) await fetchBusinessUsers()
      if (tab === 'professionals' && invitationView.value && invitations.value.length === 0) await fetchInvitations()
    })

    watch(invitationView, async (value) => {
      syncRouteQuery()
      if (mainTab.value === 'professionals' && value && invitations.value.length === 0) await fetchInvitations()
    })

    const goBack = () => router.push('/app/businesses')

    const fetchCurrentUser = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          currentUserRole.value = response.data.data?.user?.role || null
        }
      } catch (err) {
        console.error('Erro ao buscar usuário atual:', err)
      }
    }

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

    const fetchBusinessUsers = async () => {
      loadingUsers.value = true
      try {
        const response = await api.get(`/businesses/${route.params.id}/users`)
        if (response.data?.success) {
          businessUsers.value = response.data.data?.users || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar usuários:', err)
      } finally {
        loadingUsers.value = false
      }
    }

    const fetchInvitations = async () => {
      loadingInvitations.value = true
      try {
        const response = await api.get(`/businesses/${route.params.id}/invitations`, {
          params: { status: 'pending' }
        })
        if (response.data?.success) {
          invitations.value = response.data.data?.invitations || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar convites:', err)
      } finally {
        loadingInvitations.value = false
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
        await fetchBusiness()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const confirmRemoveUser = (user) => {
      userToRemove.value = user
      showRemoveUserConfirm.value = true
    }

    const removeUser = async () => {
      if (!userToRemove.value) return

      removingUser.value = true
      try {
        await api.delete(`/businesses/${route.params.id}/users/${userToRemove.value.id}`)
        $q.notify({ type: 'positive', message: 'Profissional removido com sucesso' })
        showRemoveUserConfirm.value = false
        userToRemove.value = null
        await fetchBusinessUsers()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao remover profissional' })
      } finally {
        removingUser.value = false
      }
    }

    const resetInviteForm = () => {
      inviteForm.value = { email: '', establishment_id: null, message: '' }
      inviteTarget.value = null
      inviteAlreadyLinked.value = false
      inviteSearchError.value = ''
    }

    const openInviteDialog = async () => {
      resetInviteForm()
      showInviteDialog.value = true
      if (establishments.value.length === 0) {
        await fetchEstablishments()
      }
    }

    const closeInviteDialog = () => {
      if (sendingInvite.value || searchingInviteTarget.value) return
      showInviteDialog.value = false
      resetInviteForm()
    }

    const searchInviteTarget = async () => {
      inviteSearchError.value = ''
      inviteTarget.value = null
      inviteAlreadyLinked.value = false

      if (!inviteForm.value.email?.trim()) {
        inviteSearchError.value = 'Informe um email para pesquisar'
        return
      }

      searchingInviteTarget.value = true
      try {
        const response = await api.get(`/businesses/${route.params.id}/invitation-target`, {
          params: { email: inviteForm.value.email.trim() }
        })
        if (response.data?.success) {
          inviteTarget.value = response.data.data?.user || null
          inviteAlreadyLinked.value = !!response.data.data?.already_linked
        }
      } catch (err) {
        inviteSearchError.value = err.response?.data?.error?.message || 'Não foi possível localizar esse usuário'
      } finally {
        searchingInviteTarget.value = false
      }
    }

    const sendInvitation = async () => {
      if (!inviteTarget.value) {
        $q.notify({ type: 'warning', message: 'Pesquise e selecione o profissional primeiro' })
        return
      }

      if (!inviteForm.value.establishment_id) {
        $q.notify({ type: 'warning', message: 'Selecione o estabelecimento da atuação' })
        return
      }

      sendingInvite.value = true
      try {
        await api.post(`/businesses/${route.params.id}/invitations`, {
          email: inviteTarget.value.email,
          role: 'professional',
          establishment_id: inviteForm.value.establishment_id,
          message: inviteForm.value.message?.trim() || undefined,
        })
        $q.notify({ type: 'positive', message: 'Convite enviado com sucesso' })
        closeInviteDialog()
        if (invitationView.value) await fetchInvitations()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao enviar convite' })
      } finally {
        sendingInvite.value = false
      }
    }

    const withInvitationAction = async (invitation, actionName, callback) => {
      invitationActionId.value = invitation.id
      invitationAction.value = actionName
      try {
        await callback()
      } finally {
        invitationActionId.value = null
        invitationAction.value = ''
      }
    }

    const acceptInvitation = async (invitation) => {
      await withInvitationAction(invitation, 'accept', async () => {
        try {
          await api.post(`/invitations/${invitation.id}/accept`)
          $q.notify({ type: 'positive', message: 'Solicitação aceita com sucesso' })
          await Promise.all([fetchInvitations(), fetchBusinessUsers()])
        } catch (err) {
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao aceitar solicitação' })
        }
      })
    }

    const rejectInvitation = async (invitation) => {
      await withInvitationAction(invitation, 'reject', async () => {
        try {
          await api.post(`/invitations/${invitation.id}/reject`)
          $q.notify({ type: 'positive', message: 'Solicitação rejeitada' })
          await fetchInvitations()
        } catch (err) {
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao rejeitar solicitação' })
        }
      })
    }

    const cancelInvitation = async (invitation) => {
      await withInvitationAction(invitation, 'cancel', async () => {
        try {
          await api.post(`/invitations/${invitation.id}/cancel`)
          $q.notify({ type: 'positive', message: 'Convite cancelado' })
          await fetchInvitations()
        } catch (err) {
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao cancelar convite' })
        }
      })
    }

    const toggleInvitationView = async () => {
      invitationView.value = !invitationView.value
      if (invitationView.value && invitations.value.length === 0) {
        await fetchInvitations()
      }
      if (!invitationView.value && businessUsers.value.length === 0) {
        await fetchBusinessUsers()
      }
    }

    const formatDate = (date) => (date ? new Date(date).toLocaleDateString('pt-BR') : '-')
    const getRoleLabel = (role) => ({ owner: 'Proprietário', manager: 'Gerente', professional: 'Profissional' }[role] || role || '-')
    const getRoleColor = (role) => ({ owner: 'positive', manager: 'info', professional: 'purple' }[role] || 'grey')
    const getInviteStatusColor = (status) => ({ pending: 'warning', accepted: 'positive', rejected: 'negative', cancelled: 'grey' }[status] || 'grey')
    const getInviteStatusLabel = (status) => ({ pending: 'Pendente', accepted: 'Aceito', rejected: 'Rejeitado', cancelled: 'Cancelado' }[status] || status)
    const getInvitationDirectionLabel = (direction) => (
      direction === 'professional_to_business' ? 'Solicitação recebida' : 'Convite enviado'
    )
    const getInvitationTitle = (invitation) => {
      if (invitation.direction === 'professional_to_business') {
        return invitation.from_user_name || invitation.from_user_email || `Solicitação #${invitation.id}`
      }
      return invitation.to_user_name || invitation.to_user_email || `Convite #${invitation.id}`
    }

    onMounted(async () => {
      await fetchCurrentUser()
      await fetchBusiness()
      if (canManage.value) {
        await fetchInvitations()
      }

      if (mainTab.value === 'professionals') {
        await fetchBusinessUsers()
      }

      if (route.query.tab === 'establishments') {
        mainTab.value = 'establishments'
        await fetchEstablishments()
      }
    })

    return {
      mainTab,
      invitationView,
      business,
      loading,
      loadingEstablishments,
      loadingUsers,
      loadingInvitations,
      saving,
      canManage,
      establishments,
      businessUsers,
      professionalMembers,
      invitations,
      pendingInvitationCount,
      invitationActionId,
      invitationAction,
      showEditDialog,
      showRemoveUserConfirm,
      showInviteDialog,
      editForm,
      userToRemove,
      inviteForm,
      inviteTarget,
      inviteAlreadyLinked,
      inviteSearchError,
      searchingInviteTarget,
      sendingInvite,
      establishmentOptions,
      goBack,
      openEdit,
      saveBusiness,
      confirmRemoveUser,
      removeUser,
      openInviteDialog,
      closeInviteDialog,
      searchInviteTarget,
      sendInvitation,
      acceptInvitation,
      rejectInvitation,
      cancelInvitation,
      toggleInvitationView,
      formatDate,
      getRoleLabel,
      getRoleColor,
      getInviteStatusColor,
      getInviteStatusLabel,
      getInvitationDirectionLabel,
      getInvitationTitle,
    }
  },
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

.main-card { padding: 0; overflow: hidden; }
.main-tabs { padding: 0.5rem 1rem 0; }
.tab-panels { background: transparent; min-height: 220px; }
.tab-panel-padded { padding: 1.5rem; }

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.25rem;

  h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: var(--qm-text-primary);
  }

  p {
    font-size: 0.8125rem;
    color: var(--qm-text-muted);
    margin: 0.125rem 0 0;
  }
}

.panel-header-text { flex: 1; }
.panel-header-actions { display: flex; gap: 0.5rem; align-items: center; }
.invitation-toggle-btn { color: var(--qm-text-secondary); }

.avatar-incoming {
  background: rgba(139, 92, 246, 0.12);
  color: #8b5cf6;
}

.invite-dialog {
  min-width: 480px;
  max-width: 560px;
}

.invite-search-error {
  font-size: 0.8125rem;
  color: #ef4444;
}

.invite-target-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 1rem;
  border-radius: 14px;
  background: var(--qm-bg-secondary);
}

.invite-target-card__identity {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.invite-target-card__avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  flex-shrink: 0;
}

.invite-target-card__details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.invite-target-card__name {
  font-weight: 600;
  color: var(--qm-text-primary);
}

.invite-target-card__meta {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 768px) {
  .invite-dialog {
    min-width: 0;
    width: calc(100vw - 24px);
  }

  .panel-header {
    flex-direction: column;
    gap: 0.75rem;
  }

  .panel-header-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
