const routes = [
  // ===== ROTAS DE AUTENTICAÇÃO =====
  // Usam AuthLayout (sem menu lateral)
  {
    path: '/',
    component: () => import('layouts/AuthLayout.vue'),
    children: [
      {
        path: '',
        redirect: '/login'
      },
      {
        path: 'login',
        name: 'login',
        component: () => import('pages/LoginPage.vue')
      },
      {
        path: 'auth/loading',
        name: 'auth-loading',
        component: () => import('pages/AuthLoadingPage.vue')
      }
    ]
  },

  // ===== ROTAS DO APP (AUTENTICADAS) =====
  // Usam MainLayout (com menu lateral)
  {
    path: '/app',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('pages/DashboardPage.vue')
      },
      {
        path: 'businesses',
        name: 'businesses',
        component: () => import('pages/businesses/BusinessesPage.vue')
      },
      {
        path: 'businesses/:id',
        name: 'business-detail',
        component: () => import('pages/businesses/BusinessDetailPage.vue')
      },
      {
        path: 'queues',
        name: 'queues',
        component: () => import('pages/queues/QueuesPage.vue')
      },
      {
        path: 'queues/:id',
        name: 'queue-detail',
        component: () => import('pages/queues/QueueDetailPage.vue')
      },
      {
        path: 'appointments',
        name: 'appointments',
        component: () => import('pages/appointments/AppointmentsPage.vue')
      },
      {
        path: 'appointments/:id',
        name: 'appointment-detail',
        component: () => import('pages/appointments/AppointmentDetailPage.vue')
      },
      {
        path: 'establishments',
        name: 'establishments',
        component: () => import('pages/establishments/EstablishmentsPage.vue')
      },
      {
        path: 'establishments/:id',
        name: 'establishment-detail',
        component: () => import('pages/establishments/EstablishmentDetailPage.vue')
      },
      {
        path: 'establishments/:id/services/:serviceId',
        name: 'service-detail',
        component: () => import('pages/establishments/ServiceDetailPage.vue')
      },
      {
        path: 'establishments/:id/professionals/:professionalId',
        name: 'professional-detail',
        component: () => import('pages/establishments/ProfessionalDetailPage.vue')
      },
      {
        path: 'admin',
        name: 'admin-panel',
        component: () => import('pages/admin/AdminPanelPage.vue')
      },
      {
        path: 'admin/users/:id',
        name: 'user-detail',
        component: () => import('pages/admin/UserDetailPage.vue')
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('pages/SettingsPage.vue')
      }
    ]
  },

  // Rota 404 - Página não encontrada
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue')
  }
]

export default routes
