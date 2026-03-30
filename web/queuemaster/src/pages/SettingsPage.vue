<template>
  <q-page class="settings-page">
    <div class="page-header">
      <h1 class="page-title">Configurações</h1>
      <p class="page-subtitle">Gerencie suas preferências e configurações da conta</p>
    </div>

    <div class="settings-tabs-container soft-card">
      <q-tabs
        v-model="activeTab"
        dense
        class="settings-tabs"
        active-color="primary"
        indicator-color="primary"
        align="left"
        narrow-indicator
      >
        <q-tab name="profile" icon="person" label="Perfil" no-caps />
        <q-tab name="roles" icon="badge" label="Papéis" no-caps />
        <q-tab name="appearance" icon="palette" label="Aparência" no-caps />
        <q-tab name="notifications" icon="notifications" label="Notificações" no-caps />
      </q-tabs>

      <q-separator style="margin-top: 10px;" />

      <q-tab-panels v-model="activeTab" animated class="tab-panels">
        <q-tab-panel name="profile" class="tab-panel">
          <div class="panel-header">
            <h3>Informações do perfil</h3>
            <p>Seus dados de conta</p>
          </div>

          <div class="profile-section">
            <div class="profile-card">
              <q-avatar size="100px" class="profile-avatar">
                <img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Avatar" />
                <q-icon v-else name="person" size="50px" />
              </q-avatar>
              <div class="profile-info">
                <h4>{{ user?.name || 'Usuário' }}</h4>
                <p class="profile-email">{{ user?.email || '-' }}</p>
                <q-badge :color="roleColor" class="role-badge">{{ roleLabel }}</q-badge>
              </div>
            </div>

            <div class="profile-details-grid">
              <div class="detail-card">
                <q-icon name="badge" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Nome completo</span>
                  <span class="detail-value">{{ user?.name || 'Não informado' }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="mail" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">E-mail</span>
                  <span class="detail-value">{{ user?.email || 'Não informado' }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="work" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Papéis ativos</span>
                  <span class="detail-value">{{ roleLabel }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="calendar_today" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Membro desde</span>
                  <span class="detail-value">{{ formatDate(user?.created_at) }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="phone" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Telefone</span>
                  <span class="detail-value">{{ user?.phone || 'Não informado' }}</span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="verified" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">E-mail verificado</span>
                  <span class="detail-value">
                    <q-badge :color="user?.email_verified ? 'positive' : undefined" class="verification-badge" :class="{ 'verification-badge--neutral': !user?.email_verified }">
                      {{ user?.email_verified ? 'Verificado' : 'Não verificado' }}
                    </q-badge>
                  </span>
                </div>
              </div>
              <div class="detail-card">
                <q-icon name="login" size="20px" />
                <div class="detail-content">
                  <span class="detail-label">Último login</span>
                  <span class="detail-value">{{ formatDate(user?.last_login_at) }}</span>
                </div>
              </div>
            </div>

            <div class="logout-section">
              <q-btn
                outline
                color="negative"
                icon="logout"
                label="Sair da conta"
                no-caps
                @click="handleLogout"
              />
            </div>
          </div>
        </q-tab-panel>

        <q-tab-panel name="roles" class="tab-panel">
          <div class="panel-header">
            <h3>Papéis e acessos</h3>
            <p>Veja seus papéis atuais e solicite novos acessos de forma simples.</p>
          </div>

          <div class="roles-panel">
            <div class="roles-summary-card soft-card">
              <div class="roles-summary-card__header">
                <div>
                  <h4>Seu status atual</h4>
                  <p>{{ rolesSummaryDescription }}</p>
                </div>
                <div class="roles-summary-card__badges">
                  <q-badge
                    v-for="badge in activeRoleBadges"
                    :key="badge.label"
                    :color="badge.color"
                    :label="badge.label"
                  />
                </div>
              </div>

              <div class="roles-summary-grid">
                <div class="role-stat-card">
                  <span class="role-stat-card__label">Profissional</span>
                  <strong>{{ isAdminUser ? 'Ativo' : (roleSummary.professional_link_count || 0) }}</strong>
                  <small>{{ isAdminUser ? 'acesso profissional liberado' : 'vínculos ativos' }}</small>
                </div>
                <div class="role-stat-card">
                  <span class="role-stat-card__label">Gestão</span>
                  <strong>{{ roleSummary.business_count || 0 }}</strong>
                  <small>negócios próprios</small>
                </div>
                <div class="role-stat-card">
                  <span class="role-stat-card__label">Gerência</span>
                  <strong>{{ isAdminUser || canManageOwnBusinesses ? 'Ativa' : 'Pendente' }}</strong>
                  <small>{{ isAdminUser ? 'acesso total de gestão' : 'criar e gerir negócios' }}</small>
                </div>
              </div>

              <div class="roles-summary-card__footer">
                <div v-if="roleSummary.revert_blockers?.length" class="roles-summary-card__hint">
                  <q-icon name="info" size="16px" />
                  <span>{{ roleSummary.revert_blockers[0] }}</span>
                </div>
                <q-btn
                  v-if="canRevertToClient"
                  flat
                  no-caps
                  icon="person_off"
                  label="Voltar a ser cliente"
                  :loading="revertingToClient"
                  @click="revertToClient"
                />
              </div>
            </div>

            <div class="roles-grid">
              <div class="role-access-card soft-card">
                <div class="role-access-card__icon role-access-card__icon--professional">
                  <q-icon name="workspace_premium" size="24px" />
                </div>
                <div class="role-access-card__content">
                  <div class="role-access-card__top">
                    <div>
                      <h4>Atuação profissional</h4>
                      <p>{{ isAdminUser ? 'Seu acesso profissional permanece ativo para leitura operacional e auditoria.' : 'Solicite vínculo com um negócio e envie um perfil curto com links e observações.' }}</p>
                    </div>
                    <q-badge
                      :color="professionalAccessActive ? 'positive' : (pendingProfessionalRequests.length ? 'warning' : 'grey-7')"
                      :label="professionalAccessActive ? 'Ativo' : (pendingProfessionalRequests.length ? 'Em análise' : 'Disponível')"
                    />
                  </div>

                  <div class="role-access-card__meta">
                    <span>{{ professionalAccessDescription }}</span>
                  </div>

                  <div v-if="isAdminUser" class="role-access-card__stats">
                    <div class="role-access-card__stat">
                      <span>Vínculos profissionais</span>
                      <strong>{{ roleSummary.professional_link_count || 0 }}</strong>
                    </div>
                    <div class="role-access-card__stat">
                      <span>Convites pendentes</span>
                      <strong>{{ receivedProfessionalInvitations.length }}</strong>
                    </div>
                  </div>

                  <div v-if="!isAdminUser" class="role-access-card__actions">
                    <q-btn
                      color="primary"
                      icon="workspace_premium"
                      :label="professionalRequestCtaLabel"
                      no-caps
                      @click="openRoleOverlay('professional')"
                    />
                  </div>
                </div>
              </div>

              <div class="role-access-card soft-card">
                <div class="role-access-card__icon role-access-card__icon--manager">
                  <q-icon name="business_center" size="24px" />
                </div>
                <div class="role-access-card__content">
                  <div class="role-access-card__top">
                    <div>
                      <h4>{{ isAdminUser ? 'Gestão de negócios' : 'Tornar-se gerente' }}</h4>
                      <p>{{ isAdminUser ? 'Seu acesso de gerência permanece ativo com visão completa de gestão.' : 'Peça liberação para criar seus próprios negócios. Basta a aprovação de um admin.' }}</p>
                    </div>
                    <q-badge
                      :color="managerAccessActive ? 'positive' : (pendingManagerRequest ? 'warning' : 'grey-7')"
                      :label="managerAccessActive ? 'Ativo' : (pendingManagerRequest ? 'Em análise' : 'Disponível')"
                    />
                  </div>

                  <div class="role-access-card__meta">
                    <span>{{ managerRequestStatusText }}</span>
                  </div>

                  <div v-if="isAdminUser" class="role-access-card__stats">
                    <div class="role-access-card__stat">
                      <span>Negócios próprios</span>
                      <strong>{{ roleSummary.business_count || 0 }}</strong>
                    </div>
                    <div class="role-access-card__stat">
                      <span>Gestão contextual</span>
                      <strong>{{ roleSummary.has_management_access ? 'Ativa' : 'Inativa' }}</strong>
                    </div>
                  </div>

                  <div v-if="!isAdminUser" class="role-access-card__actions">
                    <q-btn
                      v-if="!canManageOwnBusinesses"
                      color="primary"
                      icon="admin_panel_settings"
                      label="Tornar-se gerente"
                      no-caps
                      :disable="Boolean(pendingManagerRequest)"
                      @click="openRoleOverlay('manager')"
                    />
                    <q-btn
                      v-if="pendingManagerRequest"
                      flat
                      no-caps
                      icon="close"
                      label="Cancelar solicitação"
                      :loading="managerRequestActionId === pendingManagerRequest.id"
                      @click="cancelManagerRequest(pendingManagerRequest)"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div v-if="pendingProfessionalRequests.length > 0" class="request-history soft-card">
              <div class="request-history__title">Solicitações profissionais em andamento</div>
              <div class="list-items">
                <div v-for="request in pendingProfessionalRequests" :key="request.id" class="list-item">
                  <div class="list-item-info">
                    <div class="list-item-avatar"><q-icon name="schedule" size="18px" /></div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ request.business_name }}</span>
                      <span class="list-item-meta">
                        {{ request.establishment_name || 'Negócio sem estabelecimento informado' }} · {{ getInviteStatusLabel(request.status) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="receivedProfessionalInvitations.length > 0" class="request-history soft-card">
              <div class="request-history__title">Convites profissionais recebidos</div>
              <div class="list-items">
                <div v-for="invitation in receivedProfessionalInvitations" :key="invitation.id" class="list-item">
                  <div class="list-item-info">
                    <div class="list-item-avatar"><q-icon name="mail_outline" size="18px" /></div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ invitation.business_name }}</span>
                      <span class="list-item-meta">
                        {{ invitation.establishment_name || 'Negócio sem estabelecimento informado' }} · {{ getInviteStatusLabel(invitation.status) }}
                      </span>
                    </div>
                  </div>
                  <div class="list-item-side">
                    <q-btn
                      flat
                      round
                      dense
                      icon="check_circle"
                      color="positive"
                      :loading="invitationActionId === invitation.id && invitationAction === 'accept'"
                      @click="respondToInvitation(invitation, 'accept')"
                    />
                    <q-btn
                      flat
                      round
                      dense
                      icon="cancel"
                      color="negative"
                      :loading="invitationActionId === invitation.id && invitationAction === 'reject'"
                      @click="respondToInvitation(invitation, 'reject')"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div v-if="roleRequests.length > 0" class="request-history soft-card">
              <div class="request-history__title">Solicitações de gerência</div>
              <div class="list-items">
                <div v-for="request in roleRequests" :key="request.id" class="list-item">
                  <div class="list-item-info">
                    <div class="list-item-avatar"><q-icon name="admin_panel_settings" size="18px" /></div>
                    <div class="list-item-details">
                      <span class="list-item-name">{{ getRoleRequestTitle(request) }}</span>
                      <span class="list-item-meta">{{ getRoleRequestMeta(request) }}</span>
                    </div>
                  </div>
                  <div class="list-item-side">
                    <q-badge :color="getRoleRequestColor(request.status)" :label="getRoleRequestStatusLabel(request.status)" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </q-tab-panel>

        <q-tab-panel name="appearance" class="tab-panel">
          <div class="panel-header">
            <h3>Aparência</h3>
            <p>Personalize a interface do sistema</p>
          </div>

          <div class="settings-list">
            <div class="setting-row">
              <div class="setting-icon">
                <q-icon :name="isDark ? 'dark_mode' : 'light_mode'" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Tema escuro</span>
                <span class="setting-description">Alterna entre modo claro e escuro</span>
              </div>
              <q-toggle v-model="isDark" @update:model-value="toggleTheme" color="primary" />
            </div>

            <div class="setting-row brand-color-row">
              <div class="setting-icon">
                <q-icon name="palette" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Cor da marca</span>
                <span class="setting-description">Cor principal aplicada em botões, ícones e destaques</span>
              </div>
            </div>
            <div class="brand-presets">
              <button
                v-for="preset in brandPresets"
                :key="preset.color"
                class="color-swatch"
                :class="{ active: brandColor === preset.color }"
                :style="{ background: preset.color }"
                @click="setBrandColor(preset.color)"
                :title="preset.label"
              >
                <q-icon v-if="brandColor === preset.color" name="check" size="16px" />
              </button>
            </div>

            <div class="custom-hex-row">
              <q-input
                v-model="customHex"
                outlined
                dense
                label="Cor personalizada (HEX)"
                maxlength="7"
                class="hex-input"
                :error="hexError"
                :error-message="hexErrorMsg"
                hint="Ex: #3b82f6"
                @keyup.enter="applyCustomHex"
              >
                <template #prepend>
                  <div class="hex-preview" :style="{ background: hexPreviewColor }"></div>
                </template>
                <template #append>
                  <q-btn flat dense no-caps label="Aplicar" color="primary" @click="applyCustomHex" :disable="!customHex" />
                </template>
              </q-input>
              <q-btn flat dense no-caps label="Restaurar padrão" icon="restart_alt" class="reset-brand-btn" @click="resetBrand" />
            </div>
          </div>
        </q-tab-panel>

        <q-tab-panel name="notifications" class="tab-panel">
          <div class="panel-header">
            <h3>Notificações</h3>
            <p>Configure push e acompanhe sua inbox</p>
          </div>

          <div class="settings-list">
            <div class="setting-row">
              <div class="setting-icon">
                <q-icon name="campaign" size="24px" />
              </div>
              <div class="setting-info">
                <span class="setting-title">Notificações push</span>
                <span class="setting-description">
                  {{ browserPermissionLabel }}
                </span>
              </div>
              <q-toggle :model-value="preferences.push_enabled" color="primary" @update:model-value="togglePushNotifications" />
            </div>
          </div>

          <div class="notifications-inbox soft-card q-mt-lg">
            <div class="notifications-inbox__header">
              <div>
                <h4>Inbox</h4>
                <p>Filtre por período, tipo e texto para localizar notificações.</p>
              </div>
              <div class="notifications-inbox__actions">
                <q-btn flat no-caps icon="restart_alt" label="Limpar" @click="resetNotificationFilters" />
                <q-btn color="primary" no-caps icon="search" label="Aplicar" @click="applyNotificationFilters" />
              </div>
            </div>

            <div class="notifications-filters">
              <q-input
                v-model="notificationFilters.search"
                outlined
                dense
                label="Pesquisar"
                placeholder="Título, remetente, negócio..."
                @keyup.enter="applyNotificationFilters"
              >
                <template #prepend>
                  <q-icon name="search" />
                </template>
              </q-input>

              <q-select
                v-model="notificationFilters.type"
                outlined
                dense
                emit-value
                map-options
                :options="notificationTypeOptions"
                label="Tipo"
                @update:model-value="applyNotificationFilters"
              />

              <q-input
                v-model="notificationFilters.date_from"
                outlined
                dense
                type="date"
                label="De"
                @update:model-value="applyNotificationFilters"
              />

              <q-input
                v-model="notificationFilters.date_to"
                outlined
                dense
                type="date"
                label="Até"
                @update:model-value="applyNotificationFilters"
              />
            </div>

            <div v-if="hasNotificationSelection" class="notifications-selection-toolbar">
              <span class="selection-count">{{ selectedNotificationIds.length }} selecionada(s)</span>
              <q-btn
                flat
                dense
                no-caps
                icon="done_all"
                label="Marcar como lidas"
                :loading="notificationActionId === 'bulk' && notificationAction === 'bulk-read'"
                @click="markSelectedNotificationsRead"
              />
              <q-btn
                flat
                dense
                no-caps
                icon="delete_sweep"
                color="negative"
                label="Excluir em lote"
                :loading="notificationActionId === 'bulk' && notificationAction === 'bulk-delete'"
                @click="deleteSelectedNotifications"
              />
              <q-btn flat dense no-caps label="Limpar seleção" @click="clearNotificationSelection" />
            </div>

            <div class="notifications-workspace" :class="{ 'notifications-workspace--detail': quasar.screen.lt.md && selectedNotificationId }">
              <div v-show="!(quasar.screen.lt.md && selectedNotificationId)" class="notifications-master">
                <div v-if="inboxLoading" class="loading-state-sm">
                  <q-spinner-dots color="primary" size="28px" />
                </div>

                <div v-else-if="inboxNotifications.length === 0" class="empty-state-sm">
                  <q-icon name="notifications_none" size="44px" />
                  <p>Nenhuma notificação encontrada</p>
                </div>

                <template v-else>
                  <div class="table-container notifications-table-container">
                    <table class="data-table notifications-table">
                      <thead>
                        <tr>
                          <th class="th-select">
                            <q-checkbox
                              :model-value="allVisibleNotificationsSelected"
                              dense
                              @update:model-value="toggleAllVisibleNotifications"
                            />
                          </th>
                          <th class="th-notification">Notificação</th>
                          <th class="th-type">Tipo</th>
                          <th class="th-date">Data</th>
                          <th class="th-status">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="notification in inboxNotifications"
                          :key="notification.id"
                          class="clickable-row"
                          :class="{
                            'notification-row--unread': !notification.read_at,
                            'notification-row--active': selectedNotificationId === notification.id,
                          }"
                          @click="openInboxNotification(notification)"
                        >
                          <td @click.stop>
                            <q-checkbox
                              :model-value="selectedNotificationIds.includes(notification.id)"
                              dense
                              @update:model-value="toggleNotificationSelection(notification.id)"
                            />
                          </td>
                          <td>
                            <div class="notification-cell">
                              <div class="notification-icon" :class="{ 'notification-icon--unread': !notification.read_at }">
                                <q-icon :name="getNotifIcon(notification.type)" size="18px" />
                              </div>
                              <div class="notification-content">
                                <span class="notification-title">{{ notification.title }}</span>
                                <span class="notification-body">{{ notification.body || 'Sem descrição adicional.' }}</span>
                              </div>
                            </div>
                          </td>
                          <td>
                            <q-badge
                              :color="!notification.read_at ? 'primary' : 'grey-7'"
                              :label="getNotificationTypeLabel(notification.type)"
                            />
                          </td>
                          <td>
                            <div class="notification-date-block">
                              <span class="notification-date-primary">{{ formatNotifTime(notification.sent_at || notification.created_at) }}</span>
                              <span class="notification-date-secondary">{{ formatDate(notification.sent_at || notification.created_at) }}</span>
                            </div>
                          </td>
                          <td>
                            <q-badge
                              :color="notification.workflow?.status_color || (notification.read_at ? 'positive' : 'orange')"
                              :label="notification.workflow?.status_label || (notification.read_at ? 'Lida' : 'Não lida')"
                            />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div v-if="inboxMeta.total_pages > 1" class="notifications-pagination">
                    <q-pagination
                      :model-value="inboxMeta.page"
                      :max="inboxMeta.total_pages"
                      max-pages="6"
                      direction-links
                      boundary-links
                      color="primary"
                      @update:model-value="changeNotificationPage"
                    />
                  </div>
                </template>
              </div>

              <div v-show="!quasar.screen.lt.md || selectedNotificationId" class="notifications-detail">
                <NotificationDetailPanel
                  :notification="selectedNotification"
                  :loading="selectedNotificationLoading"
                  :show-back="quasar.screen.lt.md && Boolean(selectedNotificationId)"
                  :type-label="selectedNotificationTypeLabel"
                  :action-state="{ id: notificationActionId, action: notificationAction }"
                  @back="closeSelectedNotification"
                  @mark-read="markInboxNotificationRead"
                  @accept-invitation="acceptInboxNotification"
                  @reject-invitation="rejectInboxNotification"
                  @approve-manager-request="approveManagerRequest"
                  @reject-manager-request="rejectManagerRequest"
                  @open-business="openNotificationBusiness"
                />
              </div>
            </div>
          </div>
        </q-tab-panel>
      </q-tab-panels>

      <transition name="settings-overlay-fade">
        <div v-if="activeRoleOverlay" class="settings-overlay">
          <div class="settings-overlay__sheet soft-card">
            <div class="settings-overlay__header">
              <div>
                <q-btn
                  flat
                  dense
                  no-caps
                  icon="arrow_back"
                  label="Voltar"
                  class="settings-overlay__back"
                  @click="closeRoleOverlay"
                />
                <h3>{{ activeRoleOverlay === 'professional' ? 'Solicitação profissional' : 'Solicitação de gerência' }}</h3>
                <p>{{ activeRoleOverlay === 'professional' ? 'Envie um perfil simples para pedir vínculo profissional.' : 'Peça acesso para criar e gerir seus próprios negócios.' }}</p>
              </div>
              <q-badge color="primary" :label="activeRoleOverlay === 'professional' ? 'Análise do gerente' : 'Análise do admin'" />
            </div>

            <template v-if="activeRoleOverlay === 'professional'">
              <div class="professional-request-grid">
                <q-select
                  v-model="selectedBusinessId"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  fill-input
                  hide-selected
                  :options="businessOptions"
                  label="Negócio *"
                  :loading="searchingBusinesses"
                  @filter="filterBusinesses"
                  @popup-show="loadBusinessOptions"
                  @update:model-value="onBusinessSelected"
                />

                <q-select
                  v-model="selectedEstablishmentId"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  fill-input
                  hide-selected
                  :options="establishmentRequestOptions"
                  label="Estabelecimento *"
                  :disable="!selectedBusinessId"
                  :loading="searchingEstablishments"
                  @filter="filterEstablishments"
                  @popup-show="loadEstablishmentOptions"
                />

                <q-input
                  v-model="professionalRequestForm.specialty"
                  outlined
                  dense
                  label="Área de atuação *"
                  placeholder="Ex: barbeiro, nail designer, fisioterapeuta"
                />

                <q-input
                  v-model="professionalRequestForm.portfolioUrl"
                  outlined
                  dense
                  label="Portfólio ou site"
                  placeholder="https://..."
                />

                <q-input
                  v-model="professionalRequestForm.githubUrl"
                  outlined
                  dense
                  label="GitHub"
                  placeholder="https://github.com/..."
                />

                <q-input
                  v-model="professionalRequestForm.linkedinUrl"
                  outlined
                  dense
                  label="LinkedIn"
                  placeholder="https://linkedin.com/in/..."
                />
              </div>

              <q-input
                v-model="professionalRequestForm.experienceSummary"
                outlined
                dense
                type="textarea"
                autogrow
                class="q-mt-md"
                label="Resumo profissional *"
                placeholder="Conte rapidamente com o que você trabalha, experiência e como pode contribuir."
              />

              <q-input
                v-model="professionalRequestForm.notes"
                outlined
                dense
                type="textarea"
                autogrow
                class="q-mt-md"
                label="Observações adicionais"
                placeholder="Disponibilidade, certificações, serviços principais ou algo que ajude na análise."
              />

              <div class="professional-request-sheet__hint">
                <q-icon name="info" size="18px" />
                <span>Pelo menos um link profissional ou um resumo bem preenchido já ajuda bastante na análise.</span>
              </div>

              <div class="professional-request-actions">
                <q-btn flat no-caps label="Cancelar" @click="closeRoleOverlay" />
                <q-btn
                  color="primary"
                  icon="send"
                  label="Criar solicitação"
                  no-caps
                  :loading="sendingProfessionalRequest"
                  @click="submitProfessionalRequest"
                />
              </div>
            </template>

            <template v-else>
              <div class="professional-request-grid">
                <q-input
                  v-model="managerRequestForm.businessName"
                  outlined
                  dense
                  label="Nome do negócio pretendido *"
                  placeholder="Ex: Studio Aurora"
                />

                <q-input
                  v-model="managerRequestForm.businessSegment"
                  outlined
                  dense
                  label="Segmento *"
                  placeholder="Ex: salão, clínica, estúdio"
                />

                <q-input
                  v-model="managerRequestForm.websiteUrl"
                  outlined
                  dense
                  label="Site ou portfólio"
                  placeholder="https://..."
                />

                <q-input
                  v-model="managerRequestForm.linkedinUrl"
                  outlined
                  dense
                  label="LinkedIn"
                  placeholder="https://linkedin.com/in/..."
                />
              </div>

              <q-input
                v-model="managerRequestForm.motivation"
                outlined
                dense
                type="textarea"
                autogrow
                class="q-mt-md"
                label="Por que você quer gerenciar seus próprios negócios? *"
                placeholder="Descreva o contexto do seu negócio e por que precisa da liberação."
              />

              <q-input
                v-model="managerRequestForm.notes"
                outlined
                dense
                type="textarea"
                autogrow
                class="q-mt-md"
                label="Observações adicionais"
                placeholder="Informações extras que ajudem o admin a decidir."
              />

              <div class="professional-request-sheet__hint">
                <q-icon name="info" size="18px" />
                <span>Um admin aprovando já libera sua criação de business e a solicitação passa para concluída.</span>
              </div>

              <div class="professional-request-actions">
                <q-btn flat no-caps label="Cancelar" @click="closeRoleOverlay" />
                <q-btn
                  color="primary"
                  icon="send"
                  label="Enviar para admins"
                  no-caps
                  :loading="sendingManagerRequest"
                  @click="submitManagerRequest"
                />
              </div>
            </template>
          </div>
        </div>
      </transition>
    </div>
  </q-page>
</template>

<script>
import { defineComponent, ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'boot/axios'
import { BRAND_PRESETS, loadBrandColor, saveBrandColor, resetBrandColor, isValidHex, normalizeHex } from 'src/utils/brand'
import { resolveUserAvatarUrl } from 'src/utils/userAvatar'
import { useNotificationsCenter } from 'src/composables/useNotificationsCenter'
import { useRemoteSelectSearch } from 'src/composables/useRemoteSelectSearch'
import NotificationDetailPanel from 'src/components/notifications/NotificationDetailPanel.vue'

export default defineComponent({
  name: 'SettingsPage',

  components: {
    NotificationDetailPanel,
  },

  setup() {
    const createEmptyProfessionalRequestForm = () => ({
      specialty: '',
      portfolioUrl: '',
      githubUrl: '',
      linkedinUrl: '',
      experienceSummary: '',
      notes: '',
    })

    const createEmptyManagerRequestForm = () => ({
      businessName: '',
      businessSegment: '',
      websiteUrl: '',
      linkedinUrl: '',
      motivation: '',
      notes: '',
    })

    const createDefaultRoleSummary = () => ({
      effective_role: 'client',
      has_professional_access: false,
      has_management_access: false,
      has_contextual_management_access: false,
      has_manager_access_grant: false,
      can_manage_own_businesses: false,
      owns_business: false,
      business_count: 0,
      professional_link_count: 0,
      manager_link_count: 0,
      can_revert_to_client: false,
      revert_blockers: [],
      active_roles: ['client'],
    })

    const route = useRoute()
    const router = useRouter()
    const $q = useQuasar()

    const activeTab = ref(
      route.query.tab === 'notifications'
        ? 'notifications'
        : route.query.tab === 'appearance'
          ? 'appearance'
          : route.query.tab === 'roles'
            ? 'roles'
            : 'profile'
    )
    const user = ref(null)
    const verifiedRole = ref(null)
    const roleSummary = ref(createDefaultRoleSummary())
    const loadingUser = ref(true)
    const userAvatarUrl = computed(() => resolveUserAvatarUrl(user.value))

    const isDark = ref(false)
    const brandColor = ref('')
    const brandPresets = BRAND_PRESETS
    const customHex = ref('')
    const hexError = ref(false)
    const hexErrorMsg = ref('')

    const activeRoleOverlay = ref(route.query.panel === 'manager' ? 'manager' : route.query.panel === 'professional' ? 'professional' : '')
    const selectedBusinessId = ref(null)
    const selectedEstablishmentId = ref(null)
    const professionalRequestForm = ref(createEmptyProfessionalRequestForm())
    const managerRequestForm = ref(createEmptyManagerRequestForm())
    const sendingProfessionalRequest = ref(false)
    const sendingManagerRequest = ref(false)
    const invitationSummary = ref({ sent: [], received: [] })
    const roleRequests = ref([])
    const managerRequestActionId = ref(null)
    const revertingToClient = ref(false)
    const invitationActionId = ref(null)
    const invitationAction = ref('')
    const notificationActionId = ref(null)
    const notificationAction = ref('')
    const selectedNotificationId = ref(route.query.notification ? Number(route.query.notification) : null)
    const selectedNotification = ref(null)
    const selectedNotificationLoading = ref(false)
    const selectedNotificationIds = ref([])
    const notificationFilters = ref({
      search: '',
      type: '',
      date_from: '',
      date_to: '',
    })

    const {
      preferences,
      browserPermission,
      inboxNotifications,
      inboxMeta,
      inboxLoading,
      notificationTypeOptions,
      getNotifIcon,
      getNotificationTypeLabel,
      formatNotifTime,
      fetchPreferences,
      setPushEnabled,
      fetchInbox,
      fetchNotificationById,
      markNotificationRead,
      markNotificationsRead,
      deleteNotifications,
      acceptInvitation,
      rejectInvitation,
    } = useNotificationsCenter()

    const businessSearch = useRemoteSelectSearch({
      search: ({ query, signal }) => api.get('/businesses/search', {
        params: { q: query, limit: 20 },
        signal,
        meta: { dedupe: false },
      }),
      mapOptions: (response) => (response.data?.data?.businesses || []).map((business) => ({
        label: business.name,
        value: business.id,
        description: business.description || '',
      })),
    })

    const establishmentSearch = useRemoteSelectSearch({
      search: ({ query, signal, contextKey }) => api.get(`/businesses/${contextKey}/discover-establishments`, {
        params: { q: query, limit: 20 },
        signal,
        meta: { dedupe: false },
      }),
      mapOptions: (response) => (response.data?.data?.establishments || []).map((establishment) => ({
        label: establishment.name,
        value: establishment.id,
      })),
    })

    const businessSearchOptions = businessSearch.options
    const establishmentSearchOptions = establishmentSearch.options
    const searchingBusinesses = businessSearch.loading
    const searchingEstablishments = establishmentSearch.loading

    const resolvedPrimaryRole = computed(() => {
      if (verifiedRole.value === 'admin' || user.value?.role === 'admin' || user.value?.effective_role === 'admin') {
        return 'admin'
      }
      return user.value?.effective_role || verifiedRole.value || user.value?.role || 'client'
    })
    const isAdminUser = computed(() => resolvedPrimaryRole.value === 'admin')

    const roleLabel = computed(() => {
      if (isAdminUser.value) {
        return 'Administrador'
      }
      if (roleSummary.value.has_management_access && roleSummary.value.has_professional_access) {
        return 'Gerente + Profissional'
      }
      if (roleSummary.value.has_management_access) {
        return 'Gerente'
      }
      if (roleSummary.value.has_professional_access) {
        return 'Profissional'
      }
      return getRoleLabel(resolvedPrimaryRole.value)
    })
    const roleColor = computed(() => {
      if (isAdminUser.value) return getRoleColor('admin')
      if (roleSummary.value.has_management_access) return 'warning'
      if (roleSummary.value.has_professional_access) return 'info'
      return getRoleColor(resolvedPrimaryRole.value)
    })
    const activeRoleBadges = computed(() => {
      if (isAdminUser.value) {
        return [{ label: 'Administrador', color: getRoleColor('admin') }]
      }
      if (roleSummary.value.has_management_access) {
        return [{ label: 'Gerente', color: 'warning' }]
      }
      if (roleSummary.value.has_professional_access) {
        return [{ label: 'Profissional', color: 'info' }]
      }
      return [{ label: 'Cliente', color: 'grey-7' }]
    })
    const rolesSummaryDescription = computed(() => {
      if (isAdminUser.value) {
        return 'Você possui acesso administrativo completo, com visualização de gerente e profissional ativas para acompanhamento operacional.'
      }
      if (canManageOwnBusinesses.value && roleSummary.value.has_professional_access) {
        return 'Você já atua como gerente e também possui atuação profissional.'
      }
      if (canManageOwnBusinesses.value) {
        return 'Você já pode criar e gerir seus próprios negócios.'
      }
      if (roleSummary.value.has_management_access) {
        return 'Você já gerencia neg?cios vinculados, mas ainda não possui liberação para abrir o seu.'
      }
      if (roleSummary.value.has_professional_access) {
        return 'Você já possui vínculos profissionais ativos.'
      }
      return 'Hoje sua conta está no modo cliente, sem vínculos de gestão ou atuação profissional.'
    })
    const professionalRequestCtaLabel = computed(() => (
      roleSummary.value.has_professional_access ? 'Solicitar novo vínculo' : 'Tornar-se profissional'
    ))
    const professionalAccessActive = computed(() => isAdminUser.value || Boolean(roleSummary.value.has_professional_access))
    const managerAccessActive = computed(() => isAdminUser.value || canManageOwnBusinesses.value)
    const professionalAccessDescription = computed(() => {
      if (isAdminUser.value) {
        return 'Como admin, o estado profissional permanece ativo para consulta e acompanhamento dos dados operacionais.'
      }
      if (roleSummary.value.has_professional_access) {
        return 'Você já possui acesso profissional e pode pedir novos vínculos.'
      }
      return 'Ideal para quem vai atuar em estabelecimentos de terceiros.'
    })
    const businessOptions = computed(() => businessSearchOptions.value)
    const establishmentRequestOptions = computed(() => establishmentSearchOptions.value)
    const pendingManagerRequest = computed(() => (
      (roleRequests.value || []).find((item) => item.requested_role === 'manager' && item.status === 'pending') || null
    ))
    const canRevertToClient = computed(() => Boolean(roleSummary.value.can_revert_to_client))
    const canManageOwnBusinesses = computed(() => Boolean(roleSummary.value.can_manage_own_businesses))
    const managerRequestStatusText = computed(() => {
      if (isAdminUser.value) {
        return 'Como admin, sua gerência está sempre ativa e sem limitações para acompanhar a gestão.'
      }
      if (canManageOwnBusinesses.value) {
        return 'Seu acesso de gestão já está liberado para criar business.'
      }
      if (pendingManagerRequest.value) {
        return 'Seu pedido está em análise. Basta um admin aprovar para concluir.'
      }
      if (roleSummary.value.has_management_access) {
        return 'Você já atua na gestão de negócios de terceiros, mas ainda precisa da aprovação para criar business próprio.'
      }
      if (roleSummary.value.has_professional_access) {
        return 'Como profissional, você também pode pedir liberação para ter seus próprios business.'
      }
      return 'Se aprovado por um admin, seu perfil passa a poder criar e gerir business.'
    })
    const pendingProfessionalRequests = computed(() => {
      return (invitationSummary.value.sent || []).filter((item) => item.direction === 'professional_to_business' && item.status === 'pending')
    })
    const receivedProfessionalInvitations = computed(() => {
      return (invitationSummary.value.received || []).filter((item) => item.direction === 'business_to_professional' && item.status === 'pending')
    })
    const visibleNotificationIds = computed(() => inboxNotifications.value.map((notification) => Number(notification.id)).filter(Boolean))
    const hasNotificationSelection = computed(() => selectedNotificationIds.value.length > 0)
    const allVisibleNotificationsSelected = computed(() => (
      visibleNotificationIds.value.length > 0
      && visibleNotificationIds.value.every((id) => selectedNotificationIds.value.includes(id))
    ))
    const selectedNotificationTypeLabel = computed(() => (
      selectedNotification.value ? getNotificationTypeLabel(selectedNotification.value.type) : 'Notificação'
    ))

    onMounted(() => {
      fetchUserFromBackend()
      loadTheme()
      fetchInvitationSummary()
      fetchRoleRequests()
      fetchPreferences().catch(() => {})
      if (activeTab.value === 'notifications') {
        applyNotificationFilters().then(() => {
          if (selectedNotificationId.value) {
            loadSelectedNotification(selectedNotificationId.value, { markAsRead: false, syncQuery: false })
          }
        })
      }
    })

    watch(activeTab, (value) => {
      router.replace({ query: buildSettingsQuery(value, activeRoleOverlay.value, selectedNotificationId.value) })
      if (value === 'notifications') {
        applyNotificationFilters()
      }
    })

    watch(activeRoleOverlay, (value) => {
      router.replace({ query: buildSettingsQuery(activeTab.value, value, selectedNotificationId.value) })
    })

    watch(() => route.query.panel, (value) => {
      activeRoleOverlay.value = value === 'manager' ? 'manager' : value === 'professional' ? 'professional' : ''
      if (value === 'manager' || value === 'professional') {
        activeTab.value = 'roles'
      }
    })

    watch(() => route.query.notification, (value) => {
      const nextId = Number(value) || null
      if (nextId === selectedNotificationId.value) return

      if (nextId) {
        activeTab.value = 'notifications'
        loadSelectedNotification(nextId, { markAsRead: false, syncQuery: false })
        return
      }

      selectedNotificationId.value = null
      selectedNotification.value = null
    })

    const fetchUserFromBackend = async () => {
      loadingUser.value = true
      try {
        const response = await api.get('/auth/me')
        if (response.data?.success && response.data?.data?.user) {
          user.value = response.data.data.user
          verifiedRole.value = response.data.data.user.role
          roleSummary.value = response.data.data.user.role_summary || createDefaultRoleSummary()
          localStorage.setItem('user', JSON.stringify(response.data.data.user))
        }
      } catch (err) {
        console.error('Erro ao buscar usuário:', err)
        verifiedRole.value = null
        roleSummary.value = createDefaultRoleSummary()
      } finally {
        loadingUser.value = false
      }
    }

    const fetchInvitationSummary = async () => {
      try {
        const response = await api.get('/invitations')
        if (response.data?.success) {
          invitationSummary.value = response.data.data || { sent: [], received: [] }
        }
      } catch (err) {
        console.error('Erro ao buscar convites:', err)
      }
    }

    const fetchRoleRequests = async () => {
      try {
        const response = await api.get('/role-requests')
        if (response.data?.success) {
          roleRequests.value = response.data.data?.requests || []
          roleSummary.value = response.data.data?.role_summary || roleSummary.value
        }
      } catch (err) {
        console.error('Erro ao buscar solicitações de papel:', err)
      }
    }

    const loadTheme = () => {
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        isDark.value = savedTheme === 'dark'
      } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
      }

      brandColor.value = loadBrandColor()
    }

    const browserPermissionLabel = computed(() => {
      if (browserPermission.value === 'granted') return 'Permissão concedida no navegador.'
      if (browserPermission.value === 'denied') return 'Permissão bloqueada no navegador.'
      if (browserPermission.value === 'unsupported') return 'Push não suportado neste dispositivo.'
      return 'Receber alertas no navegador quando houver novidades.'
    })

    const buildSettingsQuery = (tab = activeTab.value, panel = activeRoleOverlay.value, notificationId = selectedNotificationId.value) => {
      const query = {}
      if (tab !== 'profile') {
        query.tab = tab
      }
      if (tab === 'roles' && panel) {
        query.panel = panel
      }
      if (tab === 'notifications' && notificationId) {
        query.notification = String(notificationId)
      }
      return query
    }

    const loadSelectedNotification = async (notificationOrId, options = {}) => {
      const { markAsRead = true, syncQuery = true } = options
      const baseNotification = typeof notificationOrId === 'object' ? notificationOrId : null
      const targetId = Number(baseNotification?.id || notificationOrId || 0)

      if (!targetId) {
        selectedNotificationId.value = null
        selectedNotification.value = null
        return
      }

      selectedNotificationId.value = targetId
      if (baseNotification) {
        selectedNotification.value = baseNotification
      }

      if (syncQuery) {
        router.replace({ query: buildSettingsQuery(activeTab.value, activeRoleOverlay.value, targetId) })
      }

      if (markAsRead && baseNotification && !baseNotification.read_at) {
        await markNotificationRead(baseNotification)
      }

      selectedNotificationLoading.value = true
      try {
        const freshNotification = await fetchNotificationById(targetId)
        selectedNotification.value = freshNotification
      } catch (err) {
        if (err.response?.status === 404) {
          selectedNotificationId.value = null
          selectedNotification.value = null
          router.replace({ query: buildSettingsQuery(activeTab.value, activeRoleOverlay.value, null) })
        } else {
          $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao carregar detalhes da notificação' })
        }
      } finally {
        selectedNotificationLoading.value = false
      }
    }

    const closeSelectedNotification = () => {
      selectedNotificationId.value = null
      selectedNotification.value = null
      router.replace({ query: buildSettingsQuery(activeTab.value, activeRoleOverlay.value, null) })
    }

    const toggleNotificationSelection = (notificationId) => {
      const id = Number(notificationId)
      if (!id) return

      if (selectedNotificationIds.value.includes(id)) {
        selectedNotificationIds.value = selectedNotificationIds.value.filter((item) => item !== id)
        return
      }

      selectedNotificationIds.value = [...selectedNotificationIds.value, id]
    }

    const toggleAllVisibleNotifications = (checked) => {
      selectedNotificationIds.value = checked ? [...visibleNotificationIds.value] : []
    }

    const clearNotificationSelection = () => {
      selectedNotificationIds.value = []
    }

    const applyNotificationFilters = async (page = 1) => {
      await fetchInbox({
        ...notificationFilters.value,
        page,
        per_page: 12,
      })

      const visibleSet = new Set(visibleNotificationIds.value)
      selectedNotificationIds.value = selectedNotificationIds.value.filter((id) => visibleSet.has(id))
    }

    const resetNotificationFilters = async () => {
      notificationFilters.value = {
        search: '',
        type: '',
        date_from: '',
        date_to: '',
      }
      await applyNotificationFilters()
    }

    const changeNotificationPage = async (page) => {
      await applyNotificationFilters(page)
    }

    const togglePushNotifications = async (value) => {
      try {
        await setPushEnabled(Boolean(value))
        $q.notify({
          type: 'positive',
          message: value ? 'Notificações push ativadas' : 'Notificações push desativadas',
        })
      } catch (err) {
        $q.notify({
          type: 'warning',
          message: err.message || 'Não foi possível atualizar o push',
        })
      }
    }

    const openInboxNotification = async (notification) => {
      await loadSelectedNotification(notification, { markAsRead: true, syncQuery: true })
    }

    const markInboxNotificationRead = async (notification) => {
      notificationActionId.value = notification.id
      notificationAction.value = 'read'
      try {
        await markNotificationRead(notification)
        if (selectedNotificationId.value === notification.id) {
          selectedNotification.value = {
            ...(selectedNotification.value || notification),
            read_at: new Date().toISOString(),
            is_read: true,
          }
        }
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const markSelectedNotificationsRead = async () => {
      if (!selectedNotificationIds.value.length) return

      notificationActionId.value = 'bulk'
      notificationAction.value = 'bulk-read'
      try {
        await markNotificationsRead(selectedNotificationIds.value)
        if (selectedNotificationId.value && selectedNotificationIds.value.includes(selectedNotificationId.value)) {
          await loadSelectedNotification(selectedNotificationId.value, { markAsRead: false, syncQuery: false })
        }
        clearNotificationSelection()
        $q.notify({ type: 'positive', message: 'Notificações selecionadas marcadas como lidas' })
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao marcar notificações como lidas' })
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const deleteSelectedNotifications = async () => {
      if (!selectedNotificationIds.value.length) return

      const confirmed = window.confirm(`Excluir ${selectedNotificationIds.value.length} notificação(ões) selecionada(s)?`)
      if (!confirmed) return

      const targetPage = inboxNotifications.value.length === selectedNotificationIds.value.length && inboxMeta.value.page > 1
        ? inboxMeta.value.page - 1
        : inboxMeta.value.page

      notificationActionId.value = 'bulk'
      notificationAction.value = 'bulk-delete'
      try {
        await deleteNotifications(selectedNotificationIds.value)
        if (selectedNotificationId.value && selectedNotificationIds.value.includes(selectedNotificationId.value)) {
          selectedNotificationId.value = null
          selectedNotification.value = null
        }
        clearNotificationSelection()
        await applyNotificationFilters(targetPage)
        router.replace({ query: buildSettingsQuery(activeTab.value, activeRoleOverlay.value, selectedNotificationId.value) })
        $q.notify({ type: 'positive', message: 'Notificações selecionadas excluídas' })
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao excluir notificações' })
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const acceptInboxNotification = async (notification) => {
      notificationActionId.value = notification.id
      notificationAction.value = 'accept'
      try {
        await acceptInvitation(notification)
        await Promise.all([applyNotificationFilters(inboxMeta.value.page), fetchInvitationSummary(), fetchUserFromBackend()])
        await loadSelectedNotification(notification.id, { markAsRead: false, syncQuery: false })
        $q.notify({ type: 'positive', message: 'Convite aceito com sucesso' })
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao aceitar convite' })
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const rejectInboxNotification = async (payload) => {
      const notification = payload?.notification || payload
      const note = payload?.note?.trim() || ''

      notificationActionId.value = notification.id
      notificationAction.value = 'reject'
      try {
        await rejectInvitation(notification, { decision_note: note || undefined })
        await applyNotificationFilters(inboxMeta.value.page)
        await loadSelectedNotification(notification.id, { markAsRead: false, syncQuery: false })
        $q.notify({ type: 'positive', message: 'Convite recusado' })
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao recusar convite' })
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const approveManagerRequest = async (notification) => {
      const requestId = notification?.data?.request_id
      if (!requestId) return

      notificationActionId.value = notification.id
      notificationAction.value = 'approve-manager'
      try {
        await api.post(`/role-requests/${requestId}/approve`)
        await markNotificationRead(notification)
        await Promise.all([applyNotificationFilters(inboxMeta.value.page), fetchRoleRequests(), fetchUserFromBackend()])
        await loadSelectedNotification(notification.id, { markAsRead: false, syncQuery: false })
        $q.notify({ type: 'positive', message: 'Solicitação de gerência aprovada' })
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao aprovar solicitação' })
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const rejectManagerRequest = async (payload) => {
      const notification = payload?.notification || payload
      const note = payload?.note?.trim() || ''
      const requestId = notification?.data?.request_id
      if (!requestId) return

      notificationActionId.value = notification.id
      notificationAction.value = 'reject-manager'
      try {
        await api.post(`/role-requests/${requestId}/reject`, { decision_note: note || undefined })
        await markNotificationRead(notification)
        await Promise.all([applyNotificationFilters(inboxMeta.value.page), fetchRoleRequests(), fetchUserFromBackend()])
        await loadSelectedNotification(notification.id, { markAsRead: false, syncQuery: false })
        $q.notify({ type: 'positive', message: 'Solicitação de gerência recusada' })
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao recusar solicitação' })
      } finally {
        notificationActionId.value = null
        notificationAction.value = ''
      }
    }

    const openNotificationBusiness = (notification) => {
      const routeTarget = notification?.workflow?.business_route
      if (!routeTarget) {
        $q.notify({ type: 'warning', message: 'Este vínculo não possui acesso ativo ao negócio no momento.' })
        return
      }

      router.push(routeTarget)
    }

    const notifyRemoteRateLimit = (error) => {
      if (error?.response?.status !== 429) return

      $q.notify({
        type: 'warning',
        message: error.response?.data?.error?.message || 'Você excedeu o limite de requisições. Tente novamente em instantes.',
      })
    }

    const loadBusinessOptions = async () => {
      try {
        await businessSearch.load('')
      } catch (error) {
        notifyRemoteRateLimit(error)
      }
    }

    const filterBusinesses = (value, update, abort) => {
      businessSearch.filter(value, update, abort, {
        onError: notifyRemoteRateLimit,
      })
    }

    const onBusinessSelected = async (businessId) => {
      selectedEstablishmentId.value = null
      establishmentSearch.clear()
      if (businessId) {
        await fetchEstablishmentsForRequest('', businessId)
      }
    }

    const fetchEstablishmentsForRequest = async (query = '', businessId = selectedBusinessId.value) => {
      if (!businessId) {
        establishmentSearch.clear()
        return []
      }

      return establishmentSearch.load(query, {
        contextKey: String(businessId),
      })
    }

    const loadEstablishmentOptions = async () => {
      if (!selectedBusinessId.value) return
      try {
        await fetchEstablishmentsForRequest('', selectedBusinessId.value)
      } catch (error) {
        notifyRemoteRateLimit(error)
      }
    }

    const filterEstablishments = (value, update, abort) => {
      if (!selectedBusinessId.value) {
        establishmentSearch.clear()
        update(() => {
          establishmentSearchOptions.value = []
        })
        abort?.()
        return
      }

      establishmentSearch.filter(value, update, abort, {
        contextKey: String(selectedBusinessId.value),
        onError: notifyRemoteRateLimit,
      })
    }

    const resetProfessionalRequestForm = () => {
      selectedBusinessId.value = null
      selectedEstablishmentId.value = null
      professionalRequestForm.value = createEmptyProfessionalRequestForm()
      businessSearch.clear()
      establishmentSearch.clear()
    }

    const resetManagerRequestForm = () => {
      managerRequestForm.value = createEmptyManagerRequestForm()
    }

    const openRoleOverlay = (type) => {
      activeTab.value = 'roles'
      activeRoleOverlay.value = type
    }

    const closeRoleOverlay = () => {
      activeRoleOverlay.value = ''
    }

    const submitProfessionalRequest = async () => {
      if (!selectedBusinessId.value) {
        $q.notify({ type: 'warning', message: 'Selecione um negócio' })
        return
      }
      if (!selectedEstablishmentId.value) {
        $q.notify({ type: 'warning', message: 'Selecione um estabelecimento' })
        return
      }
      if (!professionalRequestForm.value.specialty?.trim()) {
        $q.notify({ type: 'warning', message: 'Informe sua área de atuação' })
        return
      }
      if (!professionalRequestForm.value.experienceSummary?.trim()) {
        $q.notify({ type: 'warning', message: 'Adicione um resumo profissional' })
        return
      }

      sendingProfessionalRequest.value = true
      try {
        await api.post(`/businesses/${selectedBusinessId.value}/join-request`, {
          establishment_id: selectedEstablishmentId.value,
          specialty: professionalRequestForm.value.specialty?.trim() || undefined,
          portfolio_url: professionalRequestForm.value.portfolioUrl?.trim() || undefined,
          github_url: professionalRequestForm.value.githubUrl?.trim() || undefined,
          linkedin_url: professionalRequestForm.value.linkedinUrl?.trim() || undefined,
          experience_summary: professionalRequestForm.value.experienceSummary?.trim() || undefined,
          notes: professionalRequestForm.value.notes?.trim() || undefined,
        })
        $q.notify({ type: 'positive', message: 'Solicitação enviada para análise do gerente' })
        resetProfessionalRequestForm()
        closeRoleOverlay()
        await Promise.all([fetchInvitationSummary(), applyNotificationFilters(inboxMeta.value.page || 1)])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao enviar solicitação' })
      } finally {
        sendingProfessionalRequest.value = false
      }
    }

    const submitManagerRequest = async () => {
      if (!managerRequestForm.value.businessName?.trim()) {
        $q.notify({ type: 'warning', message: 'Informe o nome do negócio pretendido' })
        return
      }
      if (!managerRequestForm.value.businessSegment?.trim()) {
        $q.notify({ type: 'warning', message: 'Informe o segmento do negócio' })
        return
      }
      if (!managerRequestForm.value.motivation?.trim()) {
        $q.notify({ type: 'warning', message: 'Explique por que você precisa da gerência' })
        return
      }

      sendingManagerRequest.value = true
      try {
        await api.post('/role-requests/manager', {
          business_name: managerRequestForm.value.businessName?.trim() || undefined,
          business_segment: managerRequestForm.value.businessSegment?.trim() || undefined,
          website_url: managerRequestForm.value.websiteUrl?.trim() || undefined,
          linkedin_url: managerRequestForm.value.linkedinUrl?.trim() || undefined,
          motivation: managerRequestForm.value.motivation?.trim() || undefined,
          notes: managerRequestForm.value.notes?.trim() || undefined,
        })
        $q.notify({ type: 'positive', message: 'Solicitação enviada para os administradores' })
        resetManagerRequestForm()
        closeRoleOverlay()
        await Promise.all([fetchRoleRequests(), fetchUserFromBackend(), applyNotificationFilters(inboxMeta.value.page || 1)])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao enviar solicitação de gerência' })
      } finally {
        sendingManagerRequest.value = false
      }
    }

    const cancelManagerRequest = async (request) => {
      if (!request?.id) return

      managerRequestActionId.value = request.id
      try {
        await api.post(`/role-requests/${request.id}/cancel`)
        $q.notify({ type: 'positive', message: 'Solicitação cancelada' })
        await Promise.all([fetchRoleRequests(), fetchUserFromBackend(), applyNotificationFilters(inboxMeta.value.page || 1)])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao cancelar solicitação' })
      } finally {
        managerRequestActionId.value = null
      }
    }

    const revertToClient = async () => {
      revertingToClient.value = true
      try {
        await api.post('/role-requests/revert-to-client')
        $q.notify({ type: 'positive', message: 'Seu perfil voltou para cliente' })
        await Promise.all([fetchRoleRequests(), fetchUserFromBackend(), applyNotificationFilters(inboxMeta.value.page || 1)])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Não foi possível voltar para cliente' })
      } finally {
        revertingToClient.value = false
      }
    }

    const respondToInvitation = async (invitation, action) => {
      invitationActionId.value = invitation.id
      invitationAction.value = action
      try {
        await api.post(`/invitations/${invitation.id}/${action}`)
        $q.notify({ type: 'positive', message: action === 'accept' ? 'Convite aceito com sucesso' : 'Convite recusado' })
        await Promise.all([fetchInvitationSummary(), fetchUserFromBackend()])
      } catch (err) {
        $q.notify({ type: 'negative', message: err.response?.data?.error?.message || 'Erro ao responder convite' })
      } finally {
        invitationActionId.value = null
        invitationAction.value = ''
      }
    }

    const setBrandColor = (color) => {
      brandColor.value = color
      saveBrandColor(color)
      customHex.value = ''
      hexError.value = false
    }

    const hexPreviewColor = computed(() => {
      if (!customHex.value) return 'var(--qm-bg-tertiary)'
      const hex = customHex.value.startsWith('#') ? customHex.value : '#' + customHex.value
      return isValidHex(hex) ? hex : 'var(--qm-bg-tertiary)'
    })

    const applyCustomHex = () => {
      let hex = customHex.value.trim()
      if (!hex.startsWith('#')) hex = '#' + hex
      if (!isValidHex(hex)) {
        hexError.value = true
        hexErrorMsg.value = 'HEX inválido. Use formato #RGB ou #RRGGBB'
        return
      }
      hex = normalizeHex(hex)
      hexError.value = false
      hexErrorMsg.value = ''
      brandColor.value = hex
      saveBrandColor(hex)
      customHex.value = hex
    }

    const resetBrand = () => {
      brandColor.value = resetBrandColor()
      customHex.value = ''
      hexError.value = false
    }

    const toggleTheme = (value) => {
      localStorage.setItem('theme', value ? 'dark' : 'light')
      document.documentElement.setAttribute('data-theme', value ? 'dark' : 'light')
      brandColor.value = loadBrandColor()
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Não informado'
      const date = new Date(dateString)
      return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      })
    }

    const getRoleLabel = (role) => {
      const roles = {
        admin: 'Administrador',
        manager: 'Gerente',
        professional: 'Profissional',
        user: 'Usuário',
        client: 'Cliente',
      }
      return roles[role] || 'Usuário'
    }

    const getRoleColor = (role) => {
      const colors = {
        admin: 'negative',
        manager: 'warning',
        professional: 'info',
        user: 'grey',
        client: 'grey',
      }
      return colors[role] || 'grey'
    }

    const getInviteStatusLabel = (status) => ({ pending: 'Pendente', accepted: 'Aceito', rejected: 'Rejeitado', cancelled: 'Cancelado' }[status] || status)
    const getRoleRequestStatusLabel = (status) => ({ pending: 'Pendente', accepted: 'Concluída', rejected: 'Recusada', cancelled: 'Cancelada' }[status] || status)
    const getRoleRequestColor = (status) => ({ pending: 'warning', accepted: 'positive', rejected: 'negative', cancelled: 'grey-7' }[status] || 'grey-7')
    const getRoleRequestTitle = (request) => {
      const businessName = request?.payload?.business_name
      return businessName ? `Gerência para ${businessName}` : 'Solicitação de gerência'
    }
    const getRoleRequestMeta = (request) => {
      const segment = request?.payload?.business_segment
      const reviewedBy = request?.reviewed_by_user_name
      const parts = [segment, getRoleRequestStatusLabel(request?.status)]
      if (reviewedBy) {
        parts.push(`por ${reviewedBy}`)
      }
      return parts.filter(Boolean).join(' · ')
    }

    const handleLogout = async () => {
      try {
        await api.post('/auth/logout')
      } catch {
        // ignore
      }

      localStorage.removeItem('user')
      router.push('/login')
    }

    return {
      quasar: $q,
      activeTab,
      user,
      userAvatarUrl,
      loadingUser,
      roleSummary,
      isDark,
      brandColor,
      brandPresets,
      customHex,
      hexError,
      hexErrorMsg,
      hexPreviewColor,
      roleLabel,
      roleColor,
      activeRoleBadges,
      isAdminUser,
      managerAccessActive,
      rolesSummaryDescription,
      professionalAccessActive,
      professionalAccessDescription,
      professionalRequestCtaLabel,
      activeRoleOverlay,
      selectedBusinessId,
      selectedEstablishmentId,
      professionalRequestForm,
      managerRequestForm,
      sendingProfessionalRequest,
      sendingManagerRequest,
      searchingBusinesses,
      searchingEstablishments,
      businessOptions,
      establishmentRequestOptions,
      pendingProfessionalRequests,
      receivedProfessionalInvitations,
      roleRequests,
      pendingManagerRequest,
      canManageOwnBusinesses,
      canRevertToClient,
      managerRequestStatusText,
      managerRequestActionId,
      revertingToClient,
      invitationActionId,
      invitationAction,
      preferences,
      browserPermission,
      browserPermissionLabel,
      notificationFilters,
      notificationTypeOptions,
      inboxNotifications,
      inboxMeta,
      inboxLoading,
      selectedNotificationId,
      selectedNotification,
      selectedNotificationLoading,
      selectedNotificationIds,
      hasNotificationSelection,
      allVisibleNotificationsSelected,
      selectedNotificationTypeLabel,
      notificationActionId,
      notificationAction,
      toggleTheme,
      setBrandColor,
      applyCustomHex,
      resetBrand,
      formatDate,
      getRoleLabel,
      getRoleColor,
      getInviteStatusLabel,
      filterBusinesses,
      filterEstablishments,
      loadBusinessOptions,
      loadEstablishmentOptions,
      onBusinessSelected,
      applyNotificationFilters,
      resetNotificationFilters,
      changeNotificationPage,
      togglePushNotifications,
      openInboxNotification,
      closeSelectedNotification,
      markInboxNotificationRead,
      markSelectedNotificationsRead,
      deleteSelectedNotifications,
      toggleNotificationSelection,
      toggleAllVisibleNotifications,
      clearNotificationSelection,
      acceptInboxNotification,
      rejectInboxNotification,
      approveManagerRequest,
      rejectManagerRequest,
      openNotificationBusiness,
      getNotifIcon,
      getNotificationTypeLabel,
      formatNotifTime,
      openRoleOverlay,
      closeRoleOverlay,
      submitProfessionalRequest,
      submitManagerRequest,
      cancelManagerRequest,
      revertToClient,
      respondToInvitation,
      handleLogout,
      verifiedRole,
      getRoleRequestStatusLabel,
      getRoleRequestColor,
      getRoleRequestTitle,
      getRoleRequestMeta,
    }
  },
})
</script>

<style lang="scss" scoped>
.settings-page {
  padding: 0 1.5rem 1.5rem;
}

.page-header {
  margin-bottom: 1.5rem;
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

.settings-tabs-container {
  position: relative;
  padding: 0;
  overflow: hidden;
}

.settings-tabs {
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
  margin-bottom: 1.5rem;

  h3 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--qm-text-muted);
  }
}

.profile-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.profile-card {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  background: var(--qm-bg-secondary);
  border-radius: 12px;

  @media (max-width: 500px) {
    flex-direction: column;
    text-align: center;
  }
}

.profile-avatar {
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-muted);
  flex-shrink: 0;
}

.profile-info {
  h4 {
    margin: 0 0 0.25rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--qm-text-primary);
  }
}

.profile-email {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  color: var(--qm-text-muted);
}

.role-badge {
  font-size: 0.75rem;
}

.verification-badge--neutral {
  background: var(--qm-bg-tertiary);
  color: var(--qm-text-secondary);
  border: 1px solid var(--qm-border);
}

.profile-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.detail-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--qm-bg-secondary);
  border-radius: 10px;
  color: var(--qm-text-muted);
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 0.9375rem;
  color: var(--qm-text-primary);
  font-weight: 500;
}

.roles-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.roles-summary-card,
.role-access-card,
.request-history {
  padding: 1.25rem;
  border-radius: 16px;
}

.roles-summary-card {
  background:
    radial-gradient(circle at top right, color-mix(in srgb, var(--qm-brand) 14%, transparent), transparent 34%),
    linear-gradient(135deg, var(--qm-bg-secondary), color-mix(in srgb, var(--qm-brand) 6%, var(--qm-bg-secondary)));
}

.roles-summary-card__header,
.role-access-card__top,
.settings-overlay__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;

  h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--qm-text-muted);
  }
}

.roles-summary-card__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.roles-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}

.role-stat-card {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.9rem 1rem;
  border-radius: 12px;
  background: color-mix(in srgb, var(--qm-bg-primary) 82%, transparent);
  border: 1px solid var(--qm-border);

  strong {
    font-size: 1.05rem;
    color: var(--qm-text-primary);
  }

  small {
    color: var(--qm-text-muted);
    font-size: 0.75rem;
  }
}

.role-stat-card__label {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.roles-summary-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1rem;
}

.roles-summary-card__hint {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  color: var(--qm-text-secondary);
  font-size: 0.8125rem;
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
}

.role-access-card {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.role-access-card__icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.role-access-card__icon--professional {
  background: color-mix(in srgb, var(--q-info) 18%, transparent);
  color: var(--q-info);
}

.role-access-card__icon--manager {
  background: color-mix(in srgb, var(--q-warning) 18%, transparent);
  color: var(--q-warning);
}

.role-access-card__content,
.settings-overlay__header > div {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  flex: 1;
}

.role-access-card__meta {
  color: var(--qm-text-secondary);
  font-size: 0.8125rem;
}

.role-access-card__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
  margin-top: 0.75rem;
}

.role-access-card__stat {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 0.95rem;
  border-radius: 12px;
  background: color-mix(in srgb, var(--qm-bg-primary) 82%, transparent);
  border: 1px solid var(--qm-border);

  span {
    font-size: 0.75rem;
    color: var(--qm-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.35px;
  }

  strong {
    font-size: 0.95rem;
    color: var(--qm-text-primary);
  }
}

.role-access-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.75rem;
}

.settings-overlay {
  position: absolute;
  inset: 0;
  z-index: 10;
  padding: 1rem;
  background: color-mix(in srgb, var(--qm-bg-primary) 82%, transparent);
  backdrop-filter: blur(8px);
}

.settings-overlay__sheet {
  min-height: 100%;
  padding: 1.5rem;
  overflow-y: auto;
}

.settings-overlay__back {
  align-self: flex-start;
  margin-left: -0.5rem;
}

.professional-request-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.professional-request-sheet__hint {
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  margin-top: 1rem;
  padding: 0.875rem 1rem;
  border-radius: 12px;
  background: var(--qm-bg-secondary);
  color: var(--qm-text-secondary);
}

.professional-request-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  margin-top: 1rem;
}

