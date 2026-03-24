<template>
  <q-page class="detail-page">
    <!-- Header (same pattern as EstablishmentDetailPage) -->
    <div class="page-header">
      <div class="header-back-row">
        <q-btn flat round dense icon="arrow_back" class="back-btn" @click="goBack" />
      </div>
      <div class="header-left">
        <h1 class="page-title">{{ queue?.name || '\u00A0' }}</h1>
        <StatusPill
          v-if="queue"
          :label="statusLabel"
          :variant="statusVariant"
          dot
          clickable
          @click="openStatusMenu"
        />
      </div>
      <div class="header-right" v-if="canManage && queue">
        <q-btn
          v-if="queue.status === 'open' && statistics?.total_waiting > 0"
          color="primary"
          icon="campaign"
          label="Chamar Próximo"
          no-caps
          :loading="callingNext"
          @click="callNext"
        />
      </div>
      <div class="header-bottom">
        <p class="page-subtitle" v-if="queue">
          {{ queue?.establishment_name || '' }}
          <template v-if="queue?.service_name"> · {{ queue.service_name }}</template>
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="primary" size="40px" />
      <p>Carregando...</p>
    </div>

    <template v-else-if="queue">

      <!-- =============== REGULAR USER VIEW =============== -->
      <template v-if="isRegularUser">
        <div class="soft-card q-mb-lg">
          <h2 class="section-title">Informações da Fila</h2>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Pessoas Aguardando</span>
              <span class="detail-value detail-value-lg">{{ statistics?.total_waiting || 0 }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Tempo Médio de Espera</span>
              <span class="detail-value">{{ statistics?.average_wait_time_minutes || 0 }} min</span>
            </div>
          </div>
        <div v-if="userEntry" class="highlight-box q-mt-md">
          <q-icon name="person" size="20px" />
          <span>Você está na posição <strong>{{ userEntry.position }}</strong></span>
          <span class="text-muted">(~{{ userEntry.estimated_wait_minutes || '?' }} min)</span>
        </div>
      </div>

      <div v-if="servingEntries.length > 0" class="soft-card q-mb-lg">
        <h2 class="section-title">Em atendimento agora</h2>
        <div class="list-items">
          <div v-for="entry in servingEntries" :key="entry.id" class="list-item">
            <div class="list-item-info">
              <div class="list-item-avatar"><q-icon name="support_agent" size="18px" /></div>
              <div class="list-item-details">
                <span class="list-item-name">{{ entry.user_name }}</span>
                <span class="list-item-meta">
                  <template v-if="entry.professional_name">Com {{ entry.professional_name }}</template>
                  <template v-if="entry.serving_since_minutes >= 0">
                    <span v-if="entry.professional_name"> · </span>
                    há {{ formatWaitTime(entry.serving_since_minutes) }}
                  </template>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Queue Professionals visible to clients -->
      <div v-if="queueProfessionals.length > 0" class="soft-card q-mb-lg">
          <h2 class="section-title">Profissionais Atendendo</h2>
          <div class="list-items">
            <div v-for="prof in queueProfessionals.filter(p => p.is_active)" :key="prof.id" class="list-item">
              <div class="list-item-info">
                <div class="list-item-avatar"><q-icon name="person" size="18px" /></div>
                <div class="list-item-details">
                  <span class="list-item-name">{{ prof.user_name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="queue.status === 'open' && !userEntry" class="soft-card">
          <h2 class="section-title">Entrar na Fila</h2>
          <p class="text-muted q-mb-md">Insira o código de acesso fornecido pelo estabelecimento:</p>
          <div class="join-form">
            <q-input v-model="accessCode" outlined dense placeholder="Código de acesso" class="code-input" @keyup.enter="joinWithCode">
              <template v-slot:prepend><q-icon name="vpn_key" /></template>
            </q-input>
            <q-btn color="primary" label="Entrar" no-caps :loading="joining" @click="joinWithCode" />
          </div>
        </div>
      </template>

      <!-- =============== STAFF VIEW =============== -->
      <template v-else>
        <!-- Stats Row -->
        <div class="stats-row q-mb-lg">
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.total_waiting || 0 }}</span>
            <span class="stat-text">Aguardando</span>
          </div>
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.total_being_served || 0 }}</span>
            <span class="stat-text">Em atendimento</span>
          </div>
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.total_completed_today || 0 }}</span>
            <span class="stat-text">Concluídos hoje</span>
          </div>
          <div class="stat-box soft-card">
            <span class="stat-number">{{ statistics?.average_wait_time_minutes || 0 }} min</span>
            <span class="stat-text">Tempo médio</span>
          </div>
        </div>

        <!-- Main Tabbed Card -->
        <div class="soft-card main-card">
          <q-tabs v-model="mainTab" dense class="main-tabs" active-color="primary" indicator-color="primary" align="left" narrow-indicator>
            <q-tab name="flow" icon="swap_vert" label="Fluxo" no-caps />
            <q-tab name="professionals" icon="badge" label="Profissionais" no-caps />
            <q-tab name="info" icon="info" label="Informações" no-caps />
            <q-tab name="services" icon="miscellaneous_services" label="Serviços" no-caps />
            <q-tab name="tokens" icon="qr_code_2" label="Códigos" no-caps />
            <q-tab name="reports" icon="analytics" label="Relatórios" no-caps />
          </q-tabs>

          <q-separator style="margin-top: 10px;" />

          <q-tab-panels v-model="mainTab" animated class="tab-panels">

            <!-- ================================================================ -->
            <!-- TAB: FLUXO DA FILA -->
            <!-- ================================================================ -->
            <q-tab-panel name="flow" class="q-pa-none">

              <div v-if="canManage" class="flow-actions-bar">
                <q-btn outline color="primary" icon="person_add" label="Adicionar Pessoa" no-caps dense @click="openAddPersonDialog" />
              </div>

              <q-tabs v-model="flowTab" dense class="flow-sub-tabs" active-color="primary" indicator-color="primary" align="center" narrow-indicator>
                <q-tab name="waiting" no-caps>
                  <div class="tab-label-row">
                    <span>Aguardando</span>
                    <StatusPill v-if="waitingEntries.length" :label="String(waitingEntries.length)" variant="warning" />
                  </div>
                </q-tab>
                <q-tab name="serving" no-caps>
                  <div class="tab-label-row">
                    <span>Em Atendimento</span>
                    <StatusPill v-if="servingEntries.length" :label="String(servingEntries.length)" variant="info" />
                  </div>
                </q-tab>
                <q-tab name="completed" no-caps>
                  <div class="tab-label-row">
                    <span>Concluídos</span>
                    <StatusPill v-if="completedEntries.length" :label="String(completedEntries.length)" variant="positive" />
                  </div>
                </q-tab>
              </q-tabs>

              <q-separator />

              <!-- Selection Toolbar -->
              <div v-if="hasAnySelection" class="selection-toolbar">
                <span class="selection-count">{{ totalSelected }} selecionado(s)</span>
                <q-btn flat dense no-caps icon="arrow_upward" label="Mover p/ cima" @click="batchMoveSelected('up')" :loading="movingEntries" />
                <q-btn flat dense no-caps icon="arrow_downward" label="Mover p/ baixo" @click="batchMoveSelected('down')" :loading="movingEntries" />
                <q-btn flat dense no-caps icon="delete_sweep" label="Remover" color="negative" @click="batchRemoveSelected(flowTab)" :loading="batchRemoving" />
                <q-btn flat dense no-caps label="Limpar" @click="clearSelection" />
              </div>

              <q-tab-panels v-model="flowTab" animated class="flow-panels">

                <!-- ====== WAITING ====== -->
                <q-tab-panel name="waiting" class="q-pa-none">
                  <div v-if="waitingEntries.length === 0" class="empty-state-sm">
                    <q-icon name="groups" size="48px" />
                    <p>Nenhuma pessoa aguardando</p>
                  </div>
                  <div v-else class="entry-list" :class="{ 'has-selection': hasWaitingSelection }">
                    <div
                      v-for="(entry, index) in sortedWaitingEntries"
                      :key="entry.id"
                      class="entry-row"
                      :class="{ 'entry-row--selected': selectedWaiting.includes(entry.id) }"
                    >
                      <div class="entry-body" @click="openEntryMenu($event, entry, 'waiting')">
                        <div class="entry-pos-wrap">
                          <div class="entry-pos">{{ index + 1 }}</div>
                          <q-checkbox
                            v-if="canManage"
                            :model-value="selectedWaiting.includes(entry.id)"
                            @update:model-value="toggleSelect(entry.id, 'waiting')"
                            dense
                            class="entry-check"
                            @click.stop
                          />
                        </div>
                        <div class="entry-info">
                          <div class="entry-name-row">
                            <span class="entry-name">{{ entry.user_name }}</span>
                            <StatusPill v-if="entry.priority >= 2" label="Muito Prioritário" variant="negative" />
                            <StatusPill v-else-if="entry.priority === 1" label="Prioridade" variant="orange" />
                          </div>
                          <span class="entry-meta">
                            <q-icon name="schedule" size="12px" class="q-mr-xs" />
                            {{ formatWaitTime(entry.waiting_since_minutes) }}
                          </span>
                        </div>
                      </div>
                      <q-btn v-if="canManage" flat round dense icon="more_vert" size="sm" class="entry-dots" @click.stop="openEntryMenu($event, entry, 'waiting')" />
                    </div>
                  </div>
                </q-tab-panel>

                <!-- ====== SERVING ====== -->
                <q-tab-panel name="serving" class="q-pa-none">
                  <div v-if="servingEntries.length === 0" class="empty-state-sm">
                    <q-icon name="support_agent" size="48px" />
                    <p>Nenhuma pessoa em atendimento</p>
                  </div>
                  <div v-else class="entry-list" :class="{ 'has-selection': hasServingSelection }">
                    <div
                      v-for="entry in servingEntries"
                      :key="entry.id"
                      class="entry-row"
                      :class="{ 'entry-row--selected': selectedServing.includes(entry.id) }"
                    >
                      <div class="entry-body" @click="openEntryMenu($event, entry, 'serving')">
                        <div class="entry-pos-wrap">
                          <div class="entry-pos entry-pos--serving"><q-icon name="headset_mic" size="16px" /></div>
                          <q-checkbox v-if="canManage" :model-value="selectedServing.includes(entry.id)" @update:model-value="toggleSelect(entry.id, 'serving')" dense class="entry-check" @click.stop />
                        </div>
                        <div class="entry-info">
                          <div class="entry-name-row">
                            <span class="entry-name">{{ entry.user_name }}</span>
                            <StatusPill v-if="entry.priority > 0" label="Prioridade" variant="orange" />
                          </div>
                          <span class="entry-meta">
                            <q-icon name="timer" size="12px" class="q-mr-xs" />
                            Em atendimento há {{ formatWaitTime(entry.serving_since_minutes) }}
                            <template v-if="entry.professional_name"> · por {{ entry.professional_name }}</template>
                          </span>
                        </div>
                      </div>
                      <q-btn v-if="canManage" flat round dense icon="more_vert" size="sm" class="entry-dots" @click.stop="openEntryMenu($event, entry, 'serving')" />
                    </div>
                  </div>
                </q-tab-panel>

                <!-- ====== COMPLETED ====== -->
                <q-tab-panel name="completed" class="q-pa-none">
                  <!-- Completed date filter -->
                  <div class="completed-filter">
                    <q-btn-toggle
                      v-model="completedPeriod"
                      no-caps dense rounded spread
                      toggle-color="primary"
                      :options="[
                        { label: 'Hoje', value: 'today' },
                        { label: 'Período', value: 'custom' },
                      ]"
                      class="completed-toggle"
                      @update:model-value="onCompletedPeriodChange"
                    />
                    <div v-if="completedPeriod === 'custom'" class="completed-dates">
                      <q-input v-model="completedFrom" outlined dense type="date" label="De" @update:model-value="fetchData" />
                      <q-input v-model="completedTo" outlined dense type="date" label="Até" @update:model-value="fetchData" />
                    </div>
                  </div>

                  <div v-if="completedEntries.length === 0" class="empty-state-sm">
                    <q-icon name="check_circle" size="48px" />
                    <p>Nenhum atendimento concluído{{ completedPeriod === 'today' ? ' hoje' : '' }}</p>
                  </div>
                  <div v-else class="entry-list">
                    <div v-for="entry in completedEntries" :key="entry.id" class="entry-row">
                      <div class="entry-body" @click="openEntryMenu($event, entry, 'completed')">
                        <div class="entry-pos entry-pos--done" :class="{ 'entry-pos--noshow': entry.status === 'no_show' }">
                          <q-icon :name="entry.status === 'no_show' ? 'person_off' : 'check'" size="16px" />
                        </div>
                        <div class="entry-info">
                          <div class="entry-name-row">
                            <span class="entry-name">{{ entry.user_name }}</span>
                            <StatusPill :label="entry.status === 'no_show' ? 'Não compareceu' : 'Concluído'" :variant="entry.status === 'no_show' ? 'negative' : 'positive'" />
                          </div>
                          <span class="entry-meta">
                            <q-icon name="event" size="12px" class="q-mr-xs" />
                            {{ formatDate(entry.completed_at || entry.updated_at) }}
                          </span>
                        </div>
                      </div>
                      <q-btn v-if="canManage" flat round dense icon="more_vert" size="sm" class="entry-dots" @click.stop="openEntryMenu($event, entry, 'completed')" />
                    </div>
                  </div>
                </q-tab-panel>
              </q-tab-panels>
            </q-tab-panel>

            <!-- ================================================================ -->
            <!-- TAB: PROFISSIONAIS -->
            <!-- ================================================================ -->
            <q-tab-panel name="professionals" class="tab-panel-padded">
              <div class="panel-header">
                <div class="panel-header-text">
                  <h3>Profissionais da Fila</h3>
                  <p>Gerencie quem atende nesta fila</p>
                </div>
                <div class="panel-header-actions">
                  <q-btn
                    v-if="canManage"
                    color="primary" icon="person_add" label="Adicionar" no-caps
                    @click="openAddProfDialog"
                  />
                  <q-btn
                    v-else-if="userRole === 'professional'"
                    color="primary" icon="person_add" label="Entrar na fila" no-caps
                    @click="selfAddProfessional"
                    :loading="addingProf"
                  />
                </div>
              </div>

              <div v-if="qpLoading" class="loading-state-sm"><q-spinner-dots color="primary" size="24px" /></div>
              <div v-else-if="queueProfessionals.length === 0" class="empty-state-sm">
                <q-icon name="badge" size="48px" />
                <p>Nenhum profissional vinculado a esta fila</p>
              </div>
              <div v-else class="list-items">
                <div v-for="prof in queueProfessionals" :key="prof.id" class="list-item" @click="openProfMenu($event, prof)">
                  <div class="list-item-info">
                    <div class="list-item-avatar" :class="{ 'avatar-inactive': !prof.is_active }">
                      <q-icon name="person" size="18px" />
                    </div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ prof.user_name }}</span>
                      <span class="list-item-meta">
                        {{ prof.user_email }}
                        · <StatusPill :label="prof.is_active ? 'Ativo' : 'Inativo'" :variant="prof.is_active ? 'positive' : 'grey'" />
                      </span>
                    </div>
                  </div>
                  <q-btn flat round dense icon="more_vert" size="sm" class="entry-dots" @click.stop="openProfMenu($event, prof)" />
                </div>
              </div>
            </q-tab-panel>

            <!-- ================================================================ -->
            <!-- TAB: INFORMAÇÕES (editable) -->
            <!-- ================================================================ -->
            <q-tab-panel name="info" class="tab-panel-padded">
              <div class="panel-header">
                <div class="panel-header-text">
                  <h3>Dados da Fila</h3>
                  <p>Gerencie as informações da fila</p>
                </div>
                <div class="panel-header-actions" v-if="canManage">
                  <template v-if="infoEditing">
                    <q-btn flat label="Cancelar" no-caps @click="cancelInfoEdit" />
                    <q-btn color="primary" label="Salvar" no-caps :loading="saving" @click="saveInfoEdit" />
                  </template>
                  <q-btn v-else outline icon="edit" label="Editar" no-caps @click="startInfoEdit" />
                </div>
              </div>

              <div class="detail-grid q-mb-lg">
                <div class="detail-item">
                  <span class="detail-label">Nome</span>
                  <q-input v-if="infoEditing" v-model="infoForm.name" outlined dense />
                  <span v-else class="detail-value">{{ queue.name }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Status</span>
                  <q-select v-if="infoEditing" v-model="infoForm.status" :options="statusOptions" emit-value map-options outlined dense />
                  <StatusPill v-else :label="statusLabel" :variant="statusVariant" dot />
                </div>
                <div class="detail-item">
                  <span class="detail-label">Estabelecimento</span>
                  <span class="detail-value">{{ queue.establishment_name || '-' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Serviço Vinculado</span>
                  <span class="detail-value">{{ queue.service_name || 'Nenhum' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Capacidade Máxima</span>
                  <q-input v-if="infoEditing" v-model.number="infoForm.max_capacity" outlined dense type="number" placeholder="Ilimitada" />
                  <span v-else class="detail-value">{{ queue.max_capacity || 'Ilimitada' }}</span>
                </div>
                <div class="detail-item full-width">
                  <span class="detail-label">Descrição</span>
                  <q-input v-if="infoEditing" v-model="infoForm.description" outlined dense type="textarea" autogrow />
                  <span v-else class="detail-value">{{ queue.description || 'Nenhuma' }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Criada em</span>
                  <span class="detail-value">{{ formatDate(queue.created_at) }}</span>
                </div>
              </div>
            </q-tab-panel>

            <!-- ================================================================ -->
            <!-- TAB: SERVIÇOS -->
            <!-- ================================================================ -->
            <q-tab-panel name="services" class="tab-panel-padded">
              <div class="panel-header">
                <div class="panel-header-text">
                  <h3>Serviços</h3>
                  <p>Serviços vinculados a esta fila</p>
                </div>
                <q-btn v-if="canManage" color="primary" icon="add" label="Adicionar" no-caps @click="openAddServiceDialog" />
              </div>

              <div v-if="svcLoading" class="loading-state-sm"><q-spinner-dots color="primary" size="24px" /></div>
              <div v-else-if="queueServices.length === 0" class="empty-state-sm">
                <q-icon name="miscellaneous_services" size="48px" />
                <p>Nenhum serviço vinculado a esta fila</p>
              </div>
              <div v-else class="services-grid">
                <div
                  v-for="svc in queueServices"
                  :key="svc.queue_service_id"
                  class="service-card soft-card"
                  :class="{ 'service-card--interactive': canManage }"
                  @click="canManage ? openServiceMenu($event, svc) : null"
                >
                  <div class="service-card__icon">
                    <q-icon :name="svc.icon || 'work_outline'" size="28px" />
                  </div>
                  <div class="service-card__body">
                    <span class="service-card__name">{{ svc.name }}</span>
                    <span class="service-card__meta">
                      {{ svc.duration_minutes }} min
                      <template v-if="svc.price"> · R$ {{ Number(svc.price).toFixed(2) }}</template>
                    </span>
                    <span v-if="svc.description" class="service-card__desc">{{ svc.description }}</span>
                  </div>
                  <span v-if="canManage" class="service-card__hint">Clique para gerenciar</span>
                </div>
              </div>
            </q-tab-panel>

            <!-- ================================================================ -->
            <!-- TAB: CÓDIGOS DE ACESSO -->
            <!-- ================================================================ -->
            <q-tab-panel name="tokens" class="tab-panel-padded">
              <div class="panel-header">
                <div class="panel-header-text">
                  <h3>Códigos de Acesso</h3>
                  <p>Crie e gerencie códigos para que clientes entrem na fila</p>
                </div>
                <q-btn color="primary" icon="add" label="Novo Código" no-caps @click="openCreateCode" />
              </div>

              <div v-if="codesLoading" class="loading-state-sm"><q-spinner-dots color="primary" size="24px" /></div>
              <div v-else-if="accessCodes.length === 0" class="empty-state-sm">
                <q-icon name="qr_code_2" size="48px" />
                <p>Nenhum código de acesso criado</p>
              </div>
              <div v-else class="list-items">
                <div
                  v-for="code in accessCodes"
                  :key="code.id"
                  class="list-item list-item--interactive"
                  @click="openCodeMenu($event, code)"
                >
                  <div class="list-item-info">
                    <div class="list-item-avatar" :class="{ 'avatar-inactive': !code.is_active }">
                      <q-icon :name="code.is_active ? 'qr_code' : 'block'" size="18px" />
                    </div>
                    <div class="list-item-details">
                      <span class="list-item-name code-mono">{{ code.code }}</span>
                      <span class="list-item-meta">
                        <StatusPill :label="code.is_active ? 'Ativo' : 'Inativo'" :variant="code.is_active ? 'positive' : 'grey'" />
                        · Usos: {{ code.uses || 0 }}{{ code.max_uses ? '/' + code.max_uses : ' (ilimitado)' }}
                        <template v-if="code.expires_at"> · Expira: {{ formatDate(code.expires_at) }}</template>
                        <template v-else> · Sem expiração</template>
                      </span>
                    </div>
                  </div>
                  <div class="list-item-side">
                    <q-btn flat round dense icon="content_copy" size="sm" @click.stop="copyToClipboard(code.code)"><q-tooltip>Copiar código</q-tooltip></q-btn>
                    <q-btn flat round dense icon="more_vert" size="sm" @click.stop="openCodeMenu($event, code)"><q-tooltip>Opções</q-tooltip></q-btn>
                  </div>
                </div>
              </div>
            </q-tab-panel>

            <!-- ================================================================ -->
            <!-- TAB: RELATÓRIOS -->
            <!-- ================================================================ -->
            <q-tab-panel name="reports" class="tab-panel-padded">
              <div class="panel-header">
                <div class="panel-header-text">
                  <h3>Relatórios</h3>
                  <p>Indicadores e métricas de performance da fila</p>
                </div>
                <div class="panel-header-actions">
                  <q-select v-model="reportPeriod" :options="periodOptions" emit-value map-options dense outlined style="min-width:140px;" @update:model-value="fetchReports" />
                  <q-btn flat dense icon="refresh" @click="fetchReports" :loading="reportsLoading" />
                  <q-btn outline color="primary" icon="open_in_new" label="Relatório Completo" no-caps @click="openGlobalReports" />
                </div>
              </div>

              <div v-if="reportsLoading" class="loading-state-sm"><q-spinner-dots color="primary" size="24px" /></div>

              <template v-else-if="reportData">
                <!-- Summary cards -->
                <div class="report-grid q-mb-lg">
                  <div class="report-card"><div class="report-val">{{ reportData.summary.total_entries }}</div><div class="report-lbl">Total de Entradas</div></div>
                  <div class="report-card report-card--success"><div class="report-val">{{ reportData.summary.total_completed }}</div><div class="report-lbl">Atendidos</div></div>
                  <div class="report-card report-card--danger"><div class="report-val">{{ reportData.summary.total_no_show }}</div><div class="report-lbl">Não Compareceram</div></div>
                  <div class="report-card report-card--warning"><div class="report-val">{{ reportData.summary.total_cancelled }}</div><div class="report-lbl">Cancelados</div></div>
                  <div class="report-card report-card--info"><div class="report-val">{{ reportData.summary.completion_rate }}%</div><div class="report-lbl">Comparecimento</div></div>
                  <div class="report-card"><div class="report-val">{{ reportData.summary.avg_wait_minutes }} min</div><div class="report-lbl">Espera Média</div></div>
                  <div class="report-card"><div class="report-val">{{ reportData.summary.avg_service_minutes }} min</div><div class="report-lbl">Atendimento Médio</div></div>
                  <div class="report-card"><div class="report-val">{{ reportData.summary.min_wait_minutes }}-{{ reportData.summary.max_wait_minutes }} min</div><div class="report-lbl">Espera Mín-Máx</div></div>
                </div>

                <!-- Attendance chart (bar) -->
                <div class="section-header q-mb-md"><h3 class="section-title">Presença por Dia</h3></div>
                <div v-if="reportData.daily_breakdown.length === 0" class="empty-state-sm q-mb-lg"><p>Sem dados para o período</p></div>
                <div v-else class="chart-container q-mb-xl">
                  <div class="bar-chart">
                    <div v-for="day in chartDailyData" :key="day.date" class="bar-chart__col">
                      <div class="bar-chart__bars">
                        <div class="bar-chart__bar bar-chart__bar--completed" :style="{ height: day.completedPct + '%' }" :title="'Atendidos: ' + day.completed"><span v-if="day.completed > 0" class="bar-chart__val">{{ day.completed }}</span></div>
                        <div class="bar-chart__bar bar-chart__bar--noshow" :style="{ height: day.noshowPct + '%' }" :title="'Não comp.: ' + day.no_show"><span v-if="day.no_show > 0" class="bar-chart__val">{{ day.no_show }}</span></div>
                        <div class="bar-chart__bar bar-chart__bar--cancelled" :style="{ height: day.cancelledPct + '%' }" :title="'Cancelados: ' + day.cancelled"><span v-if="day.cancelled > 0" class="bar-chart__val">{{ day.cancelled }}</span></div>
                      </div>
                      <span class="bar-chart__lbl">{{ day.label }}</span>
                    </div>
                  </div>
                  <div class="chart-legend">
                    <span class="chart-legend__item"><span class="chart-legend__dot chart-legend__dot--completed"></span>Atendidos</span>
                    <span class="chart-legend__item"><span class="chart-legend__dot chart-legend__dot--noshow"></span>Não comp.</span>
                    <span class="chart-legend__item"><span class="chart-legend__dot chart-legend__dot--cancelled"></span>Cancelados</span>
                  </div>
                </div>

                <!-- Wait time chart -->
                <div class="section-header q-mb-md"><h3 class="section-title">Tempo Médio de Espera</h3></div>
                <div v-if="reportData.daily_breakdown.length === 0" class="empty-state-sm q-mb-lg"><p>Sem dados</p></div>
                <div v-else class="chart-container q-mb-xl">
                  <div class="line-chart">
                    <svg viewBox="0 0 400 120" preserveAspectRatio="none" class="line-chart__svg">
                      <polyline :points="waitTimePoints" fill="none" stroke="var(--qm-brand)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                      <circle v-for="(pt, i) in waitTimePointsArr" :key="i" :cx="pt.x" :cy="pt.y" r="3" fill="var(--qm-brand)" />
                    </svg>
                    <div class="line-chart__labels">
                      <span v-for="day in chartDailyData" :key="day.date">{{ day.label }}</span>
                    </div>
                  </div>
                </div>

                <!-- Hourly distribution -->
                <div class="section-header q-mb-md"><h3 class="section-title">Distribuição por Horário</h3></div>
                <div v-if="reportData.hourly_distribution.length === 0" class="empty-state-sm"><p>Sem dados</p></div>
                <div v-else class="hourly-chart">
                  <div v-for="h in formattedHourlyData" :key="h.hour" class="hourly-col">
                    <div class="hourly-bar" :style="{ height: h.percent + '%' }">
                      <span v-if="h.count > 0" class="hourly-count">{{ h.count }}</span>
                    </div>
                    <span class="hourly-lbl">{{ h.label }}</span>
                  </div>
                </div>

                <!-- Pie charts row -->
                <div class="pie-charts-row q-mt-xl">
                  <!-- Status distribution pie chart -->
                  <div class="pie-chart-block">
                    <div class="section-header q-mb-md"><h3 class="section-title">Distribuição por Status</h3></div>
                    <div v-if="statusPieData.length === 0" class="empty-state-sm"><p>Sem dados</p></div>
                    <div v-else class="pie-chart-wrapper">
                      <svg viewBox="0 0 200 200" class="pie-chart-svg">
                        <path v-for="(slice, i) in statusPieData" :key="i" :d="pieSlicePath(100, 100, 80, slice.startAngle, slice.endAngle)" :fill="slice.color" stroke="var(--qm-bg-primary, #fff)" stroke-width="2" />
                      </svg>
                      <div class="pie-legend">
                        <div v-for="(slice, i) in statusPieData" :key="i" class="pie-legend__item">
                          <span class="pie-legend__dot" :style="{ background: slice.color }"></span>
                          <span class="pie-legend__label">{{ slice.label }}</span>
                          <span class="pie-legend__value">{{ slice.value }} ({{ Math.round(slice.pct * 100) }}%)</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Priority distribution pie chart -->
                  <div class="pie-chart-block">
                    <div class="section-header q-mb-md"><h3 class="section-title">Distribuição por Prioridade</h3></div>
                    <div v-if="priorityPieData.length === 0" class="empty-state-sm"><p>Sem dados</p></div>
                    <div v-else class="pie-chart-wrapper">
                      <svg viewBox="0 0 200 200" class="pie-chart-svg">
                        <path v-for="(slice, i) in priorityPieData" :key="i" :d="pieSlicePath(100, 100, 80, slice.startAngle, slice.endAngle)" :fill="slice.color" stroke="var(--qm-bg-primary, #fff)" stroke-width="2" />
                      </svg>
                      <div class="pie-legend">
                        <div v-for="(slice, i) in priorityPieData" :key="i" class="pie-legend__item">
                          <span class="pie-legend__dot" :style="{ background: slice.color }"></span>
                          <span class="pie-legend__label">{{ slice.label }}</span>
                          <span class="pie-legend__value">{{ slice.value }} ({{ Math.round(slice.pct * 100) }}%)</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <div v-else class="empty-state-sm">
                <q-icon name="analytics" size="48px" />
                <p>Clique em atualizar para carregar relatórios</p>
              </div>
            </q-tab-panel>

          </q-tab-panels>
        </div>
      </template>
    </template>

    <!-- =============== CONTEXT MENUS =============== -->

    <ContextMenu v-model="entryMenuOpen" :items="entryMenuItems" :position="entryMenuPos" :title="entryMenuEntry?.user_name" :subtitle="entryMenuSubtitle" @select="onEntryMenuSelect" />
    <ContextMenu v-model="statusMenuOpen" :items="statusMenuItems" :position="statusMenuPos" title="Status da Fila" :subtitle="statusLabel" @select="onStatusMenuSelect" />
    <ContextMenu v-model="codeMenuOpen" :items="codeMenuItems" :position="codeMenuPos" :title="codeMenuTarget?.code" :subtitle="codeMenuSubtitle" @select="onCodeMenuSelect" />
    <ContextMenu v-model="profMenuOpen" :items="profMenuItems" :position="profMenuPos" :title="profMenuTarget?.user_name" @select="onProfMenuSelect" />
    <ContextMenu v-model="serviceMenuOpen" :items="serviceMenuItems" :position="serviceMenuPos" :title="serviceMenuTarget?.name" :subtitle="serviceMenuSubtitle" @select="onServiceMenuSelect" />

    <UserProfilePreview v-model="profilePreviewOpen" :user-id="profilePreviewUserId" :position="profilePreviewPos" />

    <!-- =============== DIALOGS =============== -->

    <q-dialog v-model="showRemoveEntryDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Remover da Fila</div>
          <q-btn flat round dense icon="close" @click="closeRemoveEntryDialog" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted">
            Tem certeza que deseja remover {{ removeEntryTarget?.name || 'esta pessoa' }} da fila?
          </p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps :disable="removeEntryDialogLoading" @click="closeRemoveEntryDialog" />
          <q-btn color="negative" label="Remover" no-caps :loading="removeEntryDialogLoading" @click="removeEntryFromDialog" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showBatchRemoveDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Remover Selecionados</div>
          <q-btn flat round dense icon="close" @click="closeBatchRemoveDialog" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted">
            Remover {{ batchRemoveTargetIds.length }} pessoa(s) da fila?
          </p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps :disable="batchRemoving" @click="closeBatchRemoveDialog" />
          <q-btn color="negative" label="Remover" no-caps :loading="batchRemoving" @click="confirmBatchRemoveSelected" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showUnlinkServiceDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Desvincular Serviço</div>
          <q-btn flat round dense icon="close" @click="closeUnlinkServiceDialog" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted">
            Desvincular "{{ serviceActionTarget?.name }}" apenas desta fila? O cadastro do serviço será mantido no estabelecimento.
          </p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps :disable="unlinkingService" @click="closeUnlinkServiceDialog" />
          <q-btn color="warning" label="Desvincular" no-caps :loading="unlinkingService" @click="confirmUnlinkQueueService" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showDeleteServiceDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Excluir Serviço</div>
          <q-btn flat round dense icon="close" @click="closeDeleteServiceDialog" />
        </q-card-section>
        <q-card-section>
          <div v-if="serviceActionLoading" class="loading-state-sm">
            <q-spinner-dots color="primary" size="24px" />
          </div>
          <template v-else>
            <p class="text-muted q-mb-sm">
              Excluir "{{ serviceActionTarget?.name }}" do estabelecimento?
            </p>
            <p class="text-muted">
              {{ serviceDeleteImpactLabel }}
            </p>
          </template>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps :disable="deletingSvc" @click="closeDeleteServiceDialog" />
          <q-btn color="negative" label="Excluir" no-caps :disable="serviceActionLoading" :loading="deletingSvc" @click="confirmDeleteService" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showDeleteCodeDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Excluir Código</div>
          <q-btn flat round dense icon="close" @click="closeDeleteCodeDialog" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted">
            Tem certeza que deseja excluir o código {{ codeDeleteTarget?.code || 'selecionado' }}?
          </p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps :disable="deletingCode" @click="closeDeleteCodeDialog" />
          <q-btn color="negative" label="Excluir" no-caps :loading="deletingCode" @click="confirmDeleteCode" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showRemoveProfessionalDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Remover Profissional</div>
          <q-btn flat round dense icon="close" @click="closeRemoveProfessionalDialog" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted">
            Tem certeza que deseja remover {{ professionalRemoveTarget?.user_name || 'este profissional' }} desta fila?
          </p>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps :disable="removingProfessional" @click="closeRemoveProfessionalDialog" />
          <q-btn color="negative" label="Remover" no-caps :loading="removingProfessional" @click="confirmRemoveProfessional" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Notes dialog (no_show etc.) -->
    <q-dialog v-model="showNotesDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">{{ notesTitle }}</div>
          <q-btn flat round dense icon="close" @click="showNotesDialog = false" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted q-mb-md">{{ notesDescription }}</p>
          <q-input v-model="notesText" label="Observação (opcional)" outlined dense type="textarea" autogrow />
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showNotesDialog = false" />
          <q-btn :color="notesColor" :label="notesActionLabel" no-caps :loading="updatingStatus" @click="confirmNotesAction" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Create / Edit Code -->
    <q-dialog v-model="showCodeDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">{{ isEditingCode ? 'Editar Código' : 'Novo Código de Acesso' }}</div>
          <q-btn flat round dense icon="close" @click="showCodeDialog = false" />
        </q-card-section>
        <q-card-section>
          <div v-if="!isEditingCode" class="code-type-picker q-mb-md">
            <q-btn-toggle v-model="codeForm.type" no-caps spread rounded toggle-color="primary" :options="[{ label: 'Com Expiração', value: 'timed' },{ label: 'Com Limite de Uso', value: 'limited' },{ label: 'Ilimitado', value: 'unlimited' }]" />
          </div>
          <q-input v-if="codeForm.type === 'timed' || isEditingCode" v-model="codeForm.expires_at" label="Data/Hora de Expiração" outlined dense type="datetime-local" class="q-mb-md" :hint="isEditingCode ? 'Vazio = sem expiração' : ''" />
          <q-input v-if="codeForm.type === 'limited' || isEditingCode" v-model.number="codeForm.max_uses" label="Limite de Usos" outlined dense type="number" class="q-mb-md" :hint="isEditingCode ? 'Vazio = ilimitado' : ''" />
          <q-toggle v-if="isEditingCode" v-model="codeForm.is_active" label="Código Ativo" class="q-mb-md" />
          <div v-if="isEditingCode && codeForm.code" class="code-preview q-mb-md">
            <span class="code-preview-label">Código:</span>
            <span class="code-preview-value">{{ codeForm.code }}</span>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showCodeDialog = false" />
          <q-btn color="primary" :label="isEditingCode ? 'Salvar' : 'Gerar Código'" no-caps :loading="savingCode" @click="saveCode" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Generated Code Display -->
    <q-dialog v-model="showGeneratedCodeDialog">
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Código Gerado</div>
          <q-btn flat round dense icon="close" @click="showGeneratedCodeDialog = false" />
        </q-card-section>
        <q-card-section class="text-center" style="padding: 2rem;">
          <p class="text-muted q-mb-md" style="font-size:0.875rem;">Compartilhe este código para que clientes entrem na fila:</p>
          <div class="access-code-display"><span class="access-code-text">{{ generatedCodeValue }}</span></div>
          <q-btn outline color="primary" icon="content_copy" label="Copiar Código" no-caps class="q-mt-md" @click="copyToClipboard(generatedCodeValue)" />
          <p v-if="generatedCodeExpiry" class="text-muted q-mt-md" style="font-size:0.75rem;">Expira em: {{ generatedCodeExpiry }}</p>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showQrCodeDialog">
      <q-card class="dialog-card qr-code-dialog">
        <q-card-section class="dialog-header">
          <div class="text-h6">QR Code do código</div>
          <q-btn flat round dense icon="close" :disable="exportingQr" @click="closeQrCodeDialog" />
        </q-card-section>
        <q-card-section class="q-gutter-md">
          <div class="qr-code-preview">
            <div v-if="qrCodeLoading" class="loading-state-sm">
              <q-spinner-dots color="primary" size="32px" />
            </div>
            <div v-else class="qr-code-frame">
              <img v-if="qrCodeImage" :src="qrCodeImage" alt="QR Code do código de acesso" class="qr-code-image" />
            </div>
          </div>

          <div v-if="qrCodeTarget" class="qr-code-meta">
            <div class="code-preview">
              <span class="code-preview-label">Código:</span>
              <span class="code-preview-value">{{ qrCodeTarget.code }}</span>
            </div>
            <div class="qr-code-link">{{ qrCodeLink }}</div>
            <p v-if="qrCodeTarget.expires_at" class="text-muted q-mb-none">
              Expira em: {{ formatDate(qrCodeTarget.expires_at) }}
            </p>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Fechar" no-caps :disable="exportingQr" @click="closeQrCodeDialog" />
          <q-btn-dropdown
            color="primary"
            icon="download"
            label="Exportar"
            no-caps
            :loading="exportingQr"
            :disable="qrCodeLoading || !qrCodeImage"
          >
            <q-list dense>
              <q-item clickable v-close-popup @click="exportQrCode('png')">
                <q-item-section avatar><q-icon name="image" /></q-item-section>
                <q-item-section>
                  <q-item-label>Imagem PNG</q-item-label>
                  <q-item-label caption>Ideal para totem, arte ou compartilhamento</q-item-label>
                </q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="exportQrCode('pdf')">
                <q-item-section avatar><q-icon name="picture_as_pdf" /></q-item-section>
                <q-item-section>
                  <q-item-label>Arquivo PDF</q-item-label>
                  <q-item-label caption>Melhor para impressão e distribuição</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Person Dialog -->
    <q-dialog v-model="showAddPersonDialog" persistent>
      <q-card class="dialog-card add-person-dialog">
        <q-card-section class="dialog-header">
          <div class="text-h6">Adicionar Pessoa na Fila</div>
          <q-btn flat round dense icon="close" @click="closeAddPersonDialog" />
        </q-card-section>
        <q-card-section>
          <div class="add-person-search">
            <q-input v-model="searchEmail" outlined dense placeholder="Pesquisar por e-mail..." class="add-person-search__input" @keyup.enter="searchUserByEmail">
              <template v-slot:prepend><q-icon name="email" /></template>
              <template v-slot:append><q-btn flat round dense icon="search" @click="searchUserByEmail" :loading="searchingUser" /></template>
            </q-input>
            <span v-if="searchEmailError" class="add-person-search__error">{{ searchEmailError }}</span>
          </div>
          <div v-if="foundUser" class="add-person-profile">
            <div class="add-person-profile__header">
              <div class="add-person-profile__avatar"><span>{{ foundUser.name?.charAt(0)?.toUpperCase() || '?' }}</span></div>
              <div class="add-person-profile__identity">
                <span class="add-person-profile__name">{{ foundUser.name }}</span>
                <span class="add-person-profile__email">{{ foundUser.email }}</span>
              </div>
            </div>
            <div class="add-person-profile__details">
              <div v-if="foundUser.role" class="add-person-profile__row"><q-icon name="badge" size="16px" /><span>{{ { admin: 'Administrador', manager: 'Gerente', professional: 'Profissional', user: 'Usuário', client: 'Cliente' }[foundUser.role] || foundUser.role }}</span></div>
              <div v-if="foundUser.phone" class="add-person-profile__row"><q-icon name="phone" size="16px" /><span>{{ foundUser.phone }}</span></div>
            </div>
            <q-separator class="q-my-md" />
            <div class="add-person-field">
              <label class="add-person-field__label">Status na fila</label>
              <q-btn-toggle v-model="addToStatus" no-caps spread rounded dense toggle-color="primary" :options="addToStatusOptions" class="add-person-field__toggle" />
            </div>
            <div class="add-person-field">
              <label class="add-person-field__label">Prioridade</label>
              <q-toggle v-model="addPersonPriority" label="Atendimento prioritário" dense />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeAddPersonDialog" />
          <q-btn color="primary" icon="person_add" label="Adicionar" no-caps :disable="!foundUser" :loading="addingPerson" @click="addPersonFromDialog" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Professional Dialog -->
    <q-dialog v-model="showAddProfDialog" persistent>
      <q-card class="dialog-card">
        <q-card-section class="dialog-header">
          <div class="text-h6">Adicionar Profissional</div>
          <q-btn flat round dense icon="close" @click="showAddProfDialog = false" />
        </q-card-section>
        <q-card-section>
          <p class="text-muted q-mb-md">Selecione um profissional do estabelecimento ou do negócio. Se ele ainda não estiver neste estabelecimento, o vínculo será criado automaticamente ao confirmar.</p>
          <div v-if="availableEstProfessionals.length === 0" class="empty-state-sm"><p>Nenhum profissional disponível</p></div>
          <div v-else class="list-items">
            <div
              v-for="ep in availableEstProfessionals"
              :key="ep.user_id"
              class="list-item list-item--selectable"
              :class="{ 'list-item--active': selectedEstProf === ep.user_id }"
              @click="selectedEstProf = ep.user_id"
            >
              <div class="list-item-info">
                <div class="list-item-avatar"><q-icon name="person" size="18px" /></div>
                <div class="list-item-details">
                  <span class="list-item-name">{{ ep.user_name }}</span>
                  <span class="list-item-meta">
                    {{ ep.user_email }}
                    <template v-if="ep.is_establishment_linked"> · já vinculado ao estabelecimento</template>
                    <template v-else> · será vinculado ao estabelecimento</template>
                  </span>
                </div>
              </div>
              <q-icon v-if="selectedEstProf === ep.user_id" name="check_circle" color="primary" size="20px" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showAddProfDialog = false" />
          <q-btn color="primary" label="Adicionar" no-caps :disable="!selectedEstProf" :loading="addingProf" @click="addSelectedProfessional" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add Service Dialog -->
    <q-dialog v-model="showAddServiceDialog" persistent>
      <q-card class="dialog-card" style="min-width: 460px;">
        <q-card-section class="dialog-header">
          <div class="text-h6">Adicionar Serviço</div>
          <q-btn flat round dense icon="close" @click="showAddServiceDialog = false" />
        </q-card-section>
        <q-card-section>
          <!-- Mode toggle -->
          <q-btn-toggle
            v-model="svcDialogMode"
            no-caps spread rounded dense
            toggle-color="primary"
            :options="[
              { label: 'Selecionar Existente', value: 'select' },
              { label: 'Criar Novo', value: 'create' },
            ]"
            class="q-mb-lg"
          />

          <!-- Select existing service -->
          <template v-if="svcDialogMode === 'select'">
            <div v-if="availableEstServices.length === 0" class="empty-state-sm">
              <q-icon name="check_circle" size="36px" />
              <p>Todos os serviços já estão vinculados</p>
            </div>
            <div v-else class="list-items" style="max-height: 320px; overflow-y: auto;">
              <div
                v-for="svc in availableEstServices"
                :key="svc.id"
                class="list-item list-item--selectable"
                :class="{ 'list-item--active': selectedServiceIds.includes(svc.id) }"
                @click="toggleServiceSelection(svc.id)"
              >
                <div class="list-item-info">
                  <q-checkbox
                    :model-value="selectedServiceIds.includes(svc.id)"
                    @update:model-value="toggleServiceSelection(svc.id)"
                    color="primary"
                    size="sm"
                    dense
                    class="q-mr-sm"
                    @click.stop
                  />
                  <div class="list-item-avatar"><q-icon :name="svc.icon || 'work_outline'" size="18px" /></div>
                  <div class="list-item-details">
                    <span class="list-item-name">{{ svc.name }}</span>
                    <span class="list-item-meta">
                      {{ svc.duration_minutes }} min
                      <template v-if="svc.price"> · R$ {{ Number(svc.price).toFixed(2) }}</template>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="selectedServiceIds.length > 0" class="text-caption text-grey-6 q-mt-sm">
              {{ selectedServiceIds.length }} serviço(s) selecionado(s)
            </div>
          </template>

          <!-- Create new service -->
          <template v-else>
            <q-input v-model="svcForm.name" label="Nome do Serviço *" outlined dense class="q-mb-md" />
            <q-input v-model="svcForm.description" label="Descrição" outlined dense type="textarea" autogrow class="q-mb-md" />
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-6"><q-input v-model.number="svcForm.duration" label="Duração (min) *" outlined dense type="number" /></div>
              <div class="col-6"><q-input v-model.number="svcForm.price" label="Preço (R$)" outlined dense type="number" step="0.01" /></div>
            </div>
            <q-input v-model="svcForm.icon" label="Icone (Material Icon)" outlined dense class="q-mb-md" hint="Ex: medical_services, cut, spa">
              <template v-slot:prepend><q-icon :name="svcForm.icon || 'work_outline'" size="20px" /></template>
            </q-input>
          </template>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="showAddServiceDialog = false" />
          <q-btn
            color="primary"
            :label="svcDialogMode === 'select' ? `Adicionar (${selectedServiceIds.length})` : 'Criar e Adicionar'"
            no-caps
            :disable="svcDialogMode === 'select' ? selectedServiceIds.length === 0 : !svcForm.name"
            :loading="savingSvc"
            @click="saveServiceAction"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showEditServiceDialog" persistent>
      <q-card class="dialog-card" style="min-width: 460px;">
        <q-card-section class="dialog-header">
          <div class="text-h6">Editar Serviço</div>
          <q-btn flat round dense icon="close" @click="closeEditServiceDialog" />
        </q-card-section>
        <q-card-section>
          <div v-if="serviceDialogLoading" class="loading-state-sm">
            <q-spinner-dots color="primary" size="24px" />
          </div>
          <template v-else>
            <q-input v-model="editServiceForm.name" label="Nome do Serviço *" outlined dense class="q-mb-md" />
            <q-input v-model="editServiceForm.description" label="Descrição" outlined dense type="textarea" autogrow class="q-mb-md" />
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-6"><q-input v-model.number="editServiceForm.duration" label="Duração (min) *" outlined dense type="number" /></div>
              <div class="col-6"><q-input v-model.number="editServiceForm.price" label="Preço (R$)" outlined dense type="number" step="0.01" /></div>
            </div>
            <q-input v-model="editServiceForm.icon" label="Icone" outlined dense class="q-mb-md">
              <template v-slot:prepend><q-icon :name="editServiceForm.icon || 'work_outline'" size="20px" /></template>
            </q-input>

            <div class="service-usage-box">
              <span class="detail-label">Uso atual</span>
              <span class="detail-value">{{ serviceUsageLabel }}</span>
              <div v-if="serviceUsageQueues.length" class="service-usage-list">
                <span v-for="item in serviceUsageQueues" :key="`${item.queue_id}-${item.queue_name}`" class="service-usage-chip">
                  {{ item.queue_name }}
                </span>
              </div>
            </div>
          </template>
        </q-card-section>
        <q-card-actions align="right" class="dialog-actions">
          <q-btn flat label="Cancelar" no-caps @click="closeEditServiceDialog" />
          <q-btn color="primary" label="Salvar" no-caps :disable="serviceDialogLoading" :loading="savingServiceEdit" @click="saveServiceEdit" />
        </q-card-actions>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from 'boot/axios'
import { useQuasar } from 'quasar'
import { jsPDF } from 'jspdf'
import QRCode from 'qrcode'
import ContextMenu from 'src/components/ui/ContextMenu.vue'
import StatusPill from 'src/components/ui/StatusPill.vue'
import UserProfilePreview from 'src/components/ui/UserProfilePreview.vue'
import { useBreadcrumb } from 'src/composables/useBreadcrumb'

export default defineComponent({
  name: 'QueueDetailPage',
  components: { ContextMenu, StatusPill, UserProfilePreview },

  setup() {
    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()
    const { setDetail, clearDetail } = useBreadcrumb()
    const queueId = computed(() => route.params.id)

    // -- Core state --
    const queue = ref(null)
    const waitingEntries = ref([])
    const servingEntries = ref([])
    const completedEntries = ref([])
    const statistics = ref(null)
    const userEntry = ref(null)
    const loading = ref(true)
    const userRole = ref(null)
    const currentUserId = ref(null)
    const lastFetchToken = ref(0)
    const removingEntryIds = ref([])
    const showRemoveEntryDialog = ref(false)
    const removeEntryTarget = ref(null)
    const showBatchRemoveDialog = ref(false)
    const batchRemoveTargetIds = ref([])

    const mainTab = ref('flow')
    const flowTab = ref('waiting')

    // Loading flags
    const saving = ref(false)
    const callingNext = ref(false)
    const addingPerson = ref(false)
    const joining = ref(false)
    const updatingStatus = ref(false)
    const batchRemoving = ref(false)
    const movingEntries = ref(false)
    const codesLoading = ref(false)
    const reportsLoading = ref(false)
    const savingCode = ref(false)
    const searchingUser = ref(false)
    const qpLoading = ref(false)
    const addingProf = ref(false)
    const svcLoading = ref(false)

    // Computed helpers
    const isStaffRole = computed(() => ['admin', 'manager', 'professional'].includes(userRole.value))
    const queuePermissionsResolved = computed(() => Boolean(queue.value && Object.prototype.hasOwnProperty.call(queue.value, 'permissions')))
    const canManage = computed(() => Boolean(isStaffRole.value && queue.value?.permissions?.can_manage))
    const isRegularUser = computed(() => {
      if (!isStaffRole.value) return true
      if (!queuePermissionsResolved.value) return false
      return !canManage.value
    })

    const statusVariant = computed(() => {
      if (!queue.value) return 'grey'
      return { open: 'positive', closed: 'grey', paused: 'warning' }[queue.value.status] || 'grey'
    })
    const statusLabel = computed(() => {
      if (!queue.value) return ''
      return { open: 'Aberta', closed: 'Fechada', paused: 'Pausada' }[queue.value.status] || queue.value.status
    })

    const statusOptions = [
      { label: 'Aberta', value: 'open' },
      { label: 'Fechada', value: 'closed' },
      { label: 'Pausada', value: 'paused' },
    ]

    // -- Selection --
    const selectedWaiting = ref([])
    const selectedServing = ref([])

    const sortedWaitingEntries = computed(() => {
      return [...waitingEntries.value].sort((a, b) => {
        const pa = Number(a.priority) || 0
        const pb = Number(b.priority) || 0
        if (pb !== pa) return pb - pa
        return (Number(a.position) || 0) - (Number(b.position) || 0)
      })
    })

    const hasWaitingSelection = computed(() => selectedWaiting.value.length > 0)
    const hasServingSelection = computed(() => selectedServing.value.length > 0)
    const hasAnySelection = computed(() => hasWaitingSelection.value || hasServingSelection.value)
    const totalSelected = computed(() => selectedWaiting.value.length + selectedServing.value.length)
    const removeEntryDialogLoading = computed(() => (
      removeEntryTarget.value ? isEntryRemoving(removeEntryTarget.value.id) : false
    ))

    const toggleSelect = (id, type) => {
      const arr = type === 'waiting' ? selectedWaiting : selectedServing
      const idx = arr.value.indexOf(id)
      if (idx >= 0) arr.value.splice(idx, 1)
      else arr.value.push(id)
    }
    const clearSelection = () => { selectedWaiting.value = []; selectedServing.value = [] }
    const isEntryRemoving = (entryId) => removingEntryIds.value.includes(Number(entryId))
    const closeRemoveEntryDialog = (force = false) => {
      if (!force && removeEntryDialogLoading.value) return
      showRemoveEntryDialog.value = false
      removeEntryTarget.value = null
    }
    const closeBatchRemoveDialog = (force = false) => {
      if (!force && batchRemoving.value) return
      showBatchRemoveDialog.value = false
      batchRemoveTargetIds.value = []
    }
    const setRemovingEntries = (entryIds, shouldMark) => {
      const ids = Array.isArray(entryIds) ? entryIds.map(id => Number(id)).filter(id => Number.isFinite(id)) : []
      if (!ids.length) return

      const next = new Set(removingEntryIds.value.map(id => Number(id)))
      ids.forEach((id) => {
        if (shouldMark) next.add(id)
        else next.delete(id)
      })

      removingEntryIds.value = Array.from(next)
    }

    const removeEntriesFromLocalState = (entryIds = []) => {
      const ids = new Set(entryIds.map(id => Number(id)).filter(id => Number.isFinite(id)))
      if (!ids.size) return

      const removedWaiting = waitingEntries.value.filter(entry => ids.has(Number(entry.id)))
      const removedServing = servingEntries.value.filter(entry => ids.has(Number(entry.id)))

      waitingEntries.value = waitingEntries.value.filter(entry => !ids.has(Number(entry.id)))
      servingEntries.value = servingEntries.value.filter(entry => !ids.has(Number(entry.id)))
      completedEntries.value = completedEntries.value.filter(entry => !ids.has(Number(entry.id)))

      if (userEntry.value && ids.has(Number(userEntry.value.entry_id || userEntry.value.id))) {
        userEntry.value = null
      }

      if (statistics.value) {
        statistics.value = {
          ...statistics.value,
          total_waiting: Math.max(0, Number(statistics.value.total_waiting || 0) - removedWaiting.length),
          total_being_served: Math.max(0, Number(statistics.value.total_being_served || 0) - removedServing.length),
        }
      }

      if (queue.value) {
        queue.value = {
          ...queue.value,
          waiting_count: Math.max(0, Number(queue.value.waiting_count || 0) - removedWaiting.length),
        }
      }
    }

    // -- Completed filter --
    const completedPeriod = ref('today')
    const completedFrom = ref('')
    const completedTo = ref('')

    const onCompletedPeriodChange = (val) => {
      if (val === 'today') {
        completedFrom.value = ''
        completedTo.value = ''
        fetchData()
      }
    }

    // -- Add person dialog --
    const showAddPersonDialog = ref(false)
    const searchEmail = ref('')
    const searchEmailError = ref('')
    const foundUser = ref(null)
    const addToStatus = ref('waiting')
    const addPersonPriority = ref(false)
    const addToStatusOptions = [
      { label: 'Aguardando', value: 'waiting' },
      { label: 'Em Atendimento', value: 'serving' },
    ]

    const openAddPersonDialog = () => {
      searchEmail.value = ''
      searchEmailError.value = ''
      foundUser.value = null
      addToStatus.value = flowTab.value === 'serving' ? 'serving' : 'waiting'
      addPersonPriority.value = false
      showAddPersonDialog.value = true
    }
    const closeAddPersonDialog = () => { showAddPersonDialog.value = false; foundUser.value = null; searchEmail.value = ''; searchEmailError.value = '' }

    const searchUserByEmail = async () => {
      const email = searchEmail.value.trim()
      if (!email) return
      searchingUser.value = true
      searchEmailError.value = ''
      foundUser.value = null
      try {
        const { data } = await api.get('/users', { params: { email } })
        const users = data.data?.users || data.data || []
        const match = users.find(u => u.email?.toLowerCase() === email.toLowerCase())
        if (match) foundUser.value = match
        else searchEmailError.value = 'Nenhum usuário encontrado com esse e-mail'
      } catch { searchEmailError.value = 'Erro ao buscar usuário' }
      finally { searchingUser.value = false }
    }

    const addPersonFromDialog = async () => {
      if (!foundUser.value) return
      addingPerson.value = true
      try {
        await api.post(`/queues/${queueId.value}/join`, {
          user_id: foundUser.value.id,
          priority: addPersonPriority.value ? 1 : 0,
          status: addToStatus.value,
        })
        $q.notify({ type: 'positive', message: `${foundUser.value.name} adicionado(a) a fila` })
        closeAddPersonDialog()
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao adicionar' })
      } finally { addingPerson.value = false }
    }

    // -- Info tab (editable) --
    const infoEditing = ref(false)
    const infoForm = ref({ name: '', status: 'open', description: '', max_capacity: null })

    const startInfoEdit = () => {
      infoForm.value = {
        name: queue.value?.name || '',
        status: queue.value?.status || 'open',
        description: queue.value?.description || '',
        max_capacity: queue.value?.max_capacity || null,
      }
      infoEditing.value = true
    }
    const cancelInfoEdit = () => { infoEditing.value = false }
    const saveInfoEdit = async () => {
      if (!infoForm.value.name) { $q.notify({ type: 'warning', message: 'Nome é obrigatório' }); return }
      saving.value = true
      try {
        await api.put(`/queues/${queueId.value}`, infoForm.value)
        $q.notify({ type: 'positive', message: 'Fila atualizada' })
        infoEditing.value = false
        fetchData()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar' })
      } finally { saving.value = false }
    }

    // -- Professionals tab --
    const queueProfessionals = ref([])
    const estProfessionals = ref([])
    const showAddProfDialog = ref(false)
    const selectedEstProf = ref(null)
    const showRemoveProfessionalDialog = ref(false)
    const professionalRemoveTarget = ref(null)
    const removingProfessional = ref(false)

    const availableEstProfessionals = computed(() => {
      const assignedIds = queueProfessionals.value.map(qp => qp.user_id)
      return estProfessionals.value.filter(ep => !assignedIds.includes(ep.user_id))
    })

    const fetchQueueProfessionals = async () => {
      qpLoading.value = true
      try {
        const { data } = await api.get(`/queues/${queueId.value}/queue-professionals`)
        if (data?.success) queueProfessionals.value = data.data?.professionals || []
      } catch { /* ignore */ }
      finally { qpLoading.value = false }
    }

    const fetchEstProfessionals = async () => {
      try {
        const { data } = await api.get(`/queues/${queueId.value}/professionals`)
        if (data?.success) estProfessionals.value = data.data?.professionals || []
      } catch { /* ignore */ }
    }

    const openAddProfDialog = async () => {
      selectedEstProf.value = null
      await fetchEstProfessionals()
      showAddProfDialog.value = true
    }

    const addSelectedProfessional = async () => {
      if (!selectedEstProf.value) return
      addingProf.value = true
      try {
        await api.post(`/queues/${queueId.value}/queue-professionals`, { user_id: selectedEstProf.value })
        $q.notify({ type: 'positive', message: 'Profissional adicionado' })
        showAddProfDialog.value = false
        await Promise.all([fetchQueueProfessionals(), fetchEstProfessionals()])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro' })
      } finally { addingProf.value = false }
    }

    const selfAddProfessional = async () => {
      addingProf.value = true
      try {
        await api.post(`/queues/${queueId.value}/queue-professionals`, {})
        $q.notify({ type: 'positive', message: 'Você foi adicionado à fila como profissional' })
        await Promise.all([fetchQueueProfessionals(), fetchEstProfessionals()])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro' })
      } finally { addingProf.value = false }
    }

    const toggleProfActive = async (prof) => {
      try {
        await api.put(`/queues/${queueId.value}/queue-professionals/${prof.id}`, { is_active: !prof.is_active })
        $q.notify({ type: 'positive', message: prof.is_active ? 'Profissional desativado' : 'Profissional ativado' })
        fetchQueueProfessionals()
      } catch { $q.notify({ type: 'negative', message: 'Erro ao atualizar' }) }
    }

    const closeRemoveProfessionalDialog = (force = false) => {
      if (!force && removingProfessional.value) return
      showRemoveProfessionalDialog.value = false
      professionalRemoveTarget.value = null
    }

    const removeProfessional = (prof) => {
      profMenuOpen.value = false
      professionalRemoveTarget.value = prof
      showRemoveProfessionalDialog.value = true
    }

    const confirmRemoveProfessional = async () => {
      if (!professionalRemoveTarget.value) return
      removingProfessional.value = true
      try {
        await api.delete(`/queues/${queueId.value}/queue-professionals/${professionalRemoveTarget.value.id}`)
        $q.notify({ type: 'positive', message: 'Profissional removido' })
        closeRemoveProfessionalDialog(true)
        await Promise.all([fetchQueueProfessionals(), fetchEstProfessionals()])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao remover' })
      } finally {
        removingProfessional.value = false
      }
    }

    // Prof context menu
    const profMenuOpen = ref(false)
    const profMenuPos = ref({ x: 0, y: 0 })
    const profMenuTarget = ref(null)

    const profMenuItems = computed(() => {
      if (!profMenuTarget.value) return []
      const p = profMenuTarget.value
      const items = []

      // Self-remove for professionals
      const isSelf = currentUserId.value && (p.user_id == currentUserId.value)
      const isManagerOrAdmin = ['admin', 'manager'].includes(userRole.value)

      items.push(
        p.is_active
          ? { key: 'deactivate', icon: 'pause_circle', label: 'Desativar', action: () => toggleProfActive(p) }
          : { key: 'activate', icon: 'play_circle', label: 'Ativar', action: () => toggleProfActive(p) }
      )

      if (isManagerOrAdmin || isSelf) {
        items.push({ separator: true })
        items.push({ key: 'remove', icon: 'person_remove', label: 'Remover da fila', danger: true, action: () => removeProfessional(p) })
      }

      return items
    })

    const openProfMenu = (event, prof) => {
      if (!canManage.value && !(userRole.value === 'professional' && prof.user_id == currentUserId.value)) return
      profMenuTarget.value = prof
      profMenuPos.value = { x: event.clientX, y: event.clientY }
      profMenuOpen.value = true
    }
    const onProfMenuSelect = () => {}

    // -- Services tab --
    const queueServices = ref([])
    const establishmentServices = ref([])
    const showAddServiceDialog = ref(false)
    const showEditServiceDialog = ref(false)
    const svcDialogMode = ref('select')
    const selectedServiceIds = ref([])
    const svcForm = ref({ name: '', description: '', duration: 30, price: null, icon: '' })
    const savingSvc = ref(false)
    const serviceDialogLoading = ref(false)
    const savingServiceEdit = ref(false)
    const deletingSvc = ref(false)
    const unlinkingService = ref(false)
    const editingServiceId = ref(null)
    const editServiceForm = ref({ name: '', description: '', duration: 30, price: null, icon: '' })
    const loadedServiceUsage = ref(null)
    const serviceMenuOpen = ref(false)
    const serviceMenuPos = ref({ x: 0, y: 0 })
    const serviceMenuTarget = ref(null)
    const showUnlinkServiceDialog = ref(false)
    const showDeleteServiceDialog = ref(false)
    const serviceActionTarget = ref(null)
    const serviceActionLoading = ref(false)
    const serviceActionUsage = ref(null)

    const availableEstServices = computed(() => {
      const linkedIds = queueServices.value.map(qs => qs.service_id)
      return establishmentServices.value.filter(s => !linkedIds.includes(s.id))
    })
    const serviceUsageQueues = computed(() => loadedServiceUsage.value?.linked_queues || [])
    const serviceUsageLabel = computed(() => {
      const count = Number(loadedServiceUsage.value?.linked_queue_count || 0)
      if (!count) return 'Sem vínculos ativos em filas.'
      if (count === 1) return 'Vinculado a 1 fila.'
      return `Vinculado a ${count} filas.`
    })
    const serviceDeleteImpactLabel = computed(() => {
      const count = Number(serviceActionUsage.value?.linked_queue_count || 0)
      if (!count) return 'Ele não possui outros vínculos ativos em filas.'
      return `Ele está vinculado a ${count} fila(s) e será removido delas também.`
    })

    const fetchQueueServices = async () => {
      if (!queueId.value) return
      svcLoading.value = true
      try {
        const { data } = await api.get(`/queues/${queueId.value}/services`)
        if (data?.success) queueServices.value = data.data?.services || []
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar serviços da fila' })
      } finally {
        svcLoading.value = false
      }
    }

    const fetchEstServicesForDialog = async () => {
      if (!queue.value?.establishment_id) return
      try {
        const { data } = await api.get('/services', { params: { establishment_id: queue.value.establishment_id } })
        if (data?.success) establishmentServices.value = data.data?.services || []
      } catch { /* ignore */ }
    }

    const refreshServiceState = async () => {
      await Promise.all([
        fetchQueueServices(),
        fetchEstServicesForDialog(),
        fetchData(),
      ])
    }

    const openAddServiceDialog = () => {
      svcDialogMode.value = 'select'
      selectedServiceIds.value = []
      svcForm.value = { name: '', description: '', duration: 30, price: null, icon: '' }
      fetchEstServicesForDialog()
      showAddServiceDialog.value = true
    }

    const closeEditServiceDialog = () => {
      showEditServiceDialog.value = false
      editingServiceId.value = null
      loadedServiceUsage.value = null
      editServiceForm.value = { name: '', description: '', duration: 30, price: null, icon: '' }
    }
    const closeUnlinkServiceDialog = (force = false) => {
      if (!force && unlinkingService.value) return
      showUnlinkServiceDialog.value = false
      serviceActionTarget.value = null
    }
    const closeDeleteServiceDialog = (force = false) => {
      if (!force && deletingSvc.value) return
      showDeleteServiceDialog.value = false
      serviceActionTarget.value = null
      serviceActionUsage.value = null
      serviceActionLoading.value = false
    }

    const toggleServiceSelection = (id) => {
      const idx = selectedServiceIds.value.indexOf(id)
      if (idx === -1) {
        selectedServiceIds.value.push(id)
      } else {
        selectedServiceIds.value.splice(idx, 1)
      }
    }

    const saveServiceAction = async () => {
      savingSvc.value = true
      try {
        if (svcDialogMode.value === 'select') {
          // Batch link existing
          await api.post(`/queues/${queueId.value}/services`, { service_ids: selectedServiceIds.value.map(Number) })
          $q.notify({ type: 'positive', message: `${selectedServiceIds.value.length} servico(s) adicionado(s) a fila` })
        } else {
          // Create new + link
          if (!svcForm.value.name || !svcForm.value.duration) {
            $q.notify({ type: 'warning', message: 'Nome e duração são obrigatórios' })
            savingSvc.value = false
            return
          }
          await api.post(`/queues/${queueId.value}/services`, {
            create_new: true,
            establishment_id: queue.value?.establishment_id,
            name: svcForm.value.name,
            description: svcForm.value.description,
            duration_minutes: svcForm.value.duration,
            price: svcForm.value.price,
            icon: svcForm.value.icon || null,
          })
          $q.notify({ type: 'positive', message: 'Serviço criado e adicionado à fila' })
        }
        showAddServiceDialog.value = false
        await refreshServiceState()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao adicionar serviço' })
      } finally { savingSvc.value = false }
    }

    const openEditServiceDialog = async (svc) => {
      showEditServiceDialog.value = true
      editingServiceId.value = svc.service_id
      serviceDialogLoading.value = true
      loadedServiceUsage.value = null

      try {
        const { data } = await api.get(`/services/${svc.service_id}`)
        const service = data?.data?.service || null
        if (!service) throw new Error('SERVICE_NOT_FOUND')

        editServiceForm.value = {
          name: service.name || '',
          description: service.description || '',
          duration: service.duration_minutes || 30,
          price: service.price !== null && service.price !== undefined ? Number(service.price) : null,
          icon: service.icon || '',
        }
        loadedServiceUsage.value = service.usage || {
          linked_queue_count: service.linked_queue_count || 0,
          linked_queues: service.linked_queues || [],
        }
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar serviço' })
        closeEditServiceDialog()
      } finally {
        serviceDialogLoading.value = false
      }
    }

    const saveServiceEdit = async () => {
      if (!editingServiceId.value) return
      if (!editServiceForm.value.name || !editServiceForm.value.duration) {
        $q.notify({ type: 'warning', message: 'Nome e duração são obrigatórios' })
        return
      }

      savingServiceEdit.value = true
      try {
        await api.put(`/services/${editingServiceId.value}`, {
          name: editServiceForm.value.name,
          description: editServiceForm.value.description || null,
          duration_minutes: editServiceForm.value.duration,
          price: editServiceForm.value.price,
          icon: editServiceForm.value.icon || null,
          establishment_id: queue.value?.establishment_id,
        })
        $q.notify({ type: 'positive', message: 'Serviço atualizado' })
        closeEditServiceDialog()
        await refreshServiceState()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar serviço' })
      } finally {
        savingServiceEdit.value = false
      }
    }

    const serviceMenuSubtitle = computed(() => {
      if (!serviceMenuTarget.value) return ''
      const duration = Number(serviceMenuTarget.value.duration_minutes || 0)
      const price = serviceMenuTarget.value.price ? ` · R$ ${Number(serviceMenuTarget.value.price).toFixed(2)}` : ''
      return `${duration} min${price}`
    })

    const serviceMenuItems = computed(() => {
      if (!serviceMenuTarget.value) return []
      const svc = serviceMenuTarget.value
      return [
        { key: 'edit', icon: 'edit', label: 'Editar serviço', action: () => openEditServiceDialog(svc) },
        { separator: true },
        { key: 'unlink', icon: 'link_off', label: 'Desvincular da fila', danger: true, action: () => unlinkQueueService(svc) },
        { key: 'delete', icon: 'delete', label: 'Excluir serviço', danger: true, action: () => promptDeleteService(svc) },
      ]
    })

    const openServiceMenu = (event, svc) => {
      if (!canManage.value) return
      serviceMenuTarget.value = svc
      serviceMenuPos.value = { x: event.clientX, y: event.clientY }
      serviceMenuOpen.value = true
    }
    const onServiceMenuSelect = () => {}

    const unlinkQueueService = (svc) => {
      serviceMenuOpen.value = false
      serviceActionTarget.value = svc
      showUnlinkServiceDialog.value = true
    }

    const confirmUnlinkQueueService = async () => {
      if (!serviceActionTarget.value) return
      unlinkingService.value = true
      try {
        await api.delete(`/queues/${queueId.value}/services/${serviceActionTarget.value.service_id}`)
        $q.notify({ type: 'positive', message: 'Serviço desvinculado da fila' })
        if (editingServiceId.value === serviceActionTarget.value.service_id) {
          closeEditServiceDialog()
        }
        closeUnlinkServiceDialog(true)
        await refreshServiceState()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao desvincular serviço' })
      } finally {
        unlinkingService.value = false
      }
    }

    const promptDeleteService = (svc) => {
      serviceMenuOpen.value = false
      serviceActionTarget.value = svc
      serviceActionUsage.value = null
      serviceActionLoading.value = true
      showDeleteServiceDialog.value = true
      api.get(`/services/${svc.service_id}`)
        .then(({ data }) => {
          const service = data?.data?.service || null
          serviceActionUsage.value = service?.usage || {
            linked_queue_count: service?.linked_queue_count || 0,
            linked_queues: service?.linked_queues || [],
          }
        })
        .catch((err) => {
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar serviço' })
          closeDeleteServiceDialog()
        })
        .finally(() => {
          serviceActionLoading.value = false
        })
    }

    const confirmDeleteService = async () => {
      if (!serviceActionTarget.value) return

      deletingSvc.value = true
      try {
        await api.delete(`/services/${serviceActionTarget.value.service_id}`)
        $q.notify({ type: 'positive', message: 'Serviço excluído' })
        if (editingServiceId.value === serviceActionTarget.value.service_id) {
          closeEditServiceDialog()
        }
        closeDeleteServiceDialog(true)
        await refreshServiceState()
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir serviço' })
      } finally {
        deletingSvc.value = false
      }
    }

    // -- Dialogs --
    const showNotesDialog = ref(false)
    const showCodeDialog = ref(false)
    const showGeneratedCodeDialog = ref(false)
    const showQrCodeDialog = ref(false)
    const showDeleteCodeDialog = ref(false)
    const codeDeleteTarget = ref(null)
    const deletingCode = ref(false)
    const qrCodeTarget = ref(null)
    const qrCodeImage = ref('')
    const qrCodeLink = ref('')
    const qrCodeLoading = ref(false)
    const exportingQr = ref(false)

    // Notes dialog
    const notesTitle = ref('')
    const notesDescription = ref('')
    const notesText = ref('')
    const notesColor = ref('primary')
    const notesActionLabel = ref('Confirmar')
    const notesCallback = ref(null)

    // Access codes
    const accessCodes = ref([])
    const accessCode = ref('')
    const isEditingCode = ref(false)
    const codeForm = ref({ type: 'timed', expires_at: '', max_uses: null, is_active: true, code: '' })
    const editingCodeId = ref(null)
    const generatedCodeValue = ref('')
    const generatedCodeExpiry = ref('')

    // Reports
    const reportPeriod = ref('7d')
    const reportData = ref(null)
    const periodOptions = [
      { label: 'Hoje', value: 'today' },
      { label: 'Últimos 7 dias', value: '7d' },
      { label: 'Últimos 30 dias', value: '30d' },
      { label: 'Últimos 90 dias', value: '90d' },
    ]

    // -- User profile preview --
    const profilePreviewOpen = ref(false)
    const profilePreviewUserId = ref(null)
    const profilePreviewPos = ref({ x: 0, y: 0 })

    const openProfilePreview = (entry, event) => {
      profilePreviewUserId.value = entry.user_id
      profilePreviewPos.value = { x: event.clientX + 10, y: event.clientY }
      profilePreviewOpen.value = true
    }

    // -- Context Menu: Entry --
    const entryMenuOpen = ref(false)
    const entryMenuPos = ref({ x: 0, y: 0 })
    const entryMenuEntry = ref(null)
    const entryMenuType = ref('waiting')
    const entryMenuEvent = ref(null)

    const entryMenuSubtitle = computed(() => {
      if (!entryMenuEntry.value) return ''
      const e = entryMenuEntry.value
      if (entryMenuType.value === 'waiting') return `Aguardando há ${formatWaitTime(e.waiting_since_minutes)}`
      if (entryMenuType.value === 'serving') return `Em atendimento há ${formatWaitTime(e.serving_since_minutes)}`
      return e.status === 'no_show' ? 'Não compareceu' : 'Concluído'
    })

    const priorityLabels = [
      { key: 'p0', label: 'Normal', value: 0, icon: 'remove' },
      { key: 'p1', label: 'Prioritário', value: 1, icon: 'priority_high' },
      { key: 'p2', label: 'Muito Prioritário', value: 2, icon: 'warning' },
    ]

    const entryMenuItems = computed(() => {
      if (!entryMenuEntry.value) return []
      const t = entryMenuType.value
      const entry = entryMenuEntry.value
      const id = entry.id

      const profileItem = {
        key: 'profile', icon: 'account_circle', label: 'Visualizar perfil',
        action: () => openProfilePreview(entryMenuEntry.value, entryMenuEvent.value),
      }

      if (t === 'waiting') {
        return [
          profileItem,
          { separator: true },
          { key: 'serve', icon: 'headset_mic', label: 'Atender agora', action: () => updateEntryStatus(id, 'serving') },
          { key: 'call', icon: 'campaign', label: 'Chamar', action: () => updateEntryStatus(id, 'called') },
          { separator: true },
          {
            key: 'move', icon: 'swap_vert', label: 'Mover',
            children: [
              { key: 'move_up', icon: 'arrow_upward', label: 'Para cima', action: () => moveEntry(id, 'up') },
              { key: 'move_down', icon: 'arrow_downward', label: 'Para baixo', action: () => moveEntry(id, 'down') },
            ],
          },
          {
            key: 'priority', icon: 'low_priority', label: 'Prioridade',
            children: priorityLabels.map(p => ({
              ...p,
              checked: Number(entry.priority) === p.value,
              action: () => changeEntryPriority(id, p.value),
            })),
          },
          { separator: true },
          { key: 'remove', icon: 'person_remove', label: 'Remover da fila', danger: true, disabled: isEntryRemoving(id), action: () => confirmRemoveEntry(id, entry.user_name) },
        ]
      }

      if (t === 'serving') {
        return [
          profileItem,
          { separator: true },
          { key: 'done', icon: 'check_circle', label: 'Concluir atendimento', action: () => updateEntryStatus(id, 'done') },
          { key: 'no_show', icon: 'person_off', label: 'Não compareceu', action: () => openNotesDialog(id, 'no_show') },
          { separator: true },
          { key: 'return', icon: 'undo', label: 'Retornar à fila', action: () => updateEntryStatus(id, 'waiting') },
          { separator: true },
          { key: 'remove', icon: 'cancel', label: 'Cancelar atendimento', danger: true, disabled: isEntryRemoving(id), action: () => confirmRemoveEntry(id, entry.user_name) },
        ]
      }

      if (t === 'completed') {
        return [
          profileItem,
          { separator: true },
          { key: 'requeue', icon: 'replay', label: 'Realocar para a fila', action: () => updateEntryStatus(id, 'waiting') },
          { key: 'serve_again', icon: 'headset_mic', label: 'Realocar p/ atendimento', action: () => updateEntryStatus(id, 'serving') },
        ]
      }
      return []
    })

    const openEntryMenu = (event, entry, type) => {
      if (!canManage.value) return
      entryMenuEntry.value = entry
      entryMenuType.value = type
      entryMenuEvent.value = event
      entryMenuPos.value = { x: event.clientX, y: event.clientY }
      entryMenuOpen.value = true
    }
    const onEntryMenuSelect = () => {}

    // -- Context Menu: Status --
    const statusMenuOpen = ref(false)
    const statusMenuPos = ref({ x: 0, y: 0 })

    const statusMenuItems = computed(() => {
      if (!queue.value) return []
      const s = queue.value.status
      const items = []
      if (s !== 'open') items.push({ key: 'open', icon: 'play_arrow', label: 'Abrir fila', action: () => changeQueueStatus('open') })
      if (s !== 'paused') items.push({ key: 'pause', icon: 'pause', label: 'Pausar fila', action: () => changeQueueStatus('paused') })
      if (s !== 'closed') { items.push({ separator: true }); items.push({ key: 'close', icon: 'stop', label: 'Fechar fila', danger: true, action: () => changeQueueStatus('closed') }) }
      return items
    })

    const openStatusMenu = (event) => {
      if (!canManage.value) return
      statusMenuPos.value = { x: event.clientX, y: event.clientY }
      statusMenuOpen.value = true
    }
    const onStatusMenuSelect = () => {}

    const changeQueueStatus = async (status) => {
      saving.value = true
      try {
        await api.put(`/queues/${queueId.value}`, { status })
        $q.notify({ type: 'positive', message: `Fila ${status === 'open' ? 'aberta' : status === 'paused' ? 'pausada' : 'fechada'}` })
        fetchData()
      } catch (err) { $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao alterar status' }) }
      finally { saving.value = false }
    }

    // -- Context Menu: Code --
    const codeMenuOpen = ref(false)
    const codeMenuPos = ref({ x: 0, y: 0 })
    const codeMenuTarget = ref(null)
    const codeMenuSubtitle = computed(() => {
      if (!codeMenuTarget.value) return ''
      return codeMenuTarget.value.is_active ? 'Código ativo' : 'Código inativo'
    })

    const codeMenuItems = computed(() => {
      if (!codeMenuTarget.value) return []
      const c = codeMenuTarget.value
      return [
        { key: 'copy', icon: 'content_copy', label: 'Copiar código', action: () => copyToClipboard(c.code) },
        { key: 'qr', icon: 'qr_code_2', label: 'Visualizar QR Code', action: () => openQrCodeDialog(c) },
        { key: 'edit', icon: 'edit', label: 'Editar', action: () => openEditCode(c) },
        { separator: true },
        c.is_active
          ? { key: 'deactivate', icon: 'block', label: 'Desativar', danger: true, action: () => toggleCodeActive(c, false) }
          : { key: 'activate', icon: 'check_circle', label: 'Ativar', action: () => toggleCodeActive(c, true) },
        { key: 'delete', icon: 'delete', label: 'Excluir', danger: true, action: () => deleteCode(c) },
      ]
    })

    const openCodeMenu = (event, code) => {
      codeMenuTarget.value = code
      codeMenuPos.value = { x: event.clientX, y: event.clientY }
      codeMenuOpen.value = true
    }
    const onCodeMenuSelect = () => {}

    // -- Data fetching --
    let timer = null

    const fetchData = async () => {
      const fetchToken = ++lastFetchToken.value
      try {
        const params = {}
        if (completedPeriod.value === 'custom') {
          if (completedFrom.value) params.completed_from = completedFrom.value
          if (completedTo.value) params.completed_to = completedTo.value
        }
        const { data } = await api.get(`/queues/${queueId.value}/status`, { params })
        if (fetchToken !== lastFetchToken.value) return
        if (data?.success) {
          const d = data.data
          queue.value = d.queue || null
          waitingEntries.value = d.entries || []
          servingEntries.value = d.entries_serving || []
          completedEntries.value = d.entries_completed || []
          statistics.value = d.statistics || null
          userEntry.value = d.user_entry || null
          if (queue.value?.name) setDetail(queue.value.name)
        }
      } catch {
        if (fetchToken !== lastFetchToken.value) return
        try {
          const { data } = await api.get(`/queues/${queueId.value}`)
          if (fetchToken !== lastFetchToken.value) return
          if (data?.success) { queue.value = data.data.queue || null; if (queue.value?.name) setDetail(queue.value.name) }
        } catch { $q.notify({ type: 'negative', message: 'Erro ao carregar fila' }); goBack() }
      } finally {
        if (fetchToken === lastFetchToken.value) {
          loading.value = false
        }
      }
    }

    const fetchUserRole = async () => {
      try {
        const { data } = await api.get('/auth/me')
        if (data?.success) {
          userRole.value = data.data.user.role
          currentUserId.value = data.data.user.id
        }
      } catch { /* ignore */ }
    }

    const fetchAccessCodes = async () => {
      codesLoading.value = true
      try {
        const { data } = await api.get(`/queues/${queueId.value}/access-codes`)
        if (data?.success) accessCodes.value = data.data?.access_codes || []
      } catch { /* ignore */ }
      finally { codesLoading.value = false }
    }

    const fetchReports = async () => {
      reportsLoading.value = true
      try {
        const { data } = await api.get(`/queues/${queueId.value}/reports`, { params: { period: reportPeriod.value } })
        if (data?.success) reportData.value = data.data
      } catch { $q.notify({ type: 'negative', message: 'Erro ao carregar relatórios' }) }
      finally { reportsLoading.value = false }
    }

    const openGlobalReports = () => {
      router.push({ name: 'queue-reports', query: { queue_id: String(queueId.value) } })
    }

    // -- Entry actions --
    const resetEntryUiState = (entryIds = []) => {
      const ids = Array.isArray(entryIds) ? entryIds.map(Number) : []

      if (ids.length) {
        selectedWaiting.value = selectedWaiting.value.filter(id => !ids.includes(Number(id)))
        selectedServing.value = selectedServing.value.filter(id => !ids.includes(Number(id)))
      } else {
        clearSelection()
      }

      if (entryMenuEntry.value && (!ids.length || ids.includes(Number(entryMenuEntry.value.id)))) {
        entryMenuEntry.value = null
        entryMenuOpen.value = false
      }
    }

    const updateEntryStatus = async (entryId, status, notes = null) => {
      updatingStatus.value = true
      try {
        const payload = { status }
        if (notes) payload.notes = notes
        await api.put(`/queues/entries/${entryId}/status`, payload)
        $q.notify({ type: 'positive', message: 'Status atualizado' })
        resetEntryUiState([entryId])
        await fetchData()
      } catch (err) { $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao atualizar status' }) }
      finally { updatingStatus.value = false }
    }

    const confirmRemoveEntry = (entryId, name) => {
      const normalizedEntryId = Number(entryId)
      if (!Number.isFinite(normalizedEntryId) || isEntryRemoving(normalizedEntryId)) return

      removeEntryTarget.value = {
        id: normalizedEntryId,
        name: name || 'esta pessoa',
      }
      showRemoveEntryDialog.value = true
    }

    const removeEntryFromDialog = async () => {
      if (!removeEntryTarget.value) return
      await removeEntry(removeEntryTarget.value.id)
    }

    const removeEntry = async (entryId) => {
      const normalizedEntryId = Number(entryId)
      if (!Number.isFinite(normalizedEntryId) || isEntryRemoving(normalizedEntryId)) return

      setRemovingEntries([normalizedEntryId], true)
      try {
        await api.delete(`/queues/entries/${normalizedEntryId}`)
        removeEntriesFromLocalState([normalizedEntryId])
        $q.notify({ type: 'positive', message: 'Pessoa removida da fila' })
        resetEntryUiState([normalizedEntryId])
        closeRemoveEntryDialog(true)
        await fetchData()
      } catch (err) {
        const status = err.response?.status
        const errorCode = err.response?.data?.error?.code
        if (status === 404 || errorCode === 'INVALID_STATUS') {
          removeEntriesFromLocalState([normalizedEntryId])
          resetEntryUiState([normalizedEntryId])
          closeRemoveEntryDialog(true)
          await fetchData()
          $q.notify({ type: 'info', message: 'A fila já havia sido atualizada. A tela foi sincronizada.' })
          return
        }
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao remover' })
      } finally {
        setRemovingEntries([normalizedEntryId], false)
      }
    }

    const batchRemoveSelected = (tab) => {
      const waitingIds = [...selectedWaiting.value]
      const servingIds = [...selectedServing.value]
      const ids = (tab === 'waiting' ? waitingIds : tab === 'serving' ? servingIds : [...waitingIds, ...servingIds])
        .map(id => Number(id))
        .filter(id => Number.isFinite(id) && !isEntryRemoving(id))
      if (!ids.length) return
      batchRemoveTargetIds.value = ids
      showBatchRemoveDialog.value = true
    }

    const confirmBatchRemoveSelected = async () => {
      const ids = [...batchRemoveTargetIds.value]
      if (!ids.length) return

      batchRemoving.value = true
      setRemovingEntries(ids, true)
      try {
        await api.post(`/queues/${queueId.value}/batch-remove`, { entry_ids: ids })
        removeEntriesFromLocalState(ids)
        $q.notify({ type: 'positive', message: `${ids.length} pessoa(s) removida(s)` })
        resetEntryUiState(ids)
        closeBatchRemoveDialog(true)
        await fetchData()
      } catch (err) {
        const errorCode = err.response?.data?.error?.code
        if (errorCode === 'INVALID_STATUS') {
          removeEntriesFromLocalState(ids)
          resetEntryUiState(ids)
          closeBatchRemoveDialog(true)
          await fetchData()
          $q.notify({ type: 'info', message: 'A fila já havia sido atualizada. A tela foi sincronizada.' })
          return
        }
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao remover' })
      } finally {
        setRemovingEntries(ids, false)
        batchRemoving.value = false
      }
    }

    // -- Move entries --
    const moveEntry = async (entryId, direction) => {
      movingEntries.value = true
      try {
        await api.put(`/queues/entries/${entryId}/position`, { direction })
        await fetchData()
      } catch (err) {
        const msg = err.response?.data?.error?.message
        if (msg) $q.notify({ type: 'info', message: msg })
      } finally { movingEntries.value = false }
    }

    const batchMoveSelected = async (direction) => {
      const ids = [...selectedWaiting.value]
      if (!ids.length) return
      movingEntries.value = true

      // Get the visual order of entries
      const sorted = sortedWaitingEntries.value
      const selectedEntries = sorted.filter(e => ids.includes(e.id))

      // For 'up', process from top to bottom; for 'down', bottom to top
      const ordered = direction === 'up'
        ? selectedEntries
        : [...selectedEntries].reverse()

      for (const entry of ordered) {
        const currentIndex = sortedWaitingEntries.value.findIndex(e => e.id === entry.id)
        const canMove = direction === 'up' ? currentIndex > 0 : currentIndex < sortedWaitingEntries.value.length - 1

        // Check if the adjacent position is also selected (skip if so, they stay together)
        if (canMove) {
          const adjacentIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1
          const adjacentEntry = sortedWaitingEntries.value[adjacentIndex]
          if (adjacentEntry && ids.includes(adjacentEntry.id)) continue

          try {
            await api.put(`/queues/entries/${entry.id}/position`, { direction })
          } catch { /* skip individual failures */ }
        }
      }

      await fetchData()
      movingEntries.value = false
    }

    const changeEntryPriority = async (entryId, priority) => {
      try {
        await api.put(`/queues/entries/${entryId}/priority`, { priority })
        $q.notify({ type: 'positive', message: 'Prioridade atualizada' })
        await fetchData()
      } catch (err) { $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro' }) }
    }

    // Notes dialog
    const openNotesDialog = (entryId, status) => {
      const cfg = {
        no_show: { title: 'Não Compareceu', desc: 'Registrar que a pessoa não compareceu ao atendimento.', color: 'negative', action: 'Registrar' },
      }[status] || { title: 'Alterar Status', desc: '', color: 'primary', action: 'Confirmar' }
      notesTitle.value = cfg.title
      notesDescription.value = cfg.desc
      notesColor.value = cfg.color
      notesActionLabel.value = cfg.action
      notesText.value = ''
      notesCallback.value = () => updateEntryStatus(entryId, status, notesText.value || null)
      showNotesDialog.value = true
    }
    const confirmNotesAction = async () => { if (notesCallback.value) await notesCallback.value(); showNotesDialog.value = false }

    // -- Queue actions --
    const callNext = async () => {
      callingNext.value = true
      try {
        const payload = { establishment_id: queue.value?.establishment_id }
        const { data } = await api.post(`/queues/${queueId.value}/call-next`, payload)
        if (data?.success && data?.data?.called) {
          const c = data.data.called
          $q.notify({ type: 'positive', message: `Chamando: ${c.user_name || c.guest_name || 'Próximo'}`, timeout: 5000 })
        } else {
          $q.notify({ type: 'info', message: data?.data?.message || 'Fila vazia' })
        }
        fetchData()
      } catch (err) { $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao chamar próximo' }) }
      finally { callingNext.value = false }
    }

    const joinWithCode = async () => {
      if (!accessCode.value.trim()) { $q.notify({ type: 'warning', message: 'Insira o código de acesso' }); return }
      joining.value = true
      try {
        await api.post(`/queues/${queueId.value}/join`, { access_code: accessCode.value.trim() })
        $q.notify({ type: 'positive', message: 'Você entrou na fila com sucesso!' })
        accessCode.value = ''
        fetchData()
      } catch (err) { $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Código inválido ou erro' }) }
      finally { joining.value = false }
    }

    // -- Access Code actions --
    const openCreateCode = () => {
      isEditingCode.value = false
      codeForm.value = { type: 'timed', expires_at: '', max_uses: null, is_active: true, code: '' }
      editingCodeId.value = null
      showCodeDialog.value = true
    }

    const openEditCode = (code) => {
      isEditingCode.value = true
      editingCodeId.value = code.id
      codeForm.value = { type: 'timed', expires_at: code.expires_at ? code.expires_at.replace(' ', 'T').substring(0, 16) : '', max_uses: code.max_uses, is_active: !!code.is_active, code: code.code }
      showCodeDialog.value = true
    }

    const saveCode = async () => {
      savingCode.value = true
      try {
        if (isEditingCode.value) {
          await api.put(`/queues/${queueId.value}/access-codes/${editingCodeId.value}`, { expires_at: codeForm.value.expires_at || null, max_uses: codeForm.value.max_uses || null, is_active: codeForm.value.is_active })
          $q.notify({ type: 'positive', message: 'Código atualizado' })
        } else {
          const payload = {}
          if (codeForm.value.type === 'timed' && codeForm.value.expires_at) payload.expires_at = codeForm.value.expires_at
          if (codeForm.value.type === 'limited' && codeForm.value.max_uses) payload.max_uses = codeForm.value.max_uses
          const { data } = await api.post(`/queues/${queueId.value}/generate-code`, payload)
          if (data?.success) {
            const obj = data.data?.access_code || data.data
            generatedCodeValue.value = obj?.code || ''
            generatedCodeExpiry.value = obj?.expires_at ? formatDate(obj.expires_at) : ''
            showGeneratedCodeDialog.value = true
          }
          $q.notify({ type: 'positive', message: 'Código gerado' })
        }
        showCodeDialog.value = false
        fetchAccessCodes()
      } catch (err) { $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao salvar código' }) }
      finally { savingCode.value = false }
    }

    const toggleCodeActive = async (code, active) => {
      try {
        await api.put(`/queues/${queueId.value}/access-codes/${code.id}`, { is_active: active })
        $q.notify({ type: 'positive', message: active ? 'Código ativado' : 'Código desativado' })
        fetchAccessCodes()
      } catch { $q.notify({ type: 'negative', message: 'Erro ao atualizar código' }) }
    }

    const closeDeleteCodeDialog = (force = false) => {
      if (!force && deletingCode.value) return
      showDeleteCodeDialog.value = false
      codeDeleteTarget.value = null
    }

    const deleteCode = (code) => {
      codeMenuOpen.value = false
      codeDeleteTarget.value = code
      showDeleteCodeDialog.value = true
    }

    const confirmDeleteCode = async () => {
      if (!codeDeleteTarget.value) return
      deletingCode.value = true
      try {
        await api.delete(`/queues/${queueId.value}/access-codes/${codeDeleteTarget.value.id}`)
        $q.notify({ type: 'positive', message: 'Código excluído' })
        closeDeleteCodeDialog(true)
        fetchAccessCodes()
      } catch {
        $q.notify({ type: 'negative', message: 'Erro ao excluir código' })
      } finally {
        deletingCode.value = false
      }
    }

    const copyToClipboard = (text) => {
      navigator.clipboard.writeText(text)
        .then(() => $q.notify({ type: 'positive', message: 'Copiado!', timeout: 1500 }))
        .catch(() => $q.notify({ type: 'warning', message: 'Não foi possível copiar' }))
    }

    const sanitizeFileName = (value) => {
      return (value || 'fila')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9-_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase() || 'fila'
    }

    const getPublicAppBaseUrl = () => {
      const configuredBase = import.meta.env.VITE_PUBLIC_APP_URL?.trim()
      if (configuredBase) {
        return configuredBase.replace(/\/$/, '')
      }

      if (typeof window !== 'undefined' && window.location?.origin) {
        return window.location.origin.replace(/\/$/, '')
      }

      return ''
    }

    const buildAccessCodeLink = (code) => {
      const params = new URLSearchParams({
        queue_id: String(queueId.value),
        access_code: String(code.code || ''),
      })

      return `${getPublicAppBaseUrl()}/join/queue?${params.toString()}`
    }

    const generateQrCodeImage = async (code) => {
      qrCodeTarget.value = code
      qrCodeLink.value = buildAccessCodeLink(code)
      qrCodeLoading.value = true

      try {
        qrCodeImage.value = await QRCode.toDataURL(qrCodeLink.value, {
          errorCorrectionLevel: 'H',
          margin: 2,
          width: 360,
          color: {
            dark: '#0f172a',
            light: '#FFFFFFFF',
          },
        })
      } finally {
        qrCodeLoading.value = false
      }
    }

    const openQrCodeDialog = async (code) => {
      codeMenuOpen.value = false
      showQrCodeDialog.value = true
      await generateQrCodeImage(code)
    }

    const closeQrCodeDialog = () => {
      if (exportingQr.value) return
      showQrCodeDialog.value = false
    }

    const exportQrCode = async (format) => {
      if (!qrCodeTarget.value) return

      exportingQr.value = true
      try {
        if (!qrCodeImage.value) {
          await generateQrCodeImage(qrCodeTarget.value)
        }

        const fileBase = `${sanitizeFileName(queue.value?.name)}-${sanitizeFileName(qrCodeTarget.value.code)}-qr`

        if (format === 'png') {
          const link = document.createElement('a')
          link.href = qrCodeImage.value
          link.download = `${fileBase}.png`
          document.body.appendChild(link)
          link.click()
          link.remove()
          $q.notify({ type: 'positive', message: 'QR Code exportado em PNG' })
          return
        }

        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
        const qrSize = 90
        const x = (210 - qrSize) / 2

        pdf.setFontSize(18)
        pdf.text(queue.value?.name || 'Fila', 105, 20, { align: 'center' })
        pdf.setFontSize(12)
        pdf.text(`Código de acesso: ${qrCodeTarget.value.code}`, 105, 29, { align: 'center' })
        pdf.addImage(qrCodeImage.value, 'PNG', x, 38, qrSize, qrSize, undefined, 'FAST')
        pdf.setFontSize(10)
        const lines = pdf.splitTextToSize(qrCodeLink.value, 170)
        pdf.text(lines, 20, 136)
        if (qrCodeTarget.value.expires_at) {
          pdf.text(`Expira em: ${formatDate(qrCodeTarget.value.expires_at)}`, 20, 152)
        }
        pdf.save(`${fileBase}.pdf`)
        $q.notify({ type: 'positive', message: 'QR Code exportado em PDF' })
      } catch (err) {
        console.error('Erro ao exportar QR Code:', err)
        $q.notify({ type: 'negative', message: 'Não foi possível exportar o QR Code' })
      } finally {
        exportingQr.value = false
      }
    }

    // -- Reports helpers --
    const formattedHourlyData = computed(() => {
      if (!reportData.value?.hourly_distribution) return []
      const raw = reportData.value.hourly_distribution
      const max = Math.max(...raw.map(d => Number(d.count)), 1)
      const map = {}
      raw.forEach(d => { map[Number(d.hour)] = Number(d.count) })
      const result = []
      for (let h = 0; h < 24; h++) {
        const count = map[h] || 0
        result.push({ hour: h, label: `${String(h).padStart(2, '0')}h`, count, percent: Math.round((count / max) * 100) })
      }
      return result
    })

    const chartDailyData = computed(() => {
      if (!reportData.value?.daily_breakdown) return []
      const days = reportData.value.daily_breakdown
      const maxTotal = Math.max(...days.map(d => Number(d.total)), 1)
      return days.map(d => ({
        date: d.date,
        label: new Date(d.date + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }),
        completed: Number(d.completed) || 0,
        no_show: Number(d.no_show) || 0,
        cancelled: Number(d.cancelled) || 0,
        total: Number(d.total) || 0,
        avg_wait: Math.round(Number(d.avg_wait) || 0),
        completedPct: Math.round(((Number(d.completed) || 0) / maxTotal) * 100),
        noshowPct: Math.round(((Number(d.no_show) || 0) / maxTotal) * 100),
        cancelledPct: Math.round(((Number(d.cancelled) || 0) / maxTotal) * 100),
      }))
    })

    const waitTimePointsArr = computed(() => {
      const data = chartDailyData.value
      if (!data.length) return []
      const maxWait = Math.max(...data.map(d => d.avg_wait), 1)
      const padding = 10
      const w = 400 - padding * 2
      const h = 120 - padding * 2
      return data.map((d, i) => ({
        x: padding + (data.length === 1 ? w / 2 : (i / (data.length - 1)) * w),
        y: padding + h - ((d.avg_wait / maxWait) * h),
      }))
    })

    const waitTimePoints = computed(() => {
      return waitTimePointsArr.value.map(p => `${p.x},${p.y}`).join(' ')
    })

    // -- Pie chart data --
    const statusPieData = computed(() => {
      if (!reportData.value?.summary) return []
      const s = reportData.value.summary
      const items = [
        { label: 'Atendidos', value: s.total_completed || 0, color: '#22c55e' },
        { label: 'Não Comp.', value: s.total_no_show || 0, color: '#ef4444' },
        { label: 'Cancelados', value: s.total_cancelled || 0, color: '#f59e0b' },
        { label: 'Aguardando', value: s.total_waiting || 0, color: '#3b82f6' },
        { label: 'Atendendo', value: s.total_serving || 0, color: '#8b5cf6' },
      ].filter(i => i.value > 0)
      const total = items.reduce((a, b) => a + b.value, 0)
      if (total === 0) return []
      let cumulative = 0
      return items.map(item => {
        const pct = item.value / total
        const startAngle = cumulative * 360
        cumulative += pct
        const endAngle = cumulative * 360
        return { ...item, pct, startAngle, endAngle, total }
      })
    })

    const priorityPieData = computed(() => {
      if (!reportData.value?.priority_distribution) return []
      const priorityLabels = { 0: 'Normal', 1: 'Prioritário', 2: 'Alta Prioridade' }
      const priorityColors = { 0: '#64748b', 1: '#f59e0b', 2: '#ef4444' }
      const items = reportData.value.priority_distribution
        .map(d => ({
          label: priorityLabels[d.priority] || `P${d.priority}`,
          value: Number(d.count) || 0,
          color: priorityColors[d.priority] || '#94a3b8',
        }))
        .filter(i => i.value > 0)
      const total = items.reduce((a, b) => a + b.value, 0)
      if (total === 0) return []
      let cumulative = 0
      return items.map(item => {
        const pct = item.value / total
        const startAngle = cumulative * 360
        cumulative += pct
        const endAngle = cumulative * 360
        return { ...item, pct, startAngle, endAngle, total }
      })
    })

    function pieSlicePath (cx, cy, r, startAngle, endAngle) {
      if (endAngle - startAngle >= 360) {
        return `M ${cx - r},${cy} A ${r},${r} 0 1,1 ${cx + r},${cy} A ${r},${r} 0 1,1 ${cx - r},${cy}`
      }
      const toRad = Math.PI / 180
      const x1 = cx + r * Math.cos((startAngle - 90) * toRad)
      const y1 = cy + r * Math.sin((startAngle - 90) * toRad)
      const x2 = cx + r * Math.cos((endAngle - 90) * toRad)
      const y2 = cy + r * Math.sin((endAngle - 90) * toRad)
      const largeArc = endAngle - startAngle > 180 ? 1 : 0
      return `M ${cx},${cy} L ${x1},${y1} A ${r},${r} 0 ${largeArc},1 ${x2},${y2} Z`
    }

    // -- Formatters --
    const formatDate = (d) => d ? new Date(d).toLocaleString('pt-BR') : '-'
    const formatDateShort = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '-'
    const formatWaitTime = (minutes) => {
      if (!minutes || minutes <= 0) return 'menos de 1 min'
      if (minutes < 60) return `${minutes} min`
      const h = Math.floor(minutes / 60)
      const m = minutes % 60
      return m > 0 ? `${h}h ${m}min` : `${h}h`
    }
    const goBack = () => router.push('/app/queues')

    // -- Lazy tab loading --
    watch(mainTab, (tab) => {
      if (tab === 'professionals') fetchQueueProfessionals()
      if (tab === 'services') fetchQueueServices()
      if (tab === 'tokens') fetchAccessCodes()
      if (tab === 'reports' && !reportData.value) fetchReports()
    })

    // -- Lifecycle --
    onMounted(async () => {
      await fetchUserRole()
      await fetchData()
      // Pre-fetch queue professionals for regular users (they see the list)
      fetchQueueProfessionals()
      if (canManage.value) {
        fetchQueueServices()
        fetchAccessCodes()
      }
      timer = setInterval(fetchData, 30000)
    })

    onUnmounted(() => {
      if (timer) clearInterval(timer)
      clearDetail()
    })

    return {
      queue, waitingEntries, sortedWaitingEntries, servingEntries, completedEntries, statistics, userEntry,
      loading, userRole, currentUserId, mainTab, flowTab,
      isRegularUser, canManage, statusVariant, statusLabel, statusOptions,
      saving, callingNext, addingPerson, joining, updatingStatus, batchRemoving, movingEntries,
      codesLoading, reportsLoading, savingCode, searchingUser, qpLoading, addingProf, svcLoading, savingSvc,
      // Selection
      selectedWaiting, selectedServing, toggleSelect, clearSelection,
      hasWaitingSelection, hasServingSelection, hasAnySelection, totalSelected,
      // Completed filter
      completedPeriod, completedFrom, completedTo, onCompletedPeriodChange,
      // Add person
      showAddPersonDialog, searchEmail, searchEmailError, foundUser,
      addToStatus, addToStatusOptions, addPersonPriority,
      searchUserByEmail, openAddPersonDialog, closeAddPersonDialog, addPersonFromDialog,
      // Info editing
      infoEditing, infoForm, startInfoEdit, cancelInfoEdit, saveInfoEdit,
      // Professionals
      queueProfessionals, estProfessionals, showAddProfDialog, selectedEstProf, availableEstProfessionals,
      fetchQueueProfessionals, openAddProfDialog, addSelectedProfessional, selfAddProfessional,
      showRemoveProfessionalDialog, professionalRemoveTarget, removingProfessional, closeRemoveProfessionalDialog, confirmRemoveProfessional,
      profMenuOpen, profMenuPos, profMenuTarget, profMenuItems, openProfMenu, onProfMenuSelect,
      // Services
      queueServices, showAddServiceDialog, showEditServiceDialog, svcDialogMode, selectedServiceIds, svcForm,
      availableEstServices, openAddServiceDialog, saveServiceAction, toggleServiceSelection,
      openEditServiceDialog, closeEditServiceDialog, editServiceForm, saveServiceEdit, savingServiceEdit,
      serviceDialogLoading, deletingSvc, unlinkingService, serviceUsageLabel, serviceUsageQueues,
      serviceMenuOpen, serviceMenuPos, serviceMenuTarget, serviceMenuItems, serviceMenuSubtitle, openServiceMenu, onServiceMenuSelect,
      showUnlinkServiceDialog, closeUnlinkServiceDialog, confirmUnlinkQueueService,
      showDeleteServiceDialog, closeDeleteServiceDialog, serviceActionTarget, serviceActionLoading, serviceDeleteImpactLabel,
      unlinkQueueService, promptDeleteService, confirmDeleteService,
      // Dialogs
      showNotesDialog, showCodeDialog, showGeneratedCodeDialog, showQrCodeDialog,
      showDeleteCodeDialog, codeDeleteTarget, deletingCode, closeDeleteCodeDialog, confirmDeleteCode,
      showRemoveEntryDialog, removeEntryTarget, removeEntryDialogLoading, closeRemoveEntryDialog,
      showBatchRemoveDialog, batchRemoveTargetIds, closeBatchRemoveDialog, confirmBatchRemoveSelected,
      notesTitle, notesDescription, notesText, notesColor, notesActionLabel, confirmNotesAction,
      // Entry context menu
      entryMenuOpen, entryMenuPos, entryMenuEntry, entryMenuItems,
      entryMenuSubtitle, openEntryMenu, onEntryMenuSelect,
      // Status context menu
      statusMenuOpen, statusMenuPos, statusMenuItems, openStatusMenu, onStatusMenuSelect,
      // Code context menu
      codeMenuOpen, codeMenuPos, codeMenuTarget, codeMenuItems, codeMenuSubtitle, openCodeMenu, onCodeMenuSelect,
      // Profile preview
      profilePreviewOpen, profilePreviewUserId, profilePreviewPos,
      // Access codes
      accessCodes, accessCode, isEditingCode, codeForm,
      generatedCodeValue, generatedCodeExpiry, qrCodeTarget, qrCodeImage, qrCodeLink, qrCodeLoading, exportingQr,
      openCreateCode, openEditCode, saveCode, copyToClipboard, deleteCode, openQrCodeDialog, closeQrCodeDialog, exportQrCode,
      // Reports
      reportPeriod, reportData, periodOptions, formattedHourlyData, fetchReports, openGlobalReports,
      chartDailyData, waitTimePoints, waitTimePointsArr,
      statusPieData, priorityPieData, pieSlicePath,
      // Actions
      callNext, joinWithCode, moveEntry, batchMoveSelected, changeEntryPriority,
      confirmRemoveEntry, removeEntryFromDialog, removeEntry, batchRemoveSelected,
      formatDate, formatDateShort, formatWaitTime, goBack,
    }
  },
})
</script>

<style lang="scss" scoped>
@import 'src/css/detail-page.scss';

// -- Stats row --
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;

  .stat-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.25rem;
    text-align: center;
  }
}

// -- Main card & tabs --
.main-card { padding: 0; overflow: hidden; }
.main-tabs {
  margin-top: 10px;
  padding: 0 1rem;

  :deep(.q-tab__label) {
    font-weight: 500;
  }
}

.flow-sub-tabs {
  padding: 0 0.5rem;
  background: var(--qm-bg-secondary);
}

.tab-label-row { display: flex; align-items: center; gap: 6px; }

.tab-panels, .flow-panels { background: transparent; min-height: 200px; }
.tab-panel-padded { padding: 1.5rem; }

// -- Flow actions bar --
.flow-actions-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0.75rem 1.25rem;
  border-bottom: 1px solid var(--qm-border-light, rgba(0,0,0,0.04));
}

// -- Completed filter --
.completed-filter {
  padding: 0.75rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--qm-border-light, rgba(0,0,0,0.04));
}

.completed-toggle { max-width: 240px; }

.completed-dates {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

// -- Add person dialog --
.add-person-dialog { min-width: 420px; max-width: 480px; }

.add-person-search {
  margin-bottom: 1rem;
  &__input { width: 100%; }
  &__error { display: block; font-size: 0.75rem; color: #ef4444; margin-top: 0.375rem; }
}

.add-person-profile {
  background: var(--qm-bg-secondary);
  border-radius: 12px;
  padding: 1.25rem;
  animation: fadeSlideIn 0.2s ease;

  &__header { display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.75rem; }
  &__avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--qm-brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem; flex-shrink: 0; }
  &__identity { display: flex; flex-direction: column; min-width: 0; }
  &__name { font-weight: 700; font-size: 1rem; color: var(--qm-text-primary); }
  &__email { font-size: 0.8125rem; color: var(--qm-text-muted); }
  &__details { display: flex; flex-direction: column; gap: 0.375rem; }
  &__row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--qm-text-secondary); }
}

.add-person-field {
  margin-bottom: 0.75rem;
  &__label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--qm-text-muted); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.5rem; }
  &__toggle { width: 100%; }
}

@keyframes fadeSlideIn {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}

// -- Panel header --
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  h3 { margin: 0 0 0.25rem; font-size: 1.125rem; font-weight: 600; color: var(--qm-text-primary); }
  p  { margin: 0; font-size: 0.875rem; color: var(--qm-text-muted); }
}
.panel-header-text { flex: 1; }
.panel-header-actions { display: flex; gap: 0.5rem; align-items: center; }

// -- Selection toolbar --
.selection-toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1.25rem;
  background: var(--qm-brand-light);
  border-bottom: 1px solid var(--qm-border);
  flex-wrap: wrap;
}
.selection-count { font-size: 0.8125rem; font-weight: 600; color: var(--qm-text-primary); }

// -- Entry items --
.entry-list { padding: 0; }

.entry-row {
  display: flex;
  align-items: center;
  padding: 0.875rem 1.25rem;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid var(--qm-border-light, rgba(0,0,0,0.04));
  &:last-child { border-bottom: none; }
  &:hover { background: var(--qm-bg-secondary); }
  &--selected { background: var(--qm-brand-light); &:hover { background: var(--qm-brand-light); } }
}

.entry-pos-wrap { position: relative; width: 38px; height: 38px; flex-shrink: 0; }
.entry-check { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0; pointer-events: none; z-index: 1; transition: opacity 0.15s; }
.entry-row:hover .entry-pos, .has-selection .entry-pos { opacity: 0; }
.entry-row:hover .entry-check, .has-selection .entry-check { opacity: 1; pointer-events: auto; }

.entry-body { display: flex; align-items: center; gap: 0.875rem; flex: 1; min-width: 0; }
.entry-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.entry-name-row { display: flex; align-items: center; gap: 6px; }
.entry-name { font-weight: 600; font-size: 0.875rem; color: var(--qm-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.entry-meta { font-size: 0.75rem; color: var(--qm-text-muted); display: flex; align-items: center; gap: 2px; }
.entry-dots { opacity: 0.3; transition: opacity 0.15s; flex-shrink: 0; .entry-row:hover & { opacity: 1; } }

.entry-pos {
  width: 38px; height: 38px; border-radius: 50%; background: var(--qm-brand-light); color: var(--qm-brand);
  display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0; transition: opacity 0.15s;
  &--serving { background: rgba(33, 150, 243, 0.12); color: #2196f3; }
  &--done { background: rgba(76, 175, 80, 0.12); color: #4caf50; }
  &--noshow { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
}

// -- Services grid --
.services-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}

.service-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  position: relative;
  overflow: hidden;

  &__icon {
    width: 48px; height: 48px; border-radius: 12px; background: var(--qm-brand-light); color: var(--qm-brand);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  &__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
  &__name { font-weight: 600; font-size: 0.9rem; color: var(--qm-text-primary); }
  &__meta { font-size: 0.75rem; color: var(--qm-text-muted); }
  &__desc { font-size: 0.8rem; color: var(--qm-text-secondary); margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  &__hint {
    position: absolute;
    right: 0.75rem;
    bottom: 0.75rem;
    padding: 0.3rem 0.55rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.82);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.18s ease, transform 0.18s ease;
    pointer-events: none;
  }

  &--interactive {
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease;

    &:hover {
      transform: translateY(-2px);
      box-shadow: var(--qm-shadow-lg);
    }

    &:hover .service-card__hint {
      opacity: 1;
      transform: translateY(0);
    }
  }
}

.service-usage-box {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  border-radius: 12px;
  background: var(--qm-bg-secondary);
}

.service-usage-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.service-usage-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  font-size: 0.75rem;
  font-weight: 600;
}

// -- Selectable list items --
.list-item--selectable {
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.15s;
  &:hover { background: var(--qm-bg-secondary); }
}
.list-item--interactive {
  cursor: pointer;
}
.list-item--active {
  background: var(--qm-brand-light) !important;
}

// -- Code items --
.code-mono { font-family: 'Consolas', 'Monaco', monospace; letter-spacing: 2px; }
.avatar-inactive { opacity: 0.4; }
.code-type-picker { text-align: center; }

.code-preview { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--qm-bg-secondary); border-radius: 8px; }
.code-preview-label { font-size: 0.75rem; color: var(--qm-text-muted); font-weight: 600; }
.code-preview-value { font-family: 'Consolas', 'Monaco', monospace; font-size: 1.125rem; font-weight: 700; letter-spacing: 3px; color: var(--qm-text-primary); }

.qr-code-dialog {
  width: min(520px, calc(100vw - 32px));
}

.qr-code-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 280px;
  padding: 1rem;
  border-radius: 16px;
  background: var(--qm-bg-secondary);
}

.qr-code-frame {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.qr-code-image {
  display: block;
  width: min(100%, 280px);
  height: auto;
}

.qr-code-meta {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.qr-code-link {
  padding: 0.875rem 1rem;
  border-radius: 12px;
  background: var(--qm-bg-secondary);
  color: var(--qm-text-secondary);
  font-size: 0.8125rem;
  line-height: 1.45;
  word-break: break-all;
}

// -- Charts --
.chart-container { padding: 0 0.5rem; }

.bar-chart {
  display: flex; align-items: flex-end; gap: 4px; height: 180px; padding-bottom: 24px; position: relative;
  border-bottom: 1px solid var(--qm-border);

  &__col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
  &__bars { display: flex; flex-direction: column; align-items: center; width: 100%; gap: 1px; }
  &__bar {
    width: 100%; max-width: 32px; border-radius: 3px 3px 0 0; min-height: 0; position: relative; transition: height 0.3s ease;
    &--completed { background: #22c55e; }
    &--noshow { background: #ef4444; }
    &--cancelled { background: #f59e0b; }
  }
  &__val { position: absolute; top: -16px; left: 50%; transform: translateX(-50%); font-size: 0.6rem; color: var(--qm-text-muted); white-space: nowrap; }
  &__lbl { font-size: 0.5625rem; color: var(--qm-text-muted); margin-top: 4px; position: absolute; bottom: 0; }
}

.chart-legend {
  display: flex; gap: 1rem; justify-content: center; margin-top: 0.75rem;
  &__item { display: flex; align-items: center; gap: 4px; font-size: 0.75rem; color: var(--qm-text-muted); }
  &__dot { width: 10px; height: 10px; border-radius: 2px;
    &--completed { background: #22c55e; }
    &--noshow { background: #ef4444; }
    &--cancelled { background: #f59e0b; }
  }
}

.line-chart {
  position: relative;
  &__svg { width: 100%; height: 120px; }
  &__labels { display: flex; justify-content: space-between; padding: 4px 10px 0; font-size: 0.5625rem; color: var(--qm-text-muted); }
}

// -- Reports grid --
.report-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
.report-card {
  padding: 1.25rem; background: var(--qm-bg-secondary); border-radius: 12px; text-align: center;
  border: 1px solid var(--qm-border-light, rgba(0,0,0,0.04));
  &--success { border-left: 3px solid #22c55e; }
  &--danger  { border-left: 3px solid #ef4444; }
  &--warning { border-left: 3px solid #f59e0b; }
  &--info    { border-left: 3px solid #3b82f6; }
}
.report-val { font-size: 1.5rem; font-weight: 700; color: var(--qm-text-primary); line-height: 1; margin-bottom: 0.25rem; }
.report-lbl { font-size: 0.75rem; color: var(--qm-text-muted); text-transform: uppercase; letter-spacing: 0.3px; }

// -- Hourly chart --
.hourly-chart { display: flex; align-items: flex-end; gap: 4px; height: 160px; padding: 0 0.5rem; border-bottom: 1px solid var(--qm-border); }
.hourly-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.hourly-bar { width: 100%; max-width: 28px; background: var(--qm-brand); border-radius: 4px 4px 0 0; min-height: 2px; position: relative; transition: height 0.3s ease; opacity: 0.7; }
.hourly-count { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 0.625rem; color: var(--qm-text-muted); white-space: nowrap; }
.hourly-lbl { font-size: 0.5625rem; color: var(--qm-text-muted); margin-top: 4px; }

// -- Pie charts --
.pie-charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
.pie-chart-wrapper { display: flex; align-items: center; gap: 1.5rem; }
.pie-chart-svg { width: 140px; height: 140px; flex-shrink: 0; }
.pie-legend { display: flex; flex-direction: column; gap: 0.5rem; }
.pie-legend__item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; }
.pie-legend__dot { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }
.pie-legend__label { color: var(--qm-text-primary); }
.pie-legend__value { color: var(--qm-text-muted); font-size: 0.75rem; margin-left: auto; white-space: nowrap; }

// -- User join form --
.join-form { display: flex; gap: 0.75rem; align-items: flex-start; }
.code-input { flex: 1; max-width: 300px; }

// -- Access code display --
.access-code-display { display: inline-flex; align-items: center; justify-content: center; padding: 1rem 2rem; background: var(--qm-bg-secondary); border-radius: 12px; border: 2px dashed var(--qm-brand); }
.access-code-text { font-size: 2rem; font-weight: 700; letter-spacing: 0.5rem; color: var(--qm-brand); font-family: monospace; }

// -- Full width detail item --
.detail-item.full-width { grid-column: 1 / -1; }

// -- Text utility --
.text-muted { color: var(--qm-text-muted); font-size: 0.8125rem; }

// -- Responsive --
@media (max-width: 768px) {
  .report-grid { grid-template-columns: repeat(2, 1fr); }
  .hourly-lbl { font-size: 0.5rem; }
  .selection-toolbar { flex-wrap: wrap; }
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .add-person-dialog { min-width: auto; max-width: 95vw; }
  .services-grid { grid-template-columns: 1fr; }
  .pie-charts-row { grid-template-columns: 1fr; }
  .pie-chart-wrapper { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 480px) {
  .report-grid { grid-template-columns: 1fr; }
  .stats-row { grid-template-columns: 1fr; }
}
</style>









