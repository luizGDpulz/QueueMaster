<template>
  <q-page class="detail-page">
    <div class="page-header">
      <div class="header-back-row">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
      </div>
      <div class="header-left">
        <h1 class="page-title">{{ user?.name || 'Usuario' }}</h1>
      </div>
      <div class="header-right" v-if="canEditProfile">
        <q-btn flat icon="edit" label="Editar" no-caps @click="openEdit" />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Detalhes administrativos do usuario</p>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando usuario...</p>
    </div>

    <template v-else-if="user">
      <div class="soft-card q-mb-lg profile-hero">
        <div class="profile-hero__header">
          <div class="profile-hero__identity">
            <div class="profile-avatar">
              <img v-if="user.avatar_url" :src="user.avatar_url" alt="" referrerpolicy="no-referrer" />
              <span v-else>{{ getInitials(user.name) }}</span>
            </div>

            <div class="profile-hero__copy">
              <h2 class="profile-name">{{ user.name }}</h2>
              <div class="profile-badges">
                <q-badge :color="getRoleColor(resolvedUserRole)" :label="getRoleLabel(resolvedUserRole)" />
                <q-badge v-if="user.is_owner" color="amber-8" text-color="white" label="Titular / dono" />
                <q-badge v-if="user.is_google_managed_profile" color="blue-grey-7" text-color="white" label="Google" />
                <q-badge
                  :color="accessState.can_authenticate ? 'positive' : 'negative'"
                  text-color="white"
                  :label="accessState.can_authenticate ? 'Acesso permitido' : 'Acesso bloqueado'"
                />
              </div>
              <p v-if="user.is_google_managed_profile" class="profile-hint">
                Nome e email vindos do Google ficam bloqueados para edicao nesta tela.
              </p>
            </div>
          </div>

          <q-badge
            :color="normalizeBoolean(user.is_active) ? 'positive' : 'negative'"
            :label="normalizeBoolean(user.is_active) ? 'Ativo' : 'Inativo'"
          />
        </div>
      </div>

      <div class="soft-card q-mb-lg">
        <div class="section-header">
          <div>
            <h2 class="section-title section-title--compact">Controle de acesso</h2>
            <p class="section-copy">Use o ambiente para fechar staging e o painel admin para agir sem deploy.</p>
          </div>
        </div>

        <div class="detail-grid detail-grid--wide">
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Acesso final</span>
            <span class="detail-value">{{ accessState.can_authenticate ? 'Permitido' : 'Bloqueado' }}</span>
            <small v-if="finalAccessMessage" class="detail-help">{{ finalAccessMessage }}</small>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Regra do ambiente</span>
            <span class="detail-value">{{ environmentState.label }}</span>
            <small v-if="environmentState.reason" class="detail-help">{{ environmentState.reason }}</small>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Status interno</span>
            <span class="detail-value">{{ systemState.label }}</span>
            <small v-if="systemState.blocked_reason" class="detail-help">{{ systemState.blocked_reason }}</small>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Bloqueado por</span>
            <span class="detail-value">{{ blockedByLabel }}</span>
            <small v-if="systemState.blocked_at" class="detail-help">{{ formatDateTime(systemState.blocked_at) }}</small>
          </div>
        </div>

        <div class="access-actions q-mt-md">
          <q-btn
            v-if="canBlockAccess"
            color="negative"
            icon="block"
            label="Bloquear acesso"
            no-caps
            :loading="blockingUser"
            @click="showBlockDialog = true"
          />
          <q-btn
            v-if="canUnblockAccess"
            color="positive"
            icon="lock_open"
            label="Liberar acesso"
            no-caps
            :loading="unblockingUser"
            @click="unblockUserAccess"
          />
          <q-btn
            v-if="canRevokeSessions"
            outline
            color="warning"
            icon="logout"
            label="Encerrar sessoes"
            no-caps
            :loading="revokingSessions"
            @click="showRevokeSessionsDialog = true"
          />
          <q-btn
            v-if="showDeleteAction"
            outline
            color="negative"
            icon="delete_forever"
            label="Excluir cadastro"
            no-caps
            :disable="!canDeleteUser"
            :loading="deletingUser"
            @click="showDeleteDialog = true"
          />
        </div>

        <div v-if="deleteBlockers.length" class="access-blockers q-mt-md">
          <span class="detail-label">Bloqueios para exclusao</span>
          <p v-for="blocker in deleteBlockers" :key="blocker">{{ blocker }}</p>
        </div>
      </div>

      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Informacoes</h2>

        <div class="detail-grid detail-grid--wide">
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Email</span>
            <span class="detail-value">{{ user.email }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Telefone</span>
            <span class="detail-value">{{ user.phone || 'Nao informado' }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Endereco linha 1</span>
            <span class="detail-value">{{ user.address_line_1 || 'Nao informado' }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Endereco linha 2</span>
            <span class="detail-value">{{ user.address_line_2 || 'Nao informado' }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Email verificado</span>
            <span class="detail-value">{{ normalizeBoolean(user.email_verified) ? 'Sim' : 'Nao' }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Ultimo login</span>
            <span class="detail-value">{{ formatDateTime(user.last_login_at) }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Criado em</span>
            <span class="detail-value">{{ formatDateTime(user.created_at) }}</span>
          </div>
          <div class="detail-item detail-item--boxed">
            <span class="detail-label">Provider</span>
            <span class="detail-value">{{ user.auth_provider === 'google' ? 'Google' : 'Local' }}</span>
          </div>
        </div>
      </div>

      <div class="soft-card q-mb-lg">
        <h2 class="section-title">Vinculos contextuais</h2>

        <div class="memberships-grid">
          <section class="membership-column">
            <div class="membership-column__header">
              <h3>Negocios</h3>
              <p>Vinculos diretos em negocios.</p>
            </div>

            <div v-if="memberships.businesses.length === 0" class="empty-state-sm membership-empty">
              <q-icon name="business_center" size="32px" />
              <p>Sem vinculos de negocio.</p>
            </div>

            <div v-else class="list-items">
              <div
                v-for="business in memberships.businesses"
                :key="`business-${business.business_id}-${business.role}`"
                class="list-item"
              >
                <div class="list-item-info">
                  <div class="list-item-avatar">
                    <q-icon name="business" size="18px" />
                  </div>
                  <div class="list-item-details">
                    <span class="list-item-name">{{ business.business_name }}</span>
                    <span class="list-item-meta">{{ getContextRoleLabel(business.role) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="membership-column">
            <div class="membership-column__header">
              <h3>Estabelecimentos</h3>
              <p>Vinculos diretos em estabelecimentos.</p>
            </div>

            <div v-if="memberships.establishments.length === 0" class="empty-state-sm membership-empty">
              <q-icon name="store" size="32px" />
              <p>Sem vinculos de estabelecimento.</p>
            </div>

            <div v-else class="list-items">
              <div
                v-for="establishment in memberships.establishments"
                :key="`est-${establishment.establishment_id}-${establishment.role}`"
                class="list-item"
              >
                <div class="list-item-info">
                  <div class="list-item-avatar">
                    <q-icon name="store" size="18px" />
                  </div>
                  <div class="list-item-details">
                    <span class="list-item-name">{{ establishment.establishment_name }}</span>
                    <span class="list-item-meta">
                      {{ establishment.business_name || 'Sem negocio' }} | {{ getContextRoleLabel(establishment.role) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div v-if="user.is_plan_holder || planAssignment.plan || canEditPlan" class="soft-card q-mb-lg">
        <div class="section-header">
          <div>
            <h2 class="section-title section-title--compact">Plano do titular</h2>
            <p class="section-copy">Apenas o gerente titular do plano recebe e troca assinatura.</p>
          </div>
        </div>

        <div class="plan-card">
          <div class="plan-card__header">
            <div>
              <span class="plan-card__eyebrow">Plano atual</span>
              <h3 class="plan-card__title">{{ planAssignment.plan?.name || 'Sem plano ativo' }}</h3>
            </div>
            <q-badge
              :color="planAssignment.subscription?.status === 'active' ? 'positive' : 'grey-7'"
              :label="formatSubscriptionStatus(planAssignment.subscription?.status)"
            />
          </div>

          <div v-if="planAssignment.plan" class="detail-grid detail-grid--wide q-mt-md">
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Negocios</span>
              <span class="detail-value">{{ formatLimit(planAssignment.plan.max_businesses) }}</span>
            </div>
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Estabelecimentos / negocio</span>
              <span class="detail-value">{{ formatLimit(planAssignment.plan.max_establishments_per_business) }}</span>
            </div>
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Gerentes / negocio</span>
              <span class="detail-value">{{ formatLimit(planAssignment.plan.max_managers) }}</span>
            </div>
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Profissionais / estabelecimento</span>
              <span class="detail-value">{{ formatLimit(planAssignment.plan.max_professionals_per_establishment) }}</span>
            </div>
          </div>

          <div v-if="planAssignment.usage" class="detail-grid detail-grid--wide q-mt-md">
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Negocios em uso</span>
              <span class="detail-value">{{ planAssignment.usage.business_count }}</span>
            </div>
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Maior qtd. de estabelecimentos</span>
              <span class="detail-value">{{ planAssignment.usage.max_establishments_per_business_used }}</span>
            </div>
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Maior qtd. de gerentes</span>
              <span class="detail-value">{{ planAssignment.usage.max_managers_per_business_used }}</span>
            </div>
            <div class="detail-item detail-item--boxed">
              <span class="detail-label">Maior qtd. de profissionais</span>
              <span class="detail-value">{{ planAssignment.usage.max_professionals_per_establishment_used }}</span>
            </div>
          </div>

          <div v-if="canEditPlan" class="plan-edit-row q-mt-md">
            <q-select
              v-model="selectedPlanId"
              outlined
              dense
              emit-value
              map-options
              :options="planOptions"
              label="Trocar plano"
              class="plan-select"
            />
            <q-btn
              color="primary"
              label="Salvar plano"
              no-caps
              :loading="savingPlan"
              :disable="!selectedPlanId || selectedPlanId === planAssignment.subscription?.plan_id"
              @click="savePlan"
            />
          </div>
        </div>
      </div>
    </template>

    <q-dialog v-model="showEditDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-head">
          <h3>Editar usuario</h3>
          <q-btn flat round dense icon="close" @click="showEditDialog = false" />
        </q-card-section>
        <q-card-section>
          <q-input v-model="editForm.name" outlined dense label="Nome" :disable="!editableFields.name" />
          <q-input v-model="editForm.email" outlined dense label="Email" class="q-mt-md" :disable="!editableFields.email" />
          <q-input v-model="editForm.phone" outlined dense label="Telefone" class="q-mt-md" :disable="!editableFields.phone" />
          <q-input
            v-model="editForm.address_line_1"
            outlined
            dense
            label="Endereco linha 1"
            class="q-mt-md"
            :disable="!editableFields.address_line_1"
          />
          <q-input
            v-model="editForm.address_line_2"
            outlined
            dense
            label="Endereco linha 2"
            class="q-mt-md"
            :disable="!editableFields.address_line_2"
          />
          <q-select
            v-model="editForm.role"
            outlined
            dense
            emit-value
            map-options
            class="q-mt-md"
            label="Papel"
            :options="roleOptions"
            :disable="!editableFields.role || roleOptions.length === 0"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" no-caps @click="showEditDialog = false" />
          <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveUser" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showBlockDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-head">
          <h3>Bloquear acesso</h3>
          <q-btn flat round dense icon="close" @click="closeBlockDialog" />
        </q-card-section>
        <q-card-section>
          <p class="section-copy">O usuario perde acesso imediato ao sistema e os refresh tokens sao revogados.</p>
          <q-input
            v-model="blockReason"
            outlined
            dense
            type="textarea"
            autogrow
            maxlength="500"
            label="Motivo do bloqueio"
            placeholder="Ex: ambiente de testes fechado, auditoria pendente, acesso indevido."
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" no-caps @click="closeBlockDialog" />
          <q-btn color="negative" label="Confirmar bloqueio" no-caps :loading="blockingUser" @click="blockUserAccess" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showRevokeSessionsDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-head">
          <h3>Encerrar sessoes</h3>
          <q-btn flat round dense icon="close" @click="showRevokeSessionsDialog = false" />
        </q-card-section>
        <q-card-section>
          <p>Deseja revogar todas as sessoes ativas deste usuario?</p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" no-caps @click="showRevokeSessionsDialog = false" />
          <q-btn color="warning" label="Encerrar" no-caps :loading="revokingSessions" @click="revokeSessions" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showDeleteDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-head">
          <h3>Excluir cadastro</h3>
          <q-btn flat round dense icon="close" @click="showDeleteDialog = false" />
        </q-card-section>
        <q-card-section>
          <p>Deseja excluir o cadastro de <strong>{{ user?.name }}</strong>?</p>
          <p class="section-copy">
            A exclusao remove o usuario e revoga as sessoes ativas. Titulares de negocio ou usuarios com plano ativo
            continuam protegidos.
          </p>
          <div v-if="deleteBlockers.length" class="access-blockers q-mt-md">
            <span class="detail-label">Bloqueios atuais</span>
            <p v-for="blocker in deleteBlockers" :key="`dialog-${blocker}`">{{ blocker }}</p>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" no-caps @click="showDeleteDialog = false" />
          <q-btn color="negative" label="Excluir" no-caps :disable="!canDeleteUser" :loading="deletingUser" @click="deleteUserRecord" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { computed, defineComponent, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'boot/axios'

export default defineComponent({
  name: 'UserDetailPage',

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const user = ref(null)
    const memberships = ref({ businesses: [], establishments: [] })
    const planAssignment = ref({ subscription: null, plan: null, usage: null })
    const plans = ref([])

    const loading = ref(true)
    const saving = ref(false)
    const savingPlan = ref(false)
    const blockingUser = ref(false)
    const unblockingUser = ref(false)
    const revokingSessions = ref(false)
    const deletingUser = ref(false)

    const showEditDialog = ref(false)
    const showBlockDialog = ref(false)
    const showRevokeSessionsDialog = ref(false)
    const showDeleteDialog = ref(false)

    const selectedPlanId = ref(null)
    const blockReason = ref('')
    const editForm = ref({
      name: '',
      email: '',
      phone: '',
      address_line_1: '',
      address_line_2: '',
      role: null
    })

    const normalizeBoolean = (value) => value === true || value === 1 || value === '1'

    const editableFields = computed(() => user.value?.editable_fields || {})
    const accessState = computed(() => user.value?.access_state || {})
    const environmentState = computed(() => accessState.value.environment || { label: 'Sem regra', reason: null })
    const systemState = computed(() => accessState.value.system || { label: 'Nao informado', blocked_reason: null, blocked_at: null })
    const managementSummary = computed(() => user.value?.management_summary || {})
    const deleteBlockers = computed(() => managementSummary.value.delete_blockers || [])

    const resolvedUserRole = computed(() => {
      if (user.value?.role === 'admin' || user.value?.effective_role === 'admin') return 'admin'
      return user.value?.effective_role || user.value?.role || null
    })

    const roleOptions = computed(() => user.value?.role_options || [])
    const planOptions = computed(() => (
      (plans.value || [])
        .filter((plan) => normalizeBoolean(plan.is_active))
        .map((plan) => ({
          label: plan.name,
          value: plan.id
        }))
    ))

    const canEditProfile = computed(() => Boolean(
      editableFields.value.name ||
      editableFields.value.email ||
      editableFields.value.phone ||
      editableFields.value.address_line_1 ||
      editableFields.value.address_line_2 ||
      editableFields.value.role
    ))
    const canEditPlan = computed(() => Boolean(editableFields.value.plan))
    const canBlockAccess = computed(() => Boolean(editableFields.value.block_access))
    const canUnblockAccess = computed(() => Boolean(editableFields.value.unblock_access))
    const canRevokeSessions = computed(() => Boolean(editableFields.value.revoke_sessions))
    const canDeleteUser = computed(() => Boolean(managementSummary.value.can_delete))
    const canManageUser = computed(() => Boolean(
      canEditProfile.value ||
      canEditPlan.value ||
      canBlockAccess.value ||
      canUnblockAccess.value ||
      canRevokeSessions.value ||
      editableFields.value.delete_user
    ))
    const showDeleteAction = computed(() => canManageUser.value && (canDeleteUser.value || deleteBlockers.value.length > 0))
    const blockedByLabel = computed(() => accessState.value.blocked_by_name || accessState.value.blocked_by_email || 'Nao informado')
    const finalAccessMessage = computed(() => {
      if (accessState.value.can_authenticate) return null
      return environmentState.value.reason || systemState.value.reason || systemState.value.blocked_reason || null
    })

    const notifyError = (error, fallback) => {
      $q.notify({ type: 'negative', message: error.response?.data?.error?.message || fallback })
    }

    const notifySuccess = (message) => {
      $q.notify({ type: 'positive', message })
    }

    const fetchUser = async () => {
      loading.value = true

      try {
        const response = await api.get(`/admin/users/${route.params.id}`)
        if (!response.data?.success) throw new Error('invalid_response')

        const data = response.data.data || {}
        user.value = data.user || null
        memberships.value = data.memberships || { businesses: [], establishments: [] }
        planAssignment.value = data.plan_assignment || { subscription: null, plan: null, usage: null }
        selectedPlanId.value = planAssignment.value.subscription?.plan_id || null

        if (!user.value) {
          $q.notify({ type: 'warning', message: 'Usuario nao encontrado.' })
          goBack()
          return
        }

        if (canEditPlan.value) {
          await fetchPlans()
        }
      } catch (error) {
        notifyError(error, 'Erro ao carregar usuario.')
        goBack()
      } finally {
        loading.value = false
      }
    }

    const fetchPlans = async () => {
      try {
        const response = await api.get('/admin/plans')
        plans.value = response.data?.data?.plans || []
      } catch (error) {
        console.error(error)
      }
    }

    const openEdit = () => {
      if (!user.value) return

      editForm.value = {
        name: user.value.name || '',
        email: user.value.email || '',
        phone: user.value.phone || '',
        address_line_1: user.value.address_line_1 || '',
        address_line_2: user.value.address_line_2 || '',
        role: resolvedUserRole.value
      }
      showEditDialog.value = true
    }

    const saveUser = async () => {
      saving.value = true

      try {
        const payload = {
          name: editForm.value.name,
          email: editForm.value.email,
          phone: editForm.value.phone,
          address_line_1: editForm.value.address_line_1,
          address_line_2: editForm.value.address_line_2,
          role: editForm.value.role
        }

        await api.put(`/admin/users/${route.params.id}`, payload)
        notifySuccess('Usuario atualizado com sucesso.')
        showEditDialog.value = false
        await fetchUser()
      } catch (error) {
        notifyError(error, 'Erro ao salvar usuario.')
      } finally {
        saving.value = false
      }
    }

    const savePlan = async () => {
      if (!selectedPlanId.value) return

      savingPlan.value = true

      try {
        await api.put(`/admin/users/${route.params.id}/plan`, { plan_id: selectedPlanId.value })
        notifySuccess('Plano atualizado com sucesso.')
        await fetchUser()
      } catch (error) {
        notifyError(error, 'Erro ao atualizar plano.')
      } finally {
        savingPlan.value = false
      }
    }

    const closeBlockDialog = () => {
      blockReason.value = ''
      showBlockDialog.value = false
    }

    const blockUserAccess = async () => {
      blockingUser.value = true

      try {
        const response = await api.post(`/admin/users/${route.params.id}/block`, {
          reason: blockReason.value.trim() || null
        })
        notifySuccess(response.data?.data?.message || 'Acesso bloqueado com sucesso.')
        closeBlockDialog()
        await fetchUser()
      } catch (error) {
        notifyError(error, 'Erro ao bloquear acesso do usuario.')
      } finally {
        blockingUser.value = false
      }
    }

    const unblockUserAccess = async () => {
      unblockingUser.value = true

      try {
        const response = await api.post(`/admin/users/${route.params.id}/unblock`)
        notifySuccess(response.data?.data?.message || 'Acesso liberado com sucesso.')
        await fetchUser()
      } catch (error) {
        notifyError(error, 'Erro ao liberar acesso do usuario.')
      } finally {
        unblockingUser.value = false
      }
    }

    const revokeSessions = async () => {
      revokingSessions.value = true

      try {
        const response = await api.post(`/admin/users/${route.params.id}/revoke-sessions`)
        notifySuccess(response.data?.data?.message || 'Sessoes encerradas com sucesso.')
        showRevokeSessionsDialog.value = false
        await fetchUser()
      } catch (error) {
        notifyError(error, 'Erro ao encerrar sessoes do usuario.')
      } finally {
        revokingSessions.value = false
      }
    }

    const deleteUserRecord = async () => {
      if (!canDeleteUser.value) {
        showDeleteDialog.value = false
        return
      }

      deletingUser.value = true

      try {
        const response = await api.delete(`/admin/users/${route.params.id}`)
        notifySuccess(response.data?.data?.message || 'Cadastro excluido com sucesso.')
        showDeleteDialog.value = false
        router.push('/app/admin')
      } catch (error) {
        const blockers = error.response?.data?.error?.details?.blockers
        if (Array.isArray(blockers) && blockers.length > 0 && user.value) {
          user.value = {
            ...user.value,
            management_summary: {
              ...(user.value.management_summary || {}),
              can_delete: false,
              delete_blockers: blockers
            },
            editable_fields: {
              ...(user.value.editable_fields || {}),
              delete_user: false
            }
          }
        }
        notifyError(error, 'Erro ao excluir cadastro do usuario.')
      } finally {
        deletingUser.value = false
      }
    }

    const goBack = () => router.push('/app/admin')

    const getInitials = (name) => (
      name
        ? name
          .split(' ')
          .filter(Boolean)
          .slice(0, 2)
          .map((part) => part[0])
          .join('')
          .toUpperCase()
        : '?'
    )
    const getRoleLabel = (role) => ({ admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', client: 'Cliente' }[role] || role || '-')
    const getContextRoleLabel = (role) => ({ owner: 'Dono', manager: 'Gerente', professional: 'Profissional' }[role] || role || '-')
    const getRoleColor = (role) => ({ admin: 'deep-orange', manager: 'primary', professional: 'teal', client: 'grey-7' }[role] || 'grey-7')
    const formatDateTime = (value) => (value ? new Date(value).toLocaleString('pt-BR') : '-')
    const formatLimit = (value) => (value === null || value === undefined || value === '' ? 'Ilimitado' : value)
    const formatSubscriptionStatus = (value) => ({ active: 'Ativo', past_due: 'Em atraso', cancelled: 'Cancelado' }[value] || 'Sem assinatura')

    onMounted(fetchUser)

    return {
      accessState,
      blockReason,
      blockUserAccess,
      blockedByLabel,
      blockingUser,
      canBlockAccess,
      canDeleteUser,
      canEditPlan,
      canEditProfile,
      canRevokeSessions,
      canUnblockAccess,
      closeBlockDialog,
      deleteBlockers,
      deleteUserRecord,
      deletingUser,
      editableFields,
      editForm,
      environmentState,
      finalAccessMessage,
      formatDateTime,
      formatLimit,
      formatSubscriptionStatus,
      getContextRoleLabel,
      getInitials,
      getRoleColor,
      getRoleLabel,
      goBack,
      loading,
      memberships,
      normalizeBoolean,
      openEdit,
      planAssignment,
      planOptions,
      resolvedUserRole,
      revokeSessions,
      revokingSessions,
      roleOptions,
      savePlan,
      saveUser,
      saving,
      savingPlan,
      selectedPlanId,
      showBlockDialog,
      showDeleteAction,
      showDeleteDialog,
      showEditDialog,
      showRevokeSessionsDialog,
      systemState,
      unblockUserAccess,
      unblockingUser,
      user
    }
  }
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

.profile-hero {
  padding: 1.25rem;
}

.profile-hero__header,
.profile-hero__identity,
.profile-badges,
.plan-edit-row,
.plan-card__header,
.access-actions {
  display: flex;
  gap: 1rem;
}

.profile-hero__header,
.plan-card__header {
  justify-content: space-between;
  align-items: flex-start;
}

.profile-hero__identity {
  align-items: center;
  min-width: 0;
}

.profile-avatar {
  width: 4rem;
  height: 4rem;
  border-radius: 1rem;
  overflow: hidden;
  background: var(--qm-brand);
  color: var(--qm-brand-contrast);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  font-weight: 700;
  flex-shrink: 0;
}

.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-hero__copy {
  min-width: 0;
}

.profile-name {
  margin: 0 0 0.35rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--qm-text-primary);
}

.profile-badges,
.access-actions {
  flex-wrap: wrap;
  align-items: center;
}

.profile-hint,
.section-copy,
.membership-column__header p,
.plan-card__eyebrow,
.detail-help {
  color: var(--qm-text-muted);
}

.profile-hint {
  margin: 0.75rem 0 0;
  font-size: 0.8125rem;
}

.detail-grid--wide {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.detail-item--boxed {
  padding: 0.85rem;
  border: 1px solid var(--qm-border);
  border-radius: 14px;
  background: var(--qm-bg-secondary);
}

.detail-help {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.78rem;
}

.access-blockers {
  padding: 0.9rem 1rem;
  border: 1px solid color-mix(in srgb, var(--q-negative) 22%, var(--qm-border));
  border-radius: 14px;
  background: color-mix(in srgb, var(--q-negative) 7%, var(--qm-bg-secondary));
}

.access-blockers p {
  margin: 0.35rem 0 0;
  color: var(--qm-text-secondary);
}

.memberships-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.membership-column {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.membership-column__header h3 {
  margin: 0 0 0.2rem;
  font-size: 1rem;
  color: var(--qm-text-primary);
}

.membership-column__header p {
  margin: 0;
  font-size: 0.8125rem;
}

.membership-empty {
  border: 1px dashed var(--qm-border);
  border-radius: 0.875rem;
  background: color-mix(in srgb, var(--qm-bg-secondary) 80%, transparent);
}

.plan-card {
  border: 1px solid var(--qm-border);
  border-radius: 18px;
  padding: 1rem;
  background: linear-gradient(180deg, var(--qm-bg-primary), var(--qm-bg-secondary));
}

.plan-card__eyebrow {
  display: block;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.plan-card__title {
  margin: 0.25rem 0 0;
  font-size: 1.15rem;
  color: var(--qm-text-primary);
}

.section-title--compact {
  margin-bottom: 0.2rem;
}

.section-copy {
  margin: 0;
  font-size: 0.875rem;
}

.plan-select {
  flex: 1;
  min-width: 16rem;
}

.dialog-card {
  width: min(100%, 38rem);
  border-radius: 18px;
}

@media (max-width: 768px) {
  .profile-hero__header,
  .profile-hero__identity,
  .plan-edit-row,
  .plan-card__header {
    flex-direction: column;
  }

  .memberships-grid {
    grid-template-columns: 1fr;
  }

  .plan-select {
    min-width: 100%;
  }
}
</style>