.request-history__title {
  font-size: 0.8125rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--qm-text-muted);
  margin-bottom: 0.75rem;
}

.settings-overlay-fade-enter-active,
.settings-overlay-fade-leave-active {
  transition: opacity 0.2s ease;
}

.settings-overlay-fade-enter-from,
.settings-overlay-fade-leave-to {
  opacity: 0;
}

.logout-section {
  padding-top: 1rem;
  border-top: 1px solid var(--qm-border);
}

.settings-list {
  display: flex;
  flex-direction: column;
}

.setting-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 0;
  border-bottom: 1px solid var(--qm-border);

  &:last-child {
    border-bottom: none;
  }
}

.setting-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: var(--qm-brand-light);
  color: var(--qm-brand);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.setting-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.setting-title {
  font-weight: 500;
  color: var(--qm-text-primary);
  font-size: 0.9375rem;
}

.setting-description {
  font-size: 0.8125rem;
  color: var(--qm-text-muted);
}

.brand-color-row {
  border-bottom: none !important;
  padding-bottom: 0.5rem !important;
}

.brand-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
  padding: 0 0 1rem 0;
}

.custom-hex-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--qm-border);
  flex-wrap: wrap;
}

.hex-input {
  flex: 1;
  min-width: 220px;
}

.hex-preview {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid var(--qm-border);
}

