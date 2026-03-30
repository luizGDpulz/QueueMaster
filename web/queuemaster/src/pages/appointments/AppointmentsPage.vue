<template>
  <q-page class="appointments-page">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Agendamentos</h1>
      </div>
      <div class="header-right" v-if="showCreateButton">
        <q-btn
          color="primary"
          :icon="mainTab === 'requests' ? 'mail' : 'add'"
          :label="mainTab === 'requests' ? 'Nova Solicitação' : 'Novo Agendamento'"
          no-caps
          @click="openCreateDialog"
        />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle">Separe agendamentos confirmados das solicitações pendentes</p>
      </div>
    </div>

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
        <q-tab name="appointments" icon="event" label="Agendamentos" no-caps />
        <q-tab name="requests" icon="mail" :label="requestTabLabel" no-caps />
      </q-tabs>

      <q-separator style="margin-top: 10px;" />

      <q-tab-panels v-model="mainTab" animated class="tab-panels">
        <q-tab-panel name="appointments" class="tab-panel-padded">
          <div class="filters-card">
            <div class="filters-row">
              <q-select
                v-model="appointmentFilters.bucket"
                outlined
                dense
                emit-value
                map-options
                label="Visão"
                :options="appointmentBucketOptions"
                class="filter-select"
              />
              <q-select
                v-model="appointmentFilters.status"
                outlined
                dense
                emit-value
                map-options
                clearable
                label="Status exato"
                :options="appointmentStatusOptions"
                class="filter-select"
              />
              <q-input
                v-model="appointmentFilters.date"
                outlined
                dense
                type="date"
                label="Data"
                class="filter-date"
              />
              <q-select
                v-model="appointmentFilters.establishment_id"
                outlined
                dense
                emit-value
                map-options
                clearable
                label="Estabelecimento"
                :options="staffEstablishmentOptions"
                class="filter-select"
                :disable="isClient"
              />
              <q-input
                v-model="appointmentSearch"
                outlined
                dense
                label="Buscar"
                placeholder="Cliente, profissional, serviço"
                class="search-input"
              >
                <template #prepend>
                  <q-icon name="search" />
                </template>
              </q-input>
              <q-btn flat color="primary" label="Filtrar" no-caps @click="fetchAppointments({ page: 1 })" />
              <q-btn flat label="Limpar" no-caps @click="clearAppointmentFilters" />
            </div>
          </div>

          <div v-if="loadingAppointments" class="loading-state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando agendamentos...</p>
          </div>

          <div v-else-if="filteredAppointments.length === 0" class="empty-state">
            <q-icon name="event_busy" size="56px" />
            <h3>Nenhum agendamento encontrado</h3>
            <p>Ajuste os filtros ou crie um novo agendamento confirmado.</p>
          </div>

          <div v-else class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th class="th-client">Cliente</th>
                  <th class="th-professional">Profissional</th>
                  <th class="th-service">Serviço</th>
                  <th class="th-datetime">Data/Hora</th>
                  <th class="th-status">Status</th>
                  <th class="th-actions"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="appointment in filteredAppointments"
                  :key="appointment.id"
                  class="clickable-row"
                  @click="router.push(`/app/appointments/${appointment.id}`)"
                >
                  <td>
                    <div class="primary-cell">
                      <span class="primary-text">{{ appointment.user_name || `Cliente #${appointment.user_id}` }}</span>
                      <span class="secondary-text">{{ appointment.establishment_name || '-' }}</span>
                    </div>
                  </td>
                  <td>
                    <div class="primary-cell">
                      <span class="primary-text">{{ appointment.professional_name || `Profissional #${appointment.professional_id}` }}</span>
                      <span class="secondary-text">{{ appointment.specialization || '-' }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="primary-text">{{ appointment.service_name || `Serviço #${appointment.service_id}` }}</span>
                  </td>
                  <td>
                    <div class="primary-cell">
                      <span class="primary-text">{{ formatDate(appointment.start_at) }}</span>
                      <span class="secondary-text">{{ formatTime(appointment.start_at) }} - {{ formatTime(appointment.end_at) }}</span>
                    </div>
                  </td>
                  <td>
                    <q-badge :color="getAppointmentStatusColor(appointment.status)" class="status-badge">
                      {{ getAppointmentStatusLabel(appointment.status) }}
                    </q-badge>
                  </td>
                  <td>
                    <div class="row-actions">
                      <q-btn
                        v-if="canEditAppointment(appointment)"
                        flat
                        round
                        dense
                        icon="edit"
                        size="sm"
                        @click.stop="editAppointment(appointment)"
                      />
                      <q-btn-dropdown
                        v-if="canChangeAppointmentStatus(appointment)"
                        flat
                        round
                        dense
                        icon="more_vert"
                        size="sm"
                        @click.stop
                      >
                        <q-list dense>
                          <q-item clickable v-close-popup @click.stop="updateAppointmentStatus(appointment, 'checked_in')" v-if="appointment.status === 'booked'">
                            <q-item-section>Check-in</q-item-section>
                          </q-item>
                          <q-item clickable v-close-popup @click.stop="updateAppointmentStatus(appointment, 'in_progress')" v-if="appointment.status === 'checked_in'">
                            <q-item-section>Iniciar</q-item-section>
                          </q-item>
                          <q-item clickable v-close-popup @click.stop="updateAppointmentStatus(appointment, 'completed')" v-if="appointment.status === 'in_progress' || appointment.status === 'checked_in'">
                            <q-item-section>Concluir</q-item-section>
                          </q-item>
                          <q-item clickable v-close-popup @click.stop="updateAppointmentStatus(appointment, 'no_show')" v-if="appointment.status === 'booked' || appointment.status === 'checked_in'">
                            <q-item-section>Não compareceu</q-item-section>
                          </q-item>
                          <q-item clickable v-close-popup @click.stop="updateAppointmentStatus(appointment, 'cancelled')" v-if="['booked', 'checked_in'].includes(appointment.status)">
                            <q-item-section class="text-negative">Cancelar</q-item-section>
                          </q-item>
                        </q-list>
                      </q-btn-dropdown>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="appointmentsPagination.total_pages > 1" class="table-pagination">
            <q-pagination
              v-model="appointmentsPagination.page"
              :max="appointmentsPagination.total_pages"
              direction-links
              boundary-links
              @update:model-value="fetchAppointments"
            />
          </div>
        </q-tab-panel>

        <q-tab-panel name="requests" class="tab-panel-padded">
          <div v-if="pendingRequestCount > 0" class="pending-banner">
            <q-icon name="priority_high" size="18px" />
            <span>{{ pendingRequestCount }} solicitação(ões) pendente(s) aguardando ação.</span>
          </div>

          <div class="filters-card">
            <div class="filters-row">
              <q-select
                v-model="requestFilters.status"
                outlined
                dense
                emit-value
                map-options
                label="Status"
                :options="requestStatusOptions"
                class="filter-select"
              />
              <q-input
                v-model="requestFilters.date"
                outlined
                dense
                type="date"
                label="Data"
                class="filter-date"
              />
              <q-select
                v-model="requestFilters.establishment_id"
                outlined
                dense
                emit-value
                map-options
                clearable
                label="Estabelecimento"
                :options="requestEstablishmentOptions"
                class="filter-select"
                :disable="isClient"
              />
              <q-input
                v-model="requestSearch"
                outlined
                dense
                label="Buscar"
                placeholder="Cliente, solicitante, serviço"
                class="search-input"
              >
                <template #prepend>
                  <q-icon name="search" />
                </template>
              </q-input>
              <q-btn flat color="primary" label="Filtrar" no-caps @click="fetchRequests({ page: 1 })" />
              <q-btn flat label="Limpar" no-caps @click="clearRequestFilters" />
            </div>
          </div>

          <div v-if="loadingRequests" class="loading-state">
            <q-spinner-dots color="primary" size="40px" />
            <p>Carregando solicitações...</p>
          </div>

          <div v-else-if="filteredRequests.length === 0" class="empty-state">
            <q-icon name="mail_outline" size="56px" />
            <h3>Nenhuma solicitação encontrada</h3>
            <p>As solicitações pendentes e resolvidas aparecem aqui.</p>
          </div>

          <div v-else class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th class="th-client">Parte</th>
                  <th class="th-service">Serviço</th>
                  <th class="th-establishment">Estabelecimento</th>
                  <th class="th-datetime">Data/Hora</th>
                  <th class="th-flow">Fluxo</th>
                  <th class="th-status">Status</th>
                  <th class="th-actions"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="request in filteredRequests" :key="request.id">
                  <td>
                    <div class="primary-cell">
                      <span class="primary-text">{{ getRequestPrimaryParty(request) }}</span>
                      <span class="secondary-text">{{ getRequestSecondaryParty(request) }}</span>
                    </div>
                  </td>
                  <td>
                    <div class="primary-cell">
                      <span class="primary-text">{{ request.service_name || `Serviço #${request.service_id}` }}</span>
                      <span class="secondary-text">{{ request.professional_name || 'Profissional a definir' }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="primary-text">{{ request.establishment_name || '-' }}</span>
                  </td>
                  <td>
                    <div class="primary-cell">
                      <span class="primary-text">{{ formatDate(request.proposed_start_at) }}</span>
                      <span class="secondary-text">{{ formatTime(request.proposed_start_at) }}</span>
                    </div>
                  </td>
                  <td>
                    <q-badge :color="getRequestDirectionColor(request.direction)" class="status-badge">
                      {{ getRequestDirectionLabel(request.direction) }}
                    </q-badge>
                  </td>
                  <td>
                    <q-badge :color="getRequestStatusColor(request.status)" class="status-badge">
                      {{ getRequestStatusLabel(request.status) }}
                    </q-badge>
                  </td>
                  <td>
                    <div class="row-actions row-actions--requests">
                      <q-btn
                        v-if="canRespondRequest(request)"
                        flat
                        round
                        dense
                        icon="check_circle"
                        color="positive"
                        size="sm"
                        :loading="requestActionId === request.id && requestAction === 'accept'"
                        @click="prepareAcceptRequest(request)"
                      />
                      <q-btn
                        v-if="canRespondRequest(request)"
                        flat
                        round
                        dense
                        icon="cancel"
                        color="negative"
                        size="sm"
                        :loading="requestActionId === request.id && requestAction === 'reject'"
                        @click="rejectRequest(request)"
                      />
                      <q-btn
                        v-if="canCancelRequest(request)"
                        flat
                        round
                        dense
                        icon="close"
                        color="grey-7"
                        size="sm"
                        :loading="requestActionId === request.id && requestAction === 'cancel'"
                        @click="cancelRequest(request)"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="requestsPagination.total_pages > 1" class="table-pagination">
            <q-pagination
              v-model="requestsPagination.page"
              :max="requestsPagination.total_pages"
              direction-links
              boundary-links
              @update:model-value="fetchRequests"
            />
          </div>
        </q-tab-panel>
      </q-tab-panels>
    </div>

    <q-dialog v-model="showAppointmentDialog" persistent>
      <q-card class="dialog-card dialog-large">
        <q-card-section class="dialog-header">
          <h3>{{ isEditingAppointment ? 'Editar Agendamento' : 'Novo Agendamento' }}</h3>
          <q-btn flat round dense icon="close" @click="closeAppointmentDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <div class="form-grid">
            <q-select
              v-if="!isEditingAppointment"
              v-model="appointmentForm.client_user_id"
              label="Cliente *"
              outlined
              dense
              emit-value
              map-options
              use-input
              fill-input
              hide-selected
              input-debounce="0"
              :options="clientOptions"
              :disable="isClient"
            />
            <q-select
              v-model="appointmentForm.establishment_id"
              label="Estabelecimento *"
              outlined
              dense
              emit-value
              map-options
              :options="staffEstablishmentOptions"
              @update:model-value="onAppointmentEstablishmentChange"
            />
            <q-select
              v-model="appointmentForm.professional_id"
              label="Profissional *"
              outlined
              dense
              emit-value
              map-options
              :options="appointmentProfessionalOptions"
              :disable="!appointmentForm.establishment_id || professionalFieldDisabled"
              @update:model-value="onAppointmentSlotDepsChange"
            />
            <q-select
              v-model="appointmentForm.service_id"
              label="Serviço *"
              outlined
              dense
              emit-value
              map-options
              :options="appointmentServiceOptions"
              :disable="!appointmentForm.establishment_id"
              @update:model-value="onAppointmentSlotDepsChange"
            />
            <q-input
              v-if="!isEditingAppointment"
              v-model="appointmentSlotDate"
              label="Data *"
              outlined
              dense
              type="date"
              :disable="!appointmentForm.professional_id || !appointmentForm.service_id"
              @update:model-value="onAppointmentSlotDepsChange"
            />
            <q-input
              v-else
              v-model="appointmentForm.start_at"
              label="Data e hora *"
              outlined
              dense
              type="datetime-local"
            />
            <div v-if="!isEditingAppointment && appointmentSlotsLoading" class="slots-loading">
              <q-spinner-dots color="primary" size="24px" />
              <span>Buscando horários disponíveis...</span>
            </div>
            <div v-else-if="!isEditingAppointment && appointmentAvailableSlots.length > 0" class="slots-section">
              <label class="slots-label">Horários disponíveis</label>
              <div class="slots-grid">
                <q-btn
                  v-for="slot in appointmentAvailableSlots"
                  :key="slot.start_at"
                  :outline="appointmentForm.start_at !== slot.start_at"
                  :color="appointmentForm.start_at === slot.start_at ? 'primary' : undefined"
                  :class="{ 'slot-btn--idle': appointmentForm.start_at !== slot.start_at }"
                  :label="formatSlotTime(slot.start_at)"
                  dense
                  no-caps
                  size="sm"
                  class="slot-btn"
                  @click="appointmentForm.start_at = slot.start_at"
                />
              </div>
            </div>
            <div v-else-if="!isEditingAppointment && appointmentSlotDate && appointmentSlotsSearched" class="slots-empty">
              <q-icon name="event_busy" size="18px" />
              <span>Nenhum horário disponível para está combinação.</span>
            </div>
            <q-input
              v-model="appointmentForm.notes"
              label="Observações"
              outlined
              dense
              type="textarea"
              autogrow
              class="form-span"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeAppointmentDialog" />
          <q-btn
            color="primary"
            :label="isEditingAppointment ? 'Salvar' : 'Confirmar agendamento'"
            no-caps
            :loading="savingAppointment"
            @click="saveAppointment"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showRequestDialog" persistent>
      <q-card class="dialog-card dialog-large">
        <q-card-section class="dialog-header">
          <h3>Nova Solicitação</h3>
          <q-btn flat round dense icon="close" @click="closeRequestDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <div class="form-grid">
            <q-select
              v-if="!isClient"
              v-model="requestForm.client_user_id"
              label="Cliente *"
              outlined
              dense
              emit-value
              map-options
              use-input
              fill-input
              hide-selected
              input-debounce="0"
              :options="clientOptions"
            />
            <q-select
              v-model="requestForm.establishment_id"
              label="Estabelecimento *"
              outlined
              dense
              emit-value
              map-options
              :options="requestEstablishmentOptions"
              @update:model-value="onRequestEstablishmentChange"
            />
            <q-select
              v-if="!isClient"
              v-model="requestForm.professional_id"
              label="Profissional *"
              outlined
              dense
              emit-value
              map-options
              :options="requestProfessionalOptions"
              :disable="!requestForm.establishment_id || professionalFieldDisabled"
            />
            <q-select
              v-model="requestForm.service_id"
              label="Serviço *"
              outlined
              dense
              emit-value
              map-options
              :options="requestServiceOptions"
              :disable="!requestForm.establishment_id"
            />
            <q-input
              v-model="requestForm.start_at"
              label="Data e hora propostas *"
              outlined
              dense
              type="datetime-local"
            />
            <q-input
              v-model="requestForm.notes"
              label="Observações"
              outlined
              dense
              type="textarea"
              autogrow
              class="form-span"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeRequestDialog" />
          <q-btn color="primary" label="Enviar solicitação" no-caps :loading="savingRequest" @click="saveRequest" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showAcceptDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <h3>Aceitar Solicitação</h3>
          <q-btn flat round dense icon="close" @click="closeAcceptDialog" />
        </q-card-section>

        <q-card-section class="dialog-content">
          <div class="form-grid">
            <q-select
              v-if="acceptNeedsProfessional"
              v-model="acceptForm.professional_id"
              label="Profissional que atenderá *"
              outlined
              dense
              emit-value
              map-options
              :options="acceptProfessionalOptions"
              :disable="professionalFieldDisabled"
            />
            <q-input
              v-model="acceptForm.decision_note"
              label="Observação interna"
              outlined
              dense
              type="textarea"
              autogrow
              class="form-span"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeAcceptDialog" />
          <q-btn color="positive" label="Aceitar solicitação" no-caps :loading="savingAccept" @click="submitAcceptRequest" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { computed, defineComponent, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'boot/axios'
import { useRemoteSearch } from 'src/composables/useRemoteSearch'

export default defineComponent({
  name: 'AppointmentsPage',

  setup() {
    const $q = useQuasar()
    const route = useRoute()
    const router = useRouter()

    const mainTab = ref(route.query.tab === 'requests' ? 'requests' : 'appointments')

    const userRole = ref('client')
    const userId = ref(null)

    const appointments = ref([])
    const requests = ref([])
    const loadingAppointments = ref(false)
    const loadingRequests = ref(false)
    const savingAppointment = ref(false)
    const savingRequest = ref(false)
    const savingAccept = ref(false)
    const requestActionId = ref(null)
    const requestAction = ref('')
    const pendingRequestCount = ref(0)

    const establishments = ref([])
    const professionals = ref([])
    const services = ref([])
    const discoverServices = ref([])
    const clients = ref([])

    const appointmentsPagination = ref({
      page: 1,
      per_page: 20,
      total: 0,
      total_pages: 1,
    })

    const requestsPagination = ref({
      page: 1,
      per_page: 20,
      total: 0,
      total_pages: 1,
    })

    const appointmentFilters = ref({
      bucket: 'scheduled',
      status: null,
      date: null,
      establishment_id: null,
    })

    const requestFilters = ref({
      status: 'pending',
      date: null,
      establishment_id: null,
    })

    const appointmentSearch = ref('')
    const requestSearch = ref('')

    const showAppointmentDialog = ref(false)
    const showRequestDialog = ref(false)
    const showAcceptDialog = ref(false)
    const isEditingAppointment = ref(false)
    const selectedAppointment = ref(null)
    const selectedRequest = ref(null)

    const appointmentForm = ref({
      client_user_id: null,
      establishment_id: null,
      professional_id: null,
      service_id: null,
      start_at: '',
      notes: '',
    })

    const requestForm = ref({
      client_user_id: null,
      establishment_id: null,
      professional_id: null,
      service_id: null,
      start_at: '',
      notes: '',
    })

    const acceptForm = ref({
      professional_id: null,
      decision_note: '',
    })

    const appointmentSlotDate = ref('')
    const appointmentAvailableSlotsSearch = useRemoteSearch({
      search: ({ query, signal, contextKey }) => {
        const [professionalId, serviceId] = String(contextKey || '').split(':')

        return api.get('/appointments/available-slots', {
          params: {
            professional_id: professionalId,
            service_id: serviceId,
            date: query,
          },
          signal,
        })
      },
      mapResults: (response) => response.data?.data?.slots || [],
      debounceMs: 250,
      cacheTtlMs: 10000,
    })

    const appointmentAvailableSlots = appointmentAvailableSlotsSearch.results
    const appointmentSlotsLoading = appointmentAvailableSlotsSearch.loading
    const appointmentSlotsSearched = appointmentAvailableSlotsSearch.searched

    const isClient = computed(() => userRole.value === 'client')
    const isProfessional = computed(() => userRole.value === 'professional')
    const canManageAppointments = computed(() => ['professional', 'manager', 'admin'].includes(userRole.value))
    const showCreateButton = computed(() => {
      if (mainTab.value === 'appointments') return !isClient.value
      return true
    })

    const requestTabLabel = computed(() => (
      pendingRequestCount.value > 0 ? `Solicitações (${pendingRequestCount.value})` : 'Solicitações'
    ))

    const appointmentBucketOptions = [
      { label: 'Agendados', value: 'scheduled' },
      { label: 'Concluídos', value: 'completed' },
      { label: 'Todos', value: 'all' },
    ]

    const appointmentStatusOptions = [
      { label: 'Agendado', value: 'booked' },
      { label: 'Confirmado', value: 'confirmed' },
      { label: 'Check-in', value: 'checked_in' },
      { label: 'Em andamento', value: 'in_progress' },
      { label: 'Concluído', value: 'completed' },
      { label: 'Não compareceu', value: 'no_show' },
      { label: 'Cancelado', value: 'cancelled' },
    ]

    const requestStatusOptions = [
      { label: 'Pendentes', value: 'pending' },
      { label: 'Aceitas', value: 'accepted' },
      { label: 'Recusadas', value: 'rejected' },
      { label: 'Canceladas', value: 'cancelled' },
      { label: 'Todas', value: 'all' },
    ]

    const staffEstablishmentOptions = computed(() => establishments.value.map((item) => ({
      label: item.name,
      value: item.id,
    })))

    const requestEstablishmentOptions = computed(() => staffEstablishmentOptions.value)

    const clientOptions = computed(() => clients.value.map((item) => ({
      label: `${item.name || item.email} (${item.email})`,
      value: item.id,
    })))

    const appointmentProfessionalOptions = computed(() => getProfessionalOptions(appointmentForm.value.establishment_id))
    const requestProfessionalOptions = computed(() => getProfessionalOptions(requestForm.value.establishment_id))
    const appointmentServiceOptions = computed(() => getServiceOptions(appointmentForm.value.establishment_id, false))
    const requestServiceOptions = computed(() => getServiceOptions(requestForm.value.establishment_id, true))
    const acceptProfessionalOptions = computed(() => getProfessionalOptions(selectedRequest.value?.establishment_id))

    const professionalFieldDisabled = computed(() => isProfessional.value)
    const acceptNeedsProfessional = computed(() => Boolean(selectedRequest.value && canManageAppointments.value))

    const filteredAppointments = computed(() => {
      if (!appointmentSearch.value) return appointments.value
      const query = appointmentSearch.value.toLowerCase()
      return appointments.value.filter((item) => (
        `${item.user_name || ''} ${item.professional_name || ''} ${item.service_name || ''} ${item.establishment_name || ''}`
          .toLowerCase()
          .includes(query)
      ))
    })

    const filteredRequests = computed(() => {
      if (!requestSearch.value) return requests.value
      const query = requestSearch.value.toLowerCase()
      return requests.value.filter((item) => (
        `${item.client_name || ''} ${item.requested_by_name || ''} ${item.service_name || ''} ${item.establishment_name || ''}`
          .toLowerCase()
          .includes(query)
      ))
    })

    watch(mainTab, (value) => {
      router.replace({ query: value === 'requests' ? { ...route.query, tab: 'requests' } : {} })
      if (value === 'appointments' && appointments.value.length === 0) fetchAppointments()
      if (value === 'requests' && requests.value.length === 0) fetchRequests()
    })

    const normalizeDateTime = (value) => {
      if (!value) return ''
      if (value.includes('T')) return `${value.replace('T', ' ')}:00`
      return value.length === 16 ? `${value}:00` : value
    }

    const fetchUserInfo = async () => {
      const response = await api.get('/auth/me')
      const user = response.data?.data?.user || {}
      userRole.value = user.effective_role || user.role || 'client'
      userId.value = user.id || null
    }

    const fetchEstablishments = async () => {
      const response = isClient.value
        ? await api.get('/establishments/search', { params: { limit: 50 } })
        : await api.get('/establishments')

      const list = response.data?.data?.establishments || response.data?.data || []
      establishments.value = Array.isArray(list) ? list : []
    }

    const fetchProfessionals = async () => {
      if (isClient.value) {
        professionals.value = []
        return
      }

      const response = await api.get('/professionals')
      professionals.value = response.data?.data?.professionals || response.data?.data || []
    }

    const fetchServices = async () => {
      if (isClient.value) {
        services.value = []
        return
      }

      const response = await api.get('/services')
      services.value = response.data?.data?.services || response.data?.data || []
    }

    const fetchClients = async () => {
      if (isClient.value) {
        clients.value = []
        return
      }

      const response = await api.get('/users', {
        params: {
          role: 'client',
          per_page: 100,
        },
      })
      clients.value = response.data?.data?.users || []
    }

    const fetchDiscoverServices = async (establishmentId) => {
      if (!establishmentId || !isClient.value) {
        discoverServices.value = []
        return
      }

      try {
        const response = await api.get(`/establishments/${establishmentId}/discover`)
        discoverServices.value = response.data?.data?.services || []
      } catch (err) {
        console.error('Erro ao carregar estabelecimento para descoberta:', err)
        discoverServices.value = []
      }
    }

    const fetchAppointments = async (options = {}) => {
      loadingAppointments.value = true
      try {
        const page = typeof options === 'number' ? options : (options.page || appointmentsPagination.value.page)
        const params = {
          page,
          per_page: appointmentsPagination.value.per_page,
        }

        if (appointmentFilters.value.bucket && appointmentFilters.value.bucket !== 'all') params.bucket = appointmentFilters.value.bucket
        if (appointmentFilters.value.status) params.status = appointmentFilters.value.status
        if (appointmentFilters.value.date) params.date = appointmentFilters.value.date
        if (appointmentFilters.value.establishment_id) params.establishment_id = appointmentFilters.value.establishment_id

        const response = await api.get('/appointments', { params })
        appointments.value = response.data?.data || []
        const pagination = response.data?.meta?.pagination
        if (pagination) {
          appointmentsPagination.value = {
            ...appointmentsPagination.value,
            ...pagination,
          }
        }
      } catch (err) {
        console.error('Erro ao buscar agendamentos:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar agendamentos' })
      } finally {
        loadingAppointments.value = false
      }
    }

    const fetchRequests = async (options = {}) => {
      loadingRequests.value = true
      try {
        const page = typeof options === 'number' ? options : (options.page || requestsPagination.value.page)
        const params = {
          page,
          per_page: requestsPagination.value.per_page,
        }

        if (requestFilters.value.status && requestFilters.value.status !== 'all') params.status = requestFilters.value.status
        if (requestFilters.value.date) params.date = requestFilters.value.date
        if (requestFilters.value.establishment_id) params.establishment_id = requestFilters.value.establishment_id

        const response = await api.get('/appointments/requests', { params })
        requests.value = response.data?.data || []
        const pagination = response.data?.meta?.pagination
        const summary = response.data?.meta?.summary
        if (pagination) {
          requestsPagination.value = {
            ...requestsPagination.value,
            ...pagination,
          }
        }
        pendingRequestCount.value = summary?.pending_count ?? 0
      } catch (err) {
        console.error('Erro ao buscar solicitações:', err)
        $q.notify({ type: 'negative', message: 'Erro ao carregar solicitações' })
      } finally {
        loadingRequests.value = false
      }
    }

    const clearAppointmentFilters = () => {
      appointmentFilters.value = {
        bucket: 'scheduled',
        status: null,
        date: null,
        establishment_id: null,
      }
      appointmentSearch.value = ''
      appointmentsPagination.value.page = 1
      fetchAppointments({ page: 1 })
    }

    const clearRequestFilters = () => {
      requestFilters.value = {
        status: 'pending',
        date: null,
        establishment_id: null,
      }
      requestSearch.value = ''
      requestsPagination.value.page = 1
      fetchRequests({ page: 1 })
    }

    const resetAppointmentForm = () => {
      appointmentForm.value = {
        client_user_id: null,
        establishment_id: null,
        professional_id: null,
        service_id: null,
        start_at: '',
        notes: '',
      }
      appointmentSlotDate.value = ''
      appointmentAvailableSlotsSearch.clear()
      selectedAppointment.value = null
      isEditingAppointment.value = false
    }

    const resetRequestForm = () => {
      requestForm.value = {
        client_user_id: null,
        establishment_id: null,
        professional_id: null,
        service_id: null,
        start_at: '',
        notes: '',
      }
      discoverServices.value = []
    }

    const openCreateDialog = () => {
      if (mainTab.value === 'requests') {
        resetRequestForm()
        showRequestDialog.value = true
        return
      }

      resetAppointmentForm()
      showAppointmentDialog.value = true
    }

    const closeAppointmentDialog = () => {
      showAppointmentDialog.value = false
      resetAppointmentForm()
    }

    const closeRequestDialog = () => {
      showRequestDialog.value = false
      resetRequestForm()
    }

    const closeAcceptDialog = () => {
      showAcceptDialog.value = false
      selectedRequest.value = null
      acceptForm.value = {
        professional_id: null,
        decision_note: '',
      }
    }

    const getOwnProfessionalId = (establishmentId) => {
      const ownRecord = professionals.value.find((item) => (
        Number(item.establishment_id) === Number(establishmentId)
        && Number(item.user_id) === Number(userId.value)
      ))

      return ownRecord?.id || null
    }

    const getProfessionalOptions = (establishmentId) => {
      return professionals.value
        .filter((item) => Number(item.establishment_id) === Number(establishmentId))
        .filter((item) => !isProfessional.value || Number(item.user_id) === Number(userId.value))
        .map((item) => ({
          label: item.name,
          value: item.id,
        }))
    }

    const getServiceOptions = (establishmentId, forRequest) => {
      if (!establishmentId) return []
      if (isClient.value && forRequest) {
        return discoverServices.value.map((item) => ({
          label: item.name,
          value: item.id,
        }))
      }

      return services.value
        .filter((item) => Number(item.establishment_id) === Number(establishmentId))
        .map((item) => ({
          label: item.name,
          value: item.id,
        }))
    }

    const onAppointmentEstablishmentChange = (establishmentId) => {
      appointmentForm.value.professional_id = isProfessional.value ? getOwnProfessionalId(establishmentId) : null
      appointmentForm.value.service_id = null
      appointmentForm.value.start_at = ''
      appointmentSlotDate.value = ''
      appointmentAvailableSlotsSearch.clear()
    }

    const onRequestEstablishmentChange = async (establishmentId) => {
      requestForm.value.professional_id = isProfessional.value ? getOwnProfessionalId(establishmentId) : null
      requestForm.value.service_id = null
      if (isClient.value) {
        await fetchDiscoverServices(establishmentId)
      }
    }

    const onAppointmentSlotDepsChange = () => {
      appointmentForm.value.start_at = ''

      if (!appointmentForm.value.professional_id || !appointmentForm.value.service_id || !appointmentSlotDate.value) {
        appointmentAvailableSlotsSearch.clear()
        return
      }

      appointmentAvailableSlotsSearch.schedule(appointmentSlotDate.value, {
        contextKey: `${appointmentForm.value.professional_id}:${appointmentForm.value.service_id}`,
        onError: (error) => {
          console.error('Erro ao buscar horários:', error)
        },
      })
    }

    const editAppointment = (appointment) => {
      isEditingAppointment.value = true
      selectedAppointment.value = appointment
      appointmentForm.value = {
        client_user_id: appointment.user_id,
        establishment_id: appointment.establishment_id,
        professional_id: appointment.professional_id,
        service_id: appointment.service_id,
        start_at: appointment.start_at?.replace(' ', 'T').slice(0, 16) || '',
        notes: appointment.notes || '',
      }
      appointmentAvailableSlotsSearch.clear()
      showAppointmentDialog.value = true
    }

    const saveAppointment = async () => {
      if (!appointmentForm.value.establishment_id || !appointmentForm.value.professional_id || !appointmentForm.value.service_id || !appointmentForm.value.start_at) {
        $q.notify({ type: 'warning', message: 'Preencha os campos obrigatórios do agendamento' })
        return
      }

      if (!isEditingAppointment.value && !appointmentForm.value.client_user_id) {
        $q.notify({ type: 'warning', message: 'Selecione o cliente' })
        return
      }

      savingAppointment.value = true
      try {
        const payload = {
          establishment_id: appointmentForm.value.establishment_id,
          professional_id: appointmentForm.value.professional_id,
          service_id: appointmentForm.value.service_id,
          start_at: normalizeDateTime(appointmentForm.value.start_at),
          notes: appointmentForm.value.notes?.trim() || undefined,
        }

        if (!isEditingAppointment.value) {
          payload.client_user_id = appointmentForm.value.client_user_id
          await api.post('/appointments', payload)
        } else {
          await api.put(`/appointments/${selectedAppointment.value.id}`, payload)
        }

        $q.notify({ type: 'positive', message: isEditingAppointment.value ? 'Agendamento atualizado com sucesso' : 'Agendamento confirmado com sucesso' })
        closeAppointmentDialog()
        fetchAppointments({ page: 1 })
      } catch (err) {
        console.error('Erro ao salvar agendamento:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar agendamento' })
      } finally {
        savingAppointment.value = false
      }
    }

    const saveRequest = async () => {
      if (!requestForm.value.establishment_id || !requestForm.value.service_id || !requestForm.value.start_at) {
        $q.notify({ type: 'warning', message: 'Preencha estabelecimento, serviço e data/hora da solicitação' })
        return
      }

      if (!isClient.value && !requestForm.value.client_user_id) {
        $q.notify({ type: 'warning', message: 'Selecione o cliente' })
        return
      }

      if (!isClient.value && !requestForm.value.professional_id) {
        $q.notify({ type: 'warning', message: 'Selecione o profissional que ficará proposto' })
        return
      }

      savingRequest.value = true
      try {
        const payload = {
          establishment_id: requestForm.value.establishment_id,
          service_id: requestForm.value.service_id,
          start_at: normalizeDateTime(requestForm.value.start_at),
          notes: requestForm.value.notes?.trim() || undefined,
        }

        if (!isClient.value) {
          payload.client_user_id = requestForm.value.client_user_id
          payload.professional_id = requestForm.value.professional_id
        }

        await api.post('/appointments/requests', payload)
        $q.notify({ type: 'positive', message: 'Solicitação enviada com sucesso' })
        closeRequestDialog()
        mainTab.value = 'requests'
        fetchRequests({ page: 1 })
      } catch (err) {
        console.error('Erro ao salvar solicitação:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao enviar solicitação' })
      } finally {
        savingRequest.value = false
      }
    }

    const updateAppointmentStatus = async (appointment, status) => {
      try {
        const endpointMap = {
          checked_in: { method: 'post', url: `/appointments/${appointment.id}/checkin` },
          completed: { method: 'post', url: `/appointments/${appointment.id}/complete` },
          no_show: { method: 'post', url: `/appointments/${appointment.id}/no-show` },
          cancelled: { method: 'delete', url: `/appointments/${appointment.id}` },
        }
        const endpoint = endpointMap[status]

        if (endpoint) {
          await api[endpoint.method](endpoint.url)
        } else {
          await api.put(`/appointments/${appointment.id}`, { status })
        }

        $q.notify({ type: 'positive', message: 'Status atualizado com sucesso' })
        fetchAppointments()
      } catch (err) {
        console.error('Erro ao atualizar status:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao atualizar status' })
      }
    }

    const canEditAppointment = (appointment) => {
      if (canManageAppointments.value) return true
      return Number(appointment.user_id) === Number(userId.value) && appointment.status === 'booked'
    }

    const canChangeAppointmentStatus = (appointment) => (
      canManageAppointments.value && !['completed', 'cancelled', 'no_show'].includes(appointment.status)
    )

    const canRespondRequest = (request) => {
      if (request.status !== 'pending') return false
      if (isClient.value) {
        return request.direction === 'staff_to_client' && Number(request.client_user_id) === Number(userId.value)
      }
      return request.direction === 'client_to_establishment'
    }

    const canCancelRequest = (request) => (
      request.status === 'pending' && Number(request.requested_by_user_id) === Number(userId.value)
    )

    const withRequestAction = async (requestId, actionName, callback) => {
      requestActionId.value = requestId
      requestAction.value = actionName
      try {
        await callback()
      } finally {
        requestActionId.value = null
        requestAction.value = ''
      }
    }

    const submitAcceptRequestDirect = async (request, payload) => {
      await withRequestAction(request.id, 'accept', async () => {
        await api.post(`/appointments/requests/${request.id}/accept`, payload)
        $q.notify({ type: 'positive', message: 'Solicitação aceita com sucesso' })
        await Promise.all([
          fetchRequests(),
          fetchAppointments({ page: 1 }),
        ])
      })
    }

    const prepareAcceptRequest = async (request) => {
      if (isClient.value) {
        try {
          await submitAcceptRequestDirect(request, {})
        } catch (err) {
          console.error('Erro ao aceitar solicitação:', err)
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao aceitar solicitação' })
        }
        return
      }

      if (isProfessional.value) {
        try {
          const ownProfessionalId = getOwnProfessionalId(request.establishment_id)
          await submitAcceptRequestDirect(request, { professional_id: ownProfessionalId })
        } catch (err) {
          console.error('Erro ao aceitar solicitação:', err)
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao aceitar solicitação' })
        }
        return
      }

      selectedRequest.value = request
      acceptForm.value = {
        professional_id: request.professional_id || null,
        decision_note: '',
      }
      showAcceptDialog.value = true
    }

    const submitAcceptRequest = async () => {
      if (acceptNeedsProfessional.value && !acceptForm.value.professional_id) {
        $q.notify({ type: 'warning', message: 'Selecione o profissional para concluir o aceite' })
        return
      }

      savingAccept.value = true
      try {
        await submitAcceptRequestDirect(selectedRequest.value, {
          professional_id: acceptForm.value.professional_id,
          decision_note: acceptForm.value.decision_note?.trim() || undefined,
        })
        closeAcceptDialog()
      } catch (err) {
        console.error('Erro ao aceitar solicitação:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao aceitar solicitação' })
      } finally {
        savingAccept.value = false
      }
    }

    const rejectRequest = async (request) => {
      try {
        await withRequestAction(request.id, 'reject', async () => {
          await api.post(`/appointments/requests/${request.id}/reject`)
          $q.notify({ type: 'positive', message: 'Solicitação recusada' })
          await fetchRequests()
        })
      } catch (err) {
        console.error('Erro ao rejeitar solicitação:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao rejeitar solicitação' })
      }
    }

    const cancelRequest = async (request) => {
      try {
        await withRequestAction(request.id, 'cancel', async () => {
          await api.post(`/appointments/requests/${request.id}/cancel`)
          $q.notify({ type: 'positive', message: 'Solicitação cancelada' })
          await fetchRequests()
        })
      } catch (err) {
        console.error('Erro ao cancelar solicitação:', err)
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao cancelar solicitação' })
      }
    }

    const getAppointmentStatusColor = (status) => ({
      booked: 'blue',
      confirmed: 'cyan',
      checked_in: 'orange',
      in_progress: 'purple',
      completed: 'positive',
      no_show: 'deep-orange',
      cancelled: 'negative',
    }[status] || 'grey')

    const getAppointmentStatusLabel = (status) => ({
      booked: 'Agendado',
      confirmed: 'Confirmado',
      checked_in: 'Check-in',
      in_progress: 'Em andamento',
      completed: 'Concluído',
      no_show: 'Não compareceu',
      cancelled: 'Cancelado',
    }[status] || status)

    const getRequestStatusColor = (status) => ({
      pending: 'warning',
      accepted: 'positive',
      rejected: 'negative',
      cancelled: 'grey-7',
    }[status] || 'grey')

    const getRequestStatusLabel = (status) => ({
      pending: 'Pendente',
      accepted: 'Aceita',
      rejected: 'Recusada',
      cancelled: 'Cancelada',
    }[status] || status)

    const getRequestDirectionColor = (direction) => (
      direction === 'client_to_establishment' ? 'indigo' : 'teal'
    )

    const getRequestDirectionLabel = (direction) => (
      direction === 'client_to_establishment' ? 'Cliente -> Estabelecimento' : 'Equipe -> Cliente'
    )

    const getRequestPrimaryParty = (request) => {
      if (isClient.value) {
        if (request.direction === 'client_to_establishment') return request.establishment_name || 'Estabelecimento'
        return request.requested_by_name || request.professional_name || 'Equipe'
      }

      return request.client_name || request.client_email || `Cliente #${request.client_user_id}`
    }

    const getRequestSecondaryParty = (request) => {
      if (request.direction === 'client_to_establishment') {
        return request.requested_by_name ? `Solicitado por ${request.requested_by_name}` : 'Aguardando estabelecimento'
      }

      return request.professional_name ? `Profissional: ${request.professional_name}` : 'Aguardando cliente'
    }

    const formatDate = (dateString) => (dateString ? new Date(dateString).toLocaleDateString('pt-BR') : '-')
    const formatTime = (dateString) => (dateString ? new Date(dateString).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '-')
    const formatSlotTime = (dateString) => {
      if (!dateString) return ''
      const time = dateString.includes('T') ? dateString.split('T')[1] : dateString.split(' ')[1]
      return time ? time.slice(0, 5) : dateString
    }

    onMounted(async () => {
      try {
        await fetchUserInfo()
        await fetchEstablishments()
        await Promise.all([
          fetchProfessionals(),
          fetchServices(),
          fetchClients(),
        ])
        await Promise.all([
          fetchAppointments({ page: 1 }),
          fetchRequests({ page: 1 }),
        ])
      } catch (err) {
        console.error('Erro ao inicializar agendamentos:', err)
        $q.notify({ type: 'negative', message: 'Não foi possível carregar a tela de agendamentos' })
      }
    })

    return {
      router,
      mainTab,
      showCreateButton,
      requestTabLabel,
      pendingRequestCount,
      loadingAppointments,
      loadingRequests,
      appointments,
      requests,
      appointmentsPagination,
      requestsPagination,
      appointmentFilters,
      requestFilters,
      appointmentStatusOptions,
      appointmentBucketOptions,
      requestStatusOptions,
      staffEstablishmentOptions,
      requestEstablishmentOptions,
      appointmentSearch,
      requestSearch,
      filteredAppointments,
      filteredRequests,
      fetchAppointments,
      fetchRequests,
      clearAppointmentFilters,
      clearRequestFilters,
      formatDate,
      formatTime,
      getAppointmentStatusColor,
      getAppointmentStatusLabel,
      getRequestStatusColor,
      getRequestStatusLabel,
      getRequestDirectionColor,
      getRequestDirectionLabel,
      getRequestPrimaryParty,
      getRequestSecondaryParty,
      canEditAppointment,
      canChangeAppointmentStatus,
      updateAppointmentStatus,
      canRespondRequest,
      canCancelRequest,
      prepareAcceptRequest,
      rejectRequest,
      cancelRequest,
      showAppointmentDialog,
      showRequestDialog,
      showAcceptDialog,
      isEditingAppointment,
      appointmentForm,
      requestForm,
      acceptForm,
      clientOptions,
      appointmentProfessionalOptions,
      requestProfessionalOptions,
      appointmentServiceOptions,
      requestServiceOptions,
      acceptProfessionalOptions,
      professionalFieldDisabled,
      acceptNeedsProfessional,
      appointmentSlotDate,
      appointmentAvailableSlots,
      appointmentSlotsLoading,
      appointmentSlotsSearched,
      openCreateDialog,
      closeAppointmentDialog,
      closeRequestDialog,
      closeAcceptDialog,
      saveAppointment,
      saveRequest,
      submitAcceptRequest,
      onAppointmentEstablishmentChange,
      onRequestEstablishmentChange,
      onAppointmentSlotDepsChange,
      editAppointment,
      formatSlotTime,
      savingAppointment,
      savingRequest,
      savingAccept,
      requestActionId,
      requestAction,
      isClient,
    }
  },
})
</script>

