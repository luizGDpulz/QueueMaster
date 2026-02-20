<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
    <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <h1 class="page-title">{{ business?.name || '\u00A0' }}</h1>
      </div>
      <div class="header-right">
        <q-btn flat icon="edit" label="Editar" no-caps @click="openEdit" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Detalhes do negócio</p>
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

      <!-- Users Card -->
      <div class="soft-card q-mb-lg">
        <div class="section-header">
          <h2 class="section-title">Usuários</h2>
          <q-btn flat dense icon="person_add" label="Adicionar" no-caps size="sm" @click="showAddUserDialog = true" />
        </div>

        <div v-if="loadingUsers" class="loading-state-sm">
          <q-spinner-dots color="primary" size="30px" />
        </div>
        <div v-else-if="businessUsers.length === 0" class="empty-state-sm">
          <q-icon name="people" size="40px" />
          <p>Nenhum usuário vinculado</p>
        </div>
        <div v-else class="list-items">
          <div v-for="u in businessUsers" :key="u.id" class="list-item">
            <div class="list-item-info">
              <div class="list-item-avatar">
                <q-icon name="person" size="20px" />
              </div>
              <div class="list-item-details">
                <span class="list-item-name">{{ u.name || u.email }}</span>
                <span class="list-item-meta">{{ u.email }} · {{ getRoleLabel(u.role || u.business_role) }}</span>
              </div>
            </div>
            <div class="list-item-side">
              <q-btn flat round dense icon="remove_circle" size="sm" color="negative" @click.stop="confirmRemoveUser(u)" />
            </div>
          </div>
        </div>
      </div>

      <!-- Invitations Card -->
      <div class="soft-card">
        <div class="section-header">
          <h2 class="section-title">Convites</h2>
          <q-btn flat dense icon="send" label="Convidar" no-caps size="sm" @click="showInviteDialog = true" />
        </div>

        <div v-if="loadingInvitations" class="loading-state-sm">
          <q-spinner-dots color="primary" size="30px" />
        </div>
        <div v-else-if="invitations.length === 0" class="empty-state-sm">
          <q-icon name="mail" size="40px" />
          <p>Nenhum convite pendente</p>
        </div>
        <div v-else class="list-items">
          <div v-for="inv in invitations" :key="inv.id" class="list-item">
            <div class="list-item-info">
              <div class="list-item-avatar">
                <q-icon name="mail_outline" size="20px" />
              </div>
              <div class="list-item-details">
                <span class="list-item-name">{{ inv.email || inv.user_name || 'Convite #' + inv.id }}</span>
                <span class="list-item-meta">
                  <q-badge :color="getInviteStatusColor(inv.status)" :label="getInviteStatusLabel(inv.status)" />
                </span>
              </div>
            </div>
            <div class="list-item-side">
              <q-btn v-if="inv.status === 'pending'" flat round dense icon="cancel" size="sm" color="negative" @click.stop="cancelInvitation(inv)" />
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

    <!-- Add User Dialog -->
    <q-dialog v-model="showAddUserDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Adicionar Usuário</div>
          <q-btn flat round dense icon="close" @click="showAddUserDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="addUserForm.user_id" label="ID do Usuário *" outlined dense type="number" />
          <q-select v-model="addUserForm.role" label="Papel" outlined dense :options="businessRoleOptions" emit-value map-options class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showAddUserDialog = false" />
          <q-btn color="primary" label="Adicionar" no-caps :loading="addingUser" @click="addUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Remove User Confirm -->
    <q-dialog v-model="showRemoveUserConfirm">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Remover Usuário</div>
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

    <!-- Invite Dialog -->
    <q-dialog v-model="showInviteDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Convidar Profissional</div>
          <q-btn flat round dense icon="close" @click="showInviteDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="inviteForm.email" label="Email do profissional *" outlined dense type="email" />
          <q-select v-model="inviteForm.role" label="Papel" outlined dense :options="businessRoleOptions" emit-value map-options class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showInviteDialog = false" />
          <q-btn color="primary" label="Enviar Convite" no-caps :loading="sendingInvite" @click="sendInvitation" />
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

    // Users state
    const businessUsers = ref([])
    const loadingUsers = ref(false)
    const showAddUserDialog = ref(false)
    const showRemoveUserConfirm = ref(false)
    const userToRemove = ref(null)
    const addingUser = ref(false)
    const removingUser = ref(false)
    const addUserForm = ref({ user_id: '', role: 'professional' })

    // Invitations state
    const invitations = ref([])
    const loadingInvitations = ref(false)
    const showInviteDialog = ref(false)
    const sendingInvite = ref(false)
    const inviteForm = ref({ email: '', role: 'professional' })

    const businessRoleOptions = [
      { label: 'Profissional', value: 'professional' },
      { label: 'Gerente', value: 'manager' }
    ]

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
    const getRoleLabel = (r) => ({ owner: 'Proprietário', manager: 'Gerente', professional: 'Profissional' }[r] || r || '-')
    const getRoleColor = (r) => ({ owner: 'positive', manager: 'info', professional: 'purple' }[r] || 'grey')

    // Users methods
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

    const addUser = async () => {
      if (!addUserForm.value.user_id) {
        $q.notify({ type: 'warning', message: 'ID do usuário é obrigatório' })
        return
      }
      addingUser.value = true
      try {
        await api.post(`/businesses/${route.params.id}/users`, {
          user_id: parseInt(addUserForm.value.user_id),
          role: addUserForm.value.role
        })
        $q.notify({ type: 'positive', message: 'Usuário adicionado com sucesso' })
        showAddUserDialog.value = false
        addUserForm.value = { user_id: '', role: 'professional' }
        fetchBusinessUsers()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao adicionar usuário' })
      } finally {
        addingUser.value = false
      }
    }

    const confirmRemoveUser = (user) => {
      userToRemove.value = user
      showRemoveUserConfirm.value = true
    }

    const removeUser = async () => {
      removingUser.value = true
      try {
        await api.delete(`/businesses/${route.params.id}/users/${userToRemove.value.id}`)
        $q.notify({ type: 'positive', message: 'Usuário removido com sucesso' })
        showRemoveUserConfirm.value = false
        fetchBusinessUsers()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao remover usuário' })
      } finally {
        removingUser.value = false
      }
    }

    // Invitations methods
    const fetchInvitations = async () => {
      loadingInvitations.value = true
      try {
        const response = await api.get(`/businesses/${route.params.id}/invitations`)
        if (response.data?.success) {
          invitations.value = response.data.data?.invitations || response.data.data || []
        }
      } catch (err) {
        console.error('Erro ao buscar convites:', err)
      } finally {
        loadingInvitations.value = false
      }
    }

    const sendInvitation = async () => {
      if (!inviteForm.value.email?.trim()) {
        $q.notify({ type: 'warning', message: 'Email é obrigatório' })
        return
      }
      sendingInvite.value = true
      try {
        await api.post(`/businesses/${route.params.id}/invitations`, {
          email: inviteForm.value.email.trim(),
          role: inviteForm.value.role
        })
        $q.notify({ type: 'positive', message: 'Convite enviado com sucesso' })
        showInviteDialog.value = false
        inviteForm.value = { email: '', role: 'professional' }
        fetchInvitations()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao enviar convite' })
      } finally {
        sendingInvite.value = false
      }
    }

    const cancelInvitation = async (inv) => {
      try {
        await api.post(`/invitations/${inv.id}/cancel`)
        $q.notify({ type: 'positive', message: 'Convite cancelado' })
        fetchInvitations()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao cancelar convite' })
      }
    }

    const getInviteStatusColor = (status) => ({ pending: 'warning', accepted: 'positive', rejected: 'negative', cancelled: 'grey' }[status] || 'grey')
    const getInviteStatusLabel = (status) => ({ pending: 'Pendente', accepted: 'Aceito', rejected: 'Rejeitado', cancelled: 'Cancelado' }[status] || status)

    onMounted(() => {
      fetchBusiness()
      fetchEstablishments()
      fetchBusinessUsers()
      fetchInvitations()
    })

    return {
      business, establishments, loading, loadingEstablishments,
      saving, showEditDialog, editForm,
      businessUsers, loadingUsers, showAddUserDialog, showRemoveUserConfirm,
      userToRemove, addingUser, removingUser, addUserForm, businessRoleOptions,
      invitations, loadingInvitations, showInviteDialog, sendingInvite, inviteForm,
      goBack, openEdit, saveBusiness, formatDate, getRoleLabel, getRoleColor,
      addUser, confirmRemoveUser, removeUser,
      sendInvitation, cancelInvitation, getInviteStatusColor, getInviteStatusLabel
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';
</style>