.reset-brand-btn {
  color: var(--qm-text-muted);
  font-size: 0.8125rem;
  margin-top: 2px;
}

.notifications-inbox {
  padding: 1.25rem;
  border-radius: 16px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--qm-brand) 3%, var(--qm-surface)), var(--qm-surface)),
    var(--qm-surface);
}

.notifications-inbox__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;

  h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    color: var(--qm-text-primary);
  }

  p {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--qm-text-muted);
  }
}

.notifications-inbox__actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
}

.notifications-filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.notifications-selection-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: color-mix(in srgb, var(--qm-brand) 8%, var(--qm-bg-secondary));
  border: 1px solid color-mix(in srgb, var(--qm-brand) 18%, var(--qm-border));

  :deep(.q-btn) {
    color: var(--qm-text-primary);
  }

  :deep(.q-btn.text-negative),
  :deep(.q-btn[class*='text-negative']) {
    color: var(--qm-error);
  }
}

.selection-count {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--qm-text-primary);
  letter-spacing: 0.02em;
}

.notifications-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(320px, 1fr);
  gap: 1rem;
  align-items: start;
}

.notifications-master,
.notifications-detail {
  min-width: 0;
}

.notifications-detail {
  position: sticky;
  top: 1rem;

  :deep(.notification-detail-panel) {
    max-height: calc(100vh - 7rem);
    overflow-y: auto;
    scrollbar-gutter: stable;
  }
}