<style lang="scss" scoped>
.appointments-page {
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

.main-card {
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

.tab-panel-padded {
  padding: 1.25rem;
}

.filters-card {
  padding: 1rem 1rem 1.125rem;
  border: 1px solid var(--qm-border);
  border-radius: 14px;
  background: var(--qm-bg-secondary);
  margin-bottom: 1rem;
}

.filters-row {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  align-items: center;
}

.filter-select {
  min-width: 180px;
}

.filter-date {
  min-width: 150px;
}

.search-input {
  min-width: 240px;
}

.pending-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  border-radius: 14px;
  background: color-mix(in srgb, #f59e0b 13%, white);
  color: #8a5a00;
  border: 1px solid color-mix(in srgb, #f59e0b 25%, white);
  margin-bottom: 1rem;
  font-size: 0.875rem;
  font-weight: 500;
}

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

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    padding: 0.875rem 1rem;
    text-align: left;
    vertical-align: top;
  }

  thead tr {
    background: var(--qm-bg-secondary);
  }

  thead th {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--qm-text-muted);
    border-bottom: 1px solid var(--qm-border);
  }

  tbody tr {
    border-bottom: 1px solid var(--qm-border);
    transition: background 0.2s ease;

    &:last-child {
      border-bottom: none;
    }

    &:hover {
      background: var(--qm-bg-secondary);
    }
  }
}

