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

      <!-- Plan & Subscription Card (only for managers) -->
      <div v-if="user.role === 'manager' || user.role === 'admin'" class="soft-card q-mb-lg">
        <h2 class="section-title">Plano & Assinatura</h2>
        <div v-if="subscriptionLoading" class="loading-state" style="padding: 1rem 0;">
          <q-spinner-dots color="primary" size="28px" />
        </div>
        <template v-else-if="userBusinesses.length > 0">
          <div v-for="biz in userBusinesses" :key="biz.id" class="biz-plan-item">
            <div class="biz-plan-header">
              <q-icon name="business" size="20px" color="primary" />
              <span class="biz-plan-name">{{ biz.name }}</span>
            </div>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Plano Atual</span>
                <q-badge :color="biz._plan ? 'primary' : 'grey'" :label="biz._plan?.name || 'Sem plano'" />
              </div>
              <div class="detail-item">
                <span class="detail-label">Negócios</span>
                <span class="detail-value">{{ biz._plan?.max_businesses ?? '∞' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Estabelecimentos</span>
                <span class="detail-value">{{ biz._plan?.max_establishments_per_business ?? '∞' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Gerentes</span>
                <span class="detail-value">{{ biz._plan?.max_managers ?? '∞' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Profissionais</span>
                <span class="detail-value">{{ biz._plan?.max_professionals_per_establishment ?? '∞' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Status</span>
                <q-badge :color="biz._subscription?.status === 'active' ? 'positive' : 'grey'" :label="biz._subscription?.status === 'active' ? 'Ativa' : (biz._subscription?.status || 'N/A')" />
              </div>
            </div>
            <!-- Admin: change plan -->
            <div v-if="isAdmin" class="change-plan-row q-mt-sm">
              <q-select
                v-model="biz._selectedPlanId"
                :options="planOptions"
                label="Alterar plano"
                outlined dense
                emit-value map-options
                style="flex: 1; max-width: 300px;"
              />
              <q-btn
                color="primary" label="Salvar Plano" no-caps size="sm"
                :loading="biz._savingPlan"
                :disable="!biz._selectedPlanId || biz._selectedPlanId === biz._subscription?.plan_id"
                @click="changeUserPlan(biz)"
              />
            </div>
          </div>
        </template>
        <div v-else class="empty-state" style="padding: 1rem 0;">
          <p>Este usuário não possui negócios vinculados.</p>
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

    // Plan / Subscription state
    const subscriptionLoading = ref(false)
    const userBusinesses = ref([])
    const allPlans = ref([])
    const planOptions = computed(() => allPlans.value.filter(p => p.is_active).map(p => ({ label: p.name, value: p.id })))

    const roleOptions = [
      { label: 'Cliente', value: 'client' },
      { label: 'Profissional', value: 'professional' },
      { label: 'Gerente', value: 'manager' },
      { label: 'Administrador', value: 'admin' }
    ]

    const isAdmin = computed(() => currentUserRole.value === 'admin')
    const userAvatarUrl = computed(() => {
      if (!user.value?.id) return ''
      return `${import.meta.env.VITE_API_URL || 'http://localhost/api/v1'}/users/${user.value.id}/avatar`
    })

    const goBack = () => router.push('/app/admin')

    const fetchUser = async () => {
      loading.value = true
      try {
        const response = await api.get(`/users/${route.params.id}`)
        if (response.data?.success) {
          user.value = response.data.data?.user || null
        }
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

    // Fetch businesses/subscriptions/plans for plan card
    const fetchUserSubscription = async () => {
      if (!user.value || !['manager', 'admin'].includes(user.value.role)) return
      if (currentUserRole.value !== 'admin') return
      subscriptionLoading.value = true
      try {
        // Fetch all businesses to find ones owned by this user
        const bizResp = await api.get('/businesses')
        const allBiz = bizResp.data?.data?.businesses || []
        const owned = allBiz.filter(b => String(b.owner_user_id) === String(route.params.id))

        // Fetch subscriptions and plans
        const [subsResp, plansResp] = await Promise.all([
          api.get('/admin/subscriptions'),
          api.get('/admin/plans')
        ])
        const subs = subsResp.data?.data?.subscriptions || []
        allPlans.value = plansResp.data?.data?.plans || []

        // Match each business to its subscription and plan
        userBusinesses.value = owned.map(b => {
          const sub = subs.find(s => String(s.business_id) === String(b.id) && s.status === 'active')
          const plan = sub ? allPlans.value.find(p => String(p.id) === String(sub.plan_id)) : null
          return {
            ...b,
            _subscription: sub || null,
            _plan: plan || null,
            _selectedPlanId: sub?.plan_id || null,
            _savingPlan: false
          }
        })
      } catch (err) {
        console.error('Erro ao buscar subscriptions:', err)
      } finally {
        subscriptionLoading.value = false
      }
    }

    const changeUserPlan = async (biz) => {
      biz._savingPlan = true
      try {
        if (biz._subscription) {
          await api.put(`/admin/subscriptions/${biz._subscription.id}`, { plan_id: biz._selectedPlanId })
        } else {
          await api.post('/admin/subscriptions', { business_id: biz.id, plan_id: biz._selectedPlanId, status: 'active' })
        }
        $q.notify({ type: 'positive', message: 'Plano alterado com sucesso' })
        fetchUserSubscription()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao alterar plano' })
      } finally {
        biz._savingPlan = false
      }
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
      await fetchUser()
      fetchUserSubscription()
    })

    return {
      user, loading, saving, isAdmin, userAvatarUrl,
      showEditDialog, editForm, roleOptions,
      subscriptionLoading, userBusinesses, planOptions,
      goBack, openEdit, saveUser, changeUserPlan,
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

// Plan / Subscription Card
.biz-plan-item {
  padding: 1rem 0;
  &:not(:last-child) { border-bottom: 1px solid var(--qm-border-color, #e0e0e0); }
}

.biz-plan-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.biz-plan-name {
  font-weight: 600;
  font-size: 1rem;
  color: var(--qm-text-primary);
}

.change-plan-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
</style>
