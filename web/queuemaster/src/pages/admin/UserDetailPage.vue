<template>
  <q-page class="detail-page">
    <!-- Back + Header -->
    <div class="page-header">
      <div class="header-left">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
        <h1 class="page-title">{{ user?.name || '\u00A0' }}</h1>
      </div>
      <div class="header-right" v-if="isAdmin">
        <q-btn flat icon="edit" label="Editar" no-caps @click="openEdit" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Detalhes do usuário</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="user">
      <!-- Profile Card -->
      <div class="soft-card q-mb-lg">
        <div class="profile-header">
          <img v-if="userAvatarUrl" :src="userAvatarUrl" class="profile-avatar" referrerpolicy="no-referrer" />
          <div v-else class="profile-avatar-initials">{{ getInitials(user.name) }}</div>
          <div class="profile-info">
            <h2 class="profile-name">{{ user.name }}</h2>
            <q-badge :color="getRoleColor(user.role)" :label="getRoleLabel(user.role)" />
          </div>
        </div>
      </div>

      <!-- Info Card -->
      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informações</h2>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value">{{ user.email }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Telefone</span>
            <span class="detail-value">{{ user.phone || 'Não informado' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Status</span>
            <q-badge :color="user.is_active ? 'positive' : 'negative'" :label="user.is_active ? 'Ativo' : 'Inativo'" />
          </div>
          <div class="detail-item">
            <span class="detail-label">Email Verificado</span>
            <span class="detail-value">{{ user.email_verified ? 'Sim' : 'Não' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Último Login</span>
            <span class="detail-value">{{ formatDate(user.last_login_at) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Criado em</span>
            <span class="detail-value">{{ formatDate(user.created_at) }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Usuário</div>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" label="Nome" outlined dense />
          <q-input v-model="editForm.email" label="Email" outlined dense class="q-mt-md" />
          <q-input v-model="editForm.phone" label="Telefone" outlined dense class="q-mt-md" />
          <q-select v-model="editForm.role" label="Papel" outlined dense :options="roleOptions" emit-value map-options class="q-mt-md" />
          <q-toggle v-model="editForm.is_active" label="Ativo" class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveUser" />
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
  name: 'UserDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const user = ref(null)
    const loading = ref(true)
    const saving = ref(false)
    const currentUserRole = ref(null)
    const showEditDialog = ref(false)
    const editForm = ref({ name: '', email: '', phone: '', role: 'client', is_active: true })

    const roleOptions = [
      { label: 'Cliente', value: 'client' },
      { label: 'Profissional', value: 'professional' },
      { label: 'Gerente', value: 'manager' },
      { label: 'Administrador', value: 'admin' }
    ]

    const isAdmin = computed(() => currentUserRole.value === 'admin')
    const userAvatarUrl = computed(() => {
      if (!user.value?.id) return ''
      return `http://localhost/api/v1/users/${user.value.id}/avatar`
    })

    const goBack = () => router.push('/app/admin')

    const fetchUser = async () => {
      loading.value = true
      try {
        // Try to get from users list
        const response = await api.get('/users')
        const users = response.data?.data?.users || []
        user.value = users.find(u => u.id == route.params.id) || null
        if (!user.value) {
          $q.notify({ type: 'negative', message: 'Usuário não encontrado' })
          goBack()
        }
      } catch (err) {
        console.error('Erro:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar usuário' })
        goBack()
      } finally {
        loading.value = false
      }
    }

    const fetchCurrentUser = async () => {
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success) {
          currentUserRole.value = response.data.data.user.role
        }
      } catch { /* ignore */ }
    }

    const openEdit = () => {
      editForm.value = {
        name: user.value?.name || '',
        email: user.value?.email || '',
        phone: user.value?.phone || '',
        role: user.value?.role || 'client',
        is_active: user.value?.is_active !== false && user.value?.is_active !== 0
      }
      showEditDialog.value = true
    }

    const saveUser = async () => {
      saving.value = true
      try {
        const payload = {}
        if (editForm.value.name) payload.name = editForm.value.name
        if (editForm.value.email) payload.email = editForm.value.email
        if (editForm.value.phone !== undefined) payload.phone = editForm.value.phone
        if (editForm.value.role) payload.role = editForm.value.role

        await api.put(`/users/${route.params.id}`, payload)
        $q.notify({ type: 'positive', message: 'Usuário atualizado com sucesso' })
        showEditDialog.value = false
        fetchUser()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally {
        saving.value = false
      }
    }

    const getRoleLabel = (role) => ({ admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }[role] || role)
    const getRoleColor = (role) => ({ admin: 'deep-purple', manager: 'blue', professional: 'teal', client: 'grey' }[role] || 'grey')
    const getInitials = (name) => name ? name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() : '?'
    const formatDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR') : '-'

    onMounted(async () => {
      await fetchCurrentUser()
      fetchUser()
    })

    return {
      user, loading, saving, isAdmin, userAvatarUrl,
      showEditDialog, editForm, roleOptions,
      goBack, openEdit, saveUser,
      getRoleLabel, getRoleColor, getInitials, formatDate
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

.profile-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.profile-avatar {
  width: 56px;
  height: 56px;
  border-radius: 1rem;
  object-fit: cover;
}

.profile-avatar-initials {
  width: 56px;
  height: 56px;
  border-radius: 1rem;
  background: var(--qm-brand);
  color: var(--qm-brand-contrast, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.125rem;
  font-weight: 600;
}

.profile-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.profile-name {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  margin: 0;
}
</style>
