# QueueMaster File Tree

Visão da estrutura atual do repositório.

```text
├── 📁 api
│   ├── 📁 migrations
│   │   ├── 📄 0001_schema_down.sql
│   │   └── 📄 0001_schema_up.sql
│   ├── 📁 public
│   │   ├── 📁 swagger
│   │   │   ├── ⚙️ .htaccess
│   │   │   ├── 🌐 index.html
│   │   │   ├── ⚙️ openapi.yaml
│   │   │   ├── 📄 script.js
│   │   │   └── 🎨 styles.css
│   │   ├── ⚙️ .htaccess
│   │   └── 🐘 index.php
│   ├── 📁 routes
│   │   └── 🐘 api.php
│   ├── 📁 scripts
│   │   ├── 🐘 cleanup_tokens.php
│   │   ├── 🐘 migrate.php
│   │   └── 🐘 seed.php
│   ├── 📁 seeds
│   │   └── 📄 seed_sample_data.sql
│   ├── 📁 src
│   │   ├── 📁 Builders
│   │   │   └── 🐘 QueryBuilder.php
│   │   ├── 📁 Controllers
│   │   │   ├── 🐘 AdminController.php
│   │   │   ├── 🐘 AppointmentsController.php
│   │   │   ├── 🐘 AuthController.php
│   │   │   ├── 🐘 BusinessController.php
│   │   │   ├── 🐘 DashboardController.php
│   │   │   ├── 🐘 EstablishmentController.php
│   │   │   ├── 🐘 InvitationsController.php
│   │   │   ├── 🐘 NotificationsController.php
│   │   │   ├── 🐘 ProfessionalsController.php
│   │   │   ├── 🐘 QueuesController.php
│   │   │   ├── 🐘 ReportsController.php
│   │   │   ├── 🐘 ServicesController.php
│   │   │   └── 🐘 UsersController.php
│   │   ├── 📁 Core
│   │   │   ├── 🐘 Database.php
│   │   │   ├── 🐘 Request.php
│   │   │   ├── 🐘 Response.php
│   │   │   └── 🐘 Router.php
│   │   ├── 📁 Middleware
│   │   │   ├── 🐘 AuthMiddleware.php
│   │   │   ├── 🐘 BusinessMiddleware.php
│   │   │   ├── 🐘 RateLimiter.php
│   │   │   ├── 🐘 RoleMiddleware.php
│   │   │   └── 🐘 TokenMiddleware.php
│   │   ├── 📁 Models
│   │   │   ├── 🐘 Appointment.php
│   │   │   ├── 🐘 AuditLog.php
│   │   │   ├── 🐘 Business.php
│   │   │   ├── 🐘 BusinessInvitation.php
│   │   │   ├── 🐘 BusinessSubscription.php
│   │   │   ├── 🐘 BusinessUser.php
│   │   │   ├── 🐘 Establishment.php
│   │   │   ├── 🐘 EstablishmentUser.php
│   │   │   ├── 🐘 FcmToken.php
│   │   │   ├── 🐘 Notification.php
│   │   │   ├── 🐘 NotificationPreference.php
│   │   │   ├── 🐘 Plan.php
│   │   │   ├── 🐘 Professional.php
│   │   │   ├── 🐘 ProfessionalEstablishment.php
│   │   │   ├── 🐘 ProfessionalService.php
│   │   │   ├── 🐘 Queue.php
│   │   │   ├── 🐘 QueueAccessCode.php
│   │   │   ├── 🐘 QueueEntry.php
│   │   │   ├── 🐘 QueueProfessional.php
│   │   │   ├── 🐘 QueueService.php
│   │   │   ├── 🐘 RefreshToken.php
│   │   │   ├── 🐘 Service.php
│   │   │   └── 🐘 User.php
│   │   ├── 📁 Services
│   │   │   ├── 🐘 AppointmentService.php
│   │   │   ├── 🐘 AuditService.php
│   │   │   ├── 🐘 ContextAccessService.php
│   │   │   ├── 🐘 NotificationService.php
│   │   │   ├── 🐘 ProfessionalMembershipService.php
│   │   │   ├── 🐘 QueueReportsService.php
│   │   │   ├── 🐘 QueueService.php
│   │   │   ├── 🐘 QuotaService.php
│   │   │   └── 🐘 ReportsService.php
│   │   ├── 📁 Stream
│   │   │   └── 🐘 SseController.php
│   │   └── 📁 Utils
│   │       ├── 🐘 Logger.php
│   │       └── 🐘 Validator.php
│   ├── 📁 tests
│   │   ├── 📁 phpunit
│   │   │   ├── 🐘 AppointmentConflictTest.php
│   │   │   ├── 🐘 BusinessHierarchyTest.php
│   │   │   ├── 🐘 CallNextConcurrencyTest.php
│   │   │   └── 🐘 QueueConcurrencyTest.php
│   │   ├── 📝 README.md
│   │   ├── 📄 test_password_change_security.ps1
│   │   ├── 📄 test_password_change_security.sh
│   │   └── 📄 test_password_curl.ps1
│   ├── ⚙️ .env.example
│   ├── ⚙️ composer.json
│   ├── 📄 composer.phar
│   └── 📄 phpunit.xml.dist
├── 📁 docker
│   ├── 📁 api
│   │   ├── 🐳 Dockerfile
│   │   └── ⚙️ apache.conf
│   ├── 📁 mariadb
│   │   └── 📄 init.sql
│   └── ⚙️ docker-compose.dev.yml
├── 📁 docs
│   ├── 📁 postman
│   │   ├── 📝 POSTMAN_CACHE_GUIDE.md
│   │   ├── 📝 POSTMAN_GUIDE.md
│   │   └── ⚙️ postman_collection.json
│   ├── 📁 structure
│   │   └── 📝 FILETREE.md
│   ├── 📝 API_DOCUMENTATION.md
│   ├── 📝 ARCHITECTURE_REFACTORING.md
│   ├── 📝 CRUD_COMPLETE_SUMMARY.md
│   ├── 📝 DOCKER_DEPLOY.md
│   ├── 📝 GOOGLE_OAUTH_FLOW.md
│   ├── 📝 IMPLEMENTATION_SUMMARY.md
│   ├── 📝 JWT_AUTH_FLOW.md
│   ├── 📝 LOCAL_DEPLOYMENT_XAMPP.md
│   ├── 📝 POSTMAN_GUIDE.md
│   ├── 📝 PROPOSE.md
│   ├── 📝 PROPOSE_EN.md
│   ├── 📝 QUICK_GUIDE_MODELS.md
│   ├── 📝 REFRESH_TOKEN_GUIDE.md
│   ├── 📝 SECURITY.md
│   └── 📝 SWAGGER_GUIDE.md
├── 📁 mobile
│   └── 📄 placeholder.txt
├── 📁 public
│   ├── ⚙️ .htaccess
│   └── 🐘 index.php
├── 📁 scripts
│   ├── ⚙️ .env.deploy.example
│   └── 📄 deploy.sh
├── 📁 web
│   ├── 📁 queuemaster
│   │   ├── 📁 .quasar
│   │   │   ├── 📁 dev-spa
│   │   │   │   ├── 📄 app.js
│   │   │   │   ├── 📄 client-entry.js
│   │   │   │   ├── 📄 client-prefetch.js
│   │   │   │   └── 📄 quasar-user-options.js
│   │   │   ├── 📁 prod-spa
│   │   │   │   ├── 📄 app.js
│   │   │   │   ├── 📄 client-entry.js
│   │   │   │   ├── 📄 client-prefetch.js
│   │   │   │   └── 📄 quasar-user-options.js
│   │   │   ├── 📄 feature-flags.d.ts
│   │   │   ├── 📄 pinia.d.ts
│   │   │   ├── 📄 quasar.d.ts
│   │   │   └── ⚙️ tsconfig.json
│   │   ├── 📁 public
│   │   │   ├── 📁 icons
│   │   │   │   ├── 🖼️ android-chrome-192x192.png
│   │   │   │   ├── 🖼️ android-chrome-512x512.png
│   │   │   │   ├── 🖼️ apple-icon-180x180.png
│   │   │   │   ├── 🖼️ favicon-128x128.png
│   │   │   │   ├── 🖼️ favicon-16x16.png
│   │   │   │   ├── 🖼️ favicon-32x32.png
│   │   │   │   ├── 🖼️ favicon-96x96.png
│   │   │   │   └── 🖼️ google.svg
│   │   │   ├── 📄 favicon.ico
│   │   │   └── 🖼️ favicon.svg
│   │   ├── 📁 scripts
│   │   │   └── 📄 generate-icons.js
│   │   ├── 📁 src
│   │   │   ├── 📁 assets
│   │   │   │   ├── 🖼️ logo_dark.svg
│   │   │   │   └── 🖼️ logo_light.svg
│   │   │   ├── 📁 boot
│   │   │   │   ├── ⚙️ .gitkeep
│   │   │   │   └── 📄 axios.js
│   │   │   ├── 📁 components
│   │   │   │   ├── 📁 ui
│   │   │   │   │   ├── 📄 ContextMenu.vue
│   │   │   │   │   ├── 📄 StatusPill.vue
│   │   │   │   │   └── 📄 UserProfilePreview.vue
│   │   │   │   └── 📄 AppSidebar.vue
│   │   │   ├── 📁 composables
│   │   │   │   ├── 📄 useBreadcrumb.js
│   │   │   │   └── 📄 useNotificationsCenter.js
│   │   │   ├── 📁 css
│   │   │   │   ├── 🎨 app.scss
│   │   │   │   ├── 🎨 detail-page.scss
│   │   │   │   └── 🎨 quasar.variables.scss
│   │   │   ├── 📁 layouts
│   │   │   │   ├── 📄 AuthLayout.vue
│   │   │   │   └── 📄 MainLayout.vue
│   │   │   ├── 📁 pages
│   │   │   │   ├── 📁 admin
│   │   │   │   │   ├── 📄 AdminPanelPage.vue
│   │   │   │   │   └── 📄 UserDetailPage.vue
│   │   │   │   ├── 📁 appointments
│   │   │   │   │   ├── 📄 AppointmentDetailPage.vue
│   │   │   │   │   └── 📄 AppointmentsPage.vue
│   │   │   │   ├── 📁 businesses
│   │   │   │   │   ├── 📄 BusinessDetailPage.vue
│   │   │   │   │   └── 📄 BusinessesPage.vue
│   │   │   │   ├── 📁 establishments
│   │   │   │   │   ├── 📄 EstablishmentDetailPage.vue
│   │   │   │   │   ├── 📄 EstablishmentsPage.vue
│   │   │   │   │   ├── 📄 ProfessionalDetailPage.vue
│   │   │   │   │   └── 📄 ServiceDetailPage.vue
│   │   │   │   ├── 📁 queues
│   │   │   │   │   ├── 📄 QueueDetailPage.vue
│   │   │   │   │   └── 📄 QueuesPage.vue
│   │   │   │   ├── 📁 reports
│   │   │   │   │   └── 📄 QueueReportsPage.vue
│   │   │   │   ├── 📄 AuthLoadingPage.vue
│   │   │   │   ├── 📄 DashboardPage.vue
│   │   │   │   ├── 📄 ErrorNotFound.vue
│   │   │   │   ├── 📄 LoginPage.vue
│   │   │   │   └── 📄 SettingsPage.vue
│   │   │   ├── 📁 router
│   │   │   │   ├── 📄 index.js
│   │   │   │   └── 📄 routes.js
│   │   │   ├── 📁 stores
│   │   │   │   └── 📄 index.js
│   │   │   ├── 📁 utils
│   │   │   │   └── 📄 brand.js
│   │   │   └── 📄 App.vue
│   │   ├── ⚙️ .editorconfig
│   │   ├── ⚙️ .env.example
│   │   ├── ⚙️ .gitignore
│   │   ├── ⚙️ .npmrc
│   │   ├── ⚙️ .prettierrc.json
│   │   ├── 📝 README.md
│   │   ├── 📄 eslint.config.js
│   │   ├── 🌐 index.html
│   │   ├── ⚙️ jsconfig.json
│   │   ├── ⚙️ package-lock.json
│   │   ├── ⚙️ package.json
│   │   ├── 📄 postcss.config.js
│   │   └── 📄 quasar.config.js
│   └── ⚙️ .gitkeep
├── ⚙️ .gitignore
├── 📝 LICENSE.md
├── 📝 README.md
└── ⚙️ docker-compose.yml
```