.notifications-table-container {
  overflow-x: auto;
  border: 1px solid var(--qm-border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--qm-surface) 88%, var(--qm-bg-secondary));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--qm-brand) 3%, transparent);
}

.notifications-table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    padding: 0.875rem 1.5rem;
    text-align: left;
    vertical-align: middle;
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

.notification-row--unread {
  background: color-mix(in srgb, var(--qm-brand) 4%, transparent);
}

.notification-row--active {
  background: color-mix(in srgb, var(--qm-brand) 10%, transparent);
  box-shadow: inset 3px 0 0 var(--qm-brand);
}

.th-select {
  width: 56px;
}

.th-notification {
  min-width: 320px;
}

.th-type {
  min-width: 150px;
}

.th-date {
  min-width: 130px;
}

.th-status {
  min-width: 100px;
}

.notification-cell {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  justify-content: center;
}

.notification-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--qm-bg-secondary) 84%, var(--qm-surface));
  color: var(--qm-text-secondary);
  border: 1px solid color-mix(in srgb, var(--qm-brand) 8%, var(--qm-border));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notification-icon--unread {
  background: color-mix(in srgb, var(--qm-brand) 14%, var(--qm-bg-secondary));
  color: var(--qm-brand);
  border-color: color-mix(in srgb, var(--qm-brand) 26%, var(--qm-border));
}