.clickable-row {
  cursor: pointer;
}

.primary-cell {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.primary-text {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--qm-text-primary);
}

.secondary-text {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.status-badge {
  font-size: 0.6875rem;
  padding: 0.25rem 0.625rem;
}

.row-actions {
  display: flex;
  gap: 0.25rem;
  align-items: center;
}

.row-actions--requests {
  justify-content: flex-end;
}

.table-pagination {
  display: flex;
  justify-content: center;
  padding: 1rem;
  border-top: 1px solid var(--qm-border);
}

.th-client {
  min-width: 190px;
}

.th-professional,
.th-establishment {
  min-width: 170px;
}

.th-service {
  min-width: 150px;
}

.th-datetime {
  min-width: 150px;
}

.th-flow,
.th-status {
  min-width: 120px;
}

.th-actions {
  width: 120px;
}

.dialog-card {
  width: 100%;
  max-width: 560px;
  border-radius: 16px;
  background: var(--qm-bg-primary);
}

.dialog-large {
  max-width: 680px;
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.form-span {
  grid-column: 1 / -1;
}

.slots-loading,
.slots-empty {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--qm-text-muted);
  font-size: 0.8125rem;
}

.slots-section {
  grid-column: 1 / -1;
}

.slots-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--qm-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.slots-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.slot-btn {
  border-radius: 8px;
  min-width: 64px;
}

.slot-btn--idle {
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-secondary);
  border: 1px solid var(--qm-border);
}

@media (max-width: 900px) {
  .search-input {
    min-width: 100%;
  }
}

@media (max-width: 640px) {
  .appointments-page {
    padding: 0 1rem 1rem;
  }

  .tab-panel-padded {
    padding: 1rem;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