.notification-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.notification-title {
  font-weight: 600;
  color: var(--qm-text-primary);
}

.notification-body {
  font-size: 0.8125rem;
  color: var(--qm-text-secondary);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notification-date-block {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.notification-date-primary {
  font-size: 0.8125rem;
  color: var(--qm-text-primary);
  font-weight: 500;
}

.notification-date-secondary {
  font-size: 0.75rem;
  color: var(--qm-text-muted);
}

.notification-row-actions {
  justify-content: flex-start;
}

.notifications-pagination {
  display: flex;
  justify-content: center;
  padding-top: 1rem;
}

.notifications-inbox :deep(.q-checkbox__inner) {
  color: color-mix(in srgb, var(--qm-text-secondary) 86%, var(--qm-border));
}

.notifications-inbox :deep(.q-checkbox__inner--truthy),
.notifications-inbox :deep(.q-checkbox__inner--indet) {
  color: var(--qm-brand);
}

.notifications-inbox :deep(.q-checkbox__bg) {
  border-radius: 6px;
}

.notifications-inbox :deep(.q-pagination .q-btn) {
  color: var(--qm-text-secondary);
}

.notifications-inbox :deep(.q-pagination .q-btn--active) {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--qm-brand) 22%, transparent);
}

.empty-state-sm,
.loading-state-sm {
  min-height: 240px;
  border-radius: 14px;
  border: 1px dashed color-mix(in srgb, var(--qm-brand) 10%, var(--qm-border));
  background: color-mix(in srgb, var(--qm-brand) 3%, var(--qm-bg-secondary));
}

.empty-state-sm {
  color: var(--qm-text-secondary);

  .q-icon {
    color: color-mix(in srgb, var(--qm-brand) 58%, var(--qm-text-secondary));
  }
}

.color-swatch {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 3px solid transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  transition: all 0.2s ease;
  box-shadow: var(--qm-shadow-sm);

  &:hover {
    transform: scale(1.15);
    box-shadow: var(--qm-shadow);
  }

  &.active {
    border-color: var(--qm-text-primary);
    transform: scale(1.1);
    box-shadow: var(--qm-shadow-lg);
  }
}

@media (max-width: 768px) {
  .panel-header {
    flex-direction: column;
    gap: 0.75rem;
  }

  .roles-summary-card__header,
  .role-access-card__top,
  .settings-overlay__header {
    flex-direction: column;
  }

  .roles-summary-card__footer,
  .role-access-card {
    width: 100%;
    flex-direction: column;
  }

  .professional-request-actions {
    justify-content: stretch;

    :deep(.q-btn) {
      width: 100%;
    }
  }

  .notifications-inbox__header {
    flex-direction: column;
  }

  .notifications-inbox__actions {
    width: 100%;
    justify-content: flex-end;
  }

  .notifications-selection-toolbar {
    align-items: stretch;

    :deep(.q-btn) {
      width: 100%;
    }
  }

  .notifications-workspace {
    grid-template-columns: 1fr;
  }

  .notifications-workspace--detail .notifications-master {
    display: none;
  }

  .notifications-detail {
    position: static;

    :deep(.notification-detail-panel) {
      max-height: none;
      overflow: visible;
    }
  }

  .custom-hex-row {
    align-items: stretch;
  }

  .settings-overlay {
    padding: 0.5rem;
  }

  .settings-overlay__sheet {
    padding: 1rem;
  }

  .notifications-table {
    th,
    td {
      padding: 0.875rem 1rem;
    }
  }
}
</style>
