<?php
/**
 * API Routes Configuration
 *
 * Security Architecture:
 * - ALL routes under /api/v1 require a valid JWT (AuthMiddleware on group level).
 * - The only exceptions are /api/v1/auth/google and /api/v1/auth/refresh,
 *   registered explicitly BEFORE the main group so they remain public.
 * - Role-based access is layered on top via RoleMiddleware per sub-group or route.
 * - Global rate limit: 100 req/min on the entire /api/v1 group.
 *
 * Role hierarchy (most → least privileged):
 *   admin > manager > professional > client
 *
 * @var \QueueMaster\Core\Router $router
 */

use QueueMaster\Controllers\AuthController;
use QueueMaster\Controllers\EstablishmentController;
use QueueMaster\Controllers\ServicesController;
use QueueMaster\Controllers\ProfessionalsController;
use QueueMaster\Controllers\QueuesController;
use QueueMaster\Controllers\AppointmentsController;
use QueueMaster\Controllers\DashboardController;
use QueueMaster\Controllers\NotificationsController;
use QueueMaster\Controllers\UsersController;
use QueueMaster\Controllers\BusinessController;
use QueueMaster\Controllers\AdminController;
use QueueMaster\Controllers\InvitationsController;
use QueueMaster\Stream\SseController;
use QueueMaster\Middleware\AuthMiddleware;
use QueueMaster\Middleware\RoleMiddleware;
use QueueMaster\Middleware\RateLimiter;
use QueueMaster\Core\Response;

// ============================================================================
// API Documentation (Swagger UI) — completely public
// ============================================================================

$router->get('/swagger', function ($request) {
    $swaggerPath = __DIR__ . '/../public/swagger/index.html';
    if (file_exists($swaggerPath)) {
        header('Content-Type: text/html');
        readfile($swaggerPath);
        exit;
    }
    Response::notFound('Swagger UI not found');
});

$router->get('/swagger/{file}', function ($request) {
    $file = $request->getParam('file');
    $filePath = __DIR__ . '/../public/swagger/' . $file;
    $realPath = realpath($filePath);
    $swaggerDir = realpath(__DIR__ . '/../public/swagger');

    if ($realPath && str_starts_with($realPath, $swaggerDir) && is_file($realPath)) {
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'yaml' => 'application/x-yaml',
            'json' => 'application/json',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
        ];
        $ext = pathinfo($realPath, PATHINFO_EXTENSION);
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Access-Control-Allow-Origin: *');
        readfile($realPath);
        exit;
    }

    Response::notFound('File not found');
});

$router->get('/api/docs', function ($request) {
    header('Location: /swagger');
    exit;
});

$router->get('/docs', function ($request) {
    header('Location: /swagger');
    exit;
});

$router->get('/api/openapi.yaml', function ($request) {
    $specPath = __DIR__ . '/../public/swagger/openapi.yaml';
    if (file_exists($specPath)) {
        header('Content-Type: application/x-yaml');
        header('Access-Control-Allow-Origin: *');
        echo file_get_contents($specPath);
        exit;
    }
    Response::notFound('OpenAPI specification not found');
});

$router->get('/api/openapi.json', function ($request) {
    $specPath = __DIR__ . '/../public/swagger/openapi.yaml';
    if (file_exists($specPath)) {
        $yaml = file_get_contents($specPath);
        $data = yaml_parse($yaml);
        if ($data === false) {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            echo json_encode(['error' => 'YAML parsing not available, use /api/openapi.yaml']);
            exit;
        }
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    Response::notFound('OpenAPI specification not found');
});

// ============================================================================
// Health check — public, no rate limiting
// ============================================================================

$router->get('/health', function ($request) {
    Response::success([
        'status' => 'healthy',
        'timestamp' => time(),
        'version' => '1.0.0',
    ]);
});

// ============================================================================
// PUBLIC AUTH ROUTES
// These two endpoints are the only ones that do NOT require a JWT.
// They are registered before the main group so the router matches them first.
// ============================================================================

// POST /api/v1/auth/google — Authenticate with Google OAuth (unauthenticated)
$router->post('/api/v1/auth/google', function ($request) {
    $controller = new AuthController();
    $controller->google($request);
}, [new RateLimiter(10, 60)]);

// POST /api/v1/auth/refresh — Refresh access token via httpOnly cookie (unauthenticated)
$router->post('/api/v1/auth/refresh', function ($request) {
    $controller = new AuthController();
    $controller->refresh($request);
}, [new RateLimiter(20, 60)]);


// ============================================================================
// ALL AUTHENTICATED ROUTES  (/api/v1)
//
// AuthMiddleware is applied at GROUP level — every route below requires a
// valid JWT. Additional RoleMiddleware is layered per sub-group or route.
// Global rate limit: 100 requests / 60 seconds.
// ============================================================================

$router->group('/api/v1', function ($router) {

    // ------------------------------------------------------------------------
    // API Status  (authenticated)
    // ------------------------------------------------------------------------

    $router->get('/status', function ($request) {
            Response::success([
                'message' => 'QueueMaster API is running',
                'version' => '1.0.0',
                'timestamp' => date('c'),
                'environment' => $_ENV['APP_ENV'] ?? 'production',
                'endpoints' => [
                    'auth' => '/api/v1/auth',
                    'establishments' => '/api/v1/establishments',
                    'queues' => '/api/v1/queues',
                    'appointments' => '/api/v1/appointments',
                    'dashboard' => '/api/v1/dashboard',
                    'notifications' => '/api/v1/notifications',
                    'streams' => '/api/v1/streams',
                ],
            ]);
        }
        );

        // ========================================================================
        // Auth Routes (protected — JWT already required by group)
        // ========================================================================
    
        $router->group('/auth', function ($router) {

            // GET /api/v1/auth/me — Get current user profile
            $router->get('/me', function ($request) {
                    $controller = new AuthController();
                    $controller->me($request);
                }
                );

                // POST /api/v1/auth/logout — Logout (clear cookies)
                $router->post('/logout', function ($request) {
                    $controller = new AuthController();
                    $controller->logout($request);
                }
                );

                // GET /api/v1/auth/dev-token — Generate Swagger token (admin only)
                $router->get('/dev-token', function ($request) {
                    $controller = new AuthController();
                    $controller->devToken($request);
                }
                    , [new RoleMiddleware(['admin'])]);

            }
            );

            // ========================================================================
            // Establishment Routes
            // ========================================================================
        
            $router->group('/establishments', function ($router) {

            // GET /api/v1/establishments — List all establishments (all authenticated users)
            $router->get('', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/establishments/{id} — Get single establishment
                $router->get('/{id}', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/establishments/{id}/services — Services of an establishment
                $router->get('/{id}/services', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->getServices($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/establishments/{id}/professionals — Professionals of an establishment
                $router->get('/{id}/professionals', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->getProfessionals($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/establishments — Create establishment (manager/admin)
                $router->post('', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/establishments/{id} — Update establishment (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/establishments/{id} — Delete establishment (manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->delete($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ========================================================================
            // Services Routes
            // ========================================================================
        
            $router->group('/services', function ($router) {

            // GET /api/v1/services — List all services
            $router->get('', function ($request) {
                    $controller = new ServicesController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/services/{id} — Get single service
                $router->get('/{id}', function ($request) {
                    $controller = new ServicesController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/services — Create service (manager/admin)
                $router->post('', function ($request) {
                    $controller = new ServicesController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/services/{id} — Update service (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new ServicesController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/services/{id} — Delete service (manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new ServicesController();
                    $controller->delete($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ========================================================================
            // Professionals Routes
            // ========================================================================
        
            $router->group('/professionals', function ($router) {

            // GET /api/v1/professionals — List all professionals
            $router->get('', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/professionals/{id} — Get single professional
                $router->get('/{id}', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/professionals/{id}/appointments — Get professional's appointments
                $router->get('/{id}/appointments', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->getAppointments($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/professionals — Create professional (manager/admin)
                $router->post('', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/professionals/{id} — Update professional (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/professionals/{id} — Delete professional (manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->delete($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ========================================================================
            // Queue Routes
            // ========================================================================
        
            $router->group('/queues', function ($router) {

            // GET /api/v1/queues — List queues
            $router->get('', function ($request) {
                    $controller = new QueuesController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/queues/{id} — Get single queue
                $router->get('/{id}', function ($request) {
                    $controller = new QueuesController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/queues/{id}/status — Get queue status
                $router->get('/{id}/status', function ($request) {
                    $controller = new QueuesController();
                    $controller->status($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/queues/{id}/access-codes — List access codes (manager/admin)
                $router->get('/{id}/access-codes', function ($request) {
                    $controller = new QueuesController();
                    $controller->listCodes($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // GET /api/v1/queues/{id}/access-codes/{codeId} — Get single code (manager/admin)
                $router->get('/{id}/access-codes/{codeId}', function ($request) {
                    $controller = new QueuesController();
                    $controller->getCode($request, (int)$request->getParam('id'), (int)$request->getParam('codeId'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/queues — Create queue (professional/manager/admin)
                $router->post('', function ($request) {
                    $controller = new QueuesController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // POST /api/v1/queues/{id}/join — Join queue (all authenticated, extra rate limit)
                $router->post('/{id}/join', function ($request) {
                    $controller = new QueuesController();
                    $controller->join($request, (int)$request->getParam('id'));
                }
                    , [new RateLimiter(10, 60)]);

                // POST /api/v1/queues/{id}/leave — Leave queue (all authenticated)
                $router->post('/{id}/leave', function ($request) {
                    $controller = new QueuesController();
                    $controller->leave($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/queues/{id}/call-next — Call next in queue (professional/manager/admin)
                $router->post('/{id}/call-next', function ($request) {
                    $controller = new QueuesController();
                    $controller->callNext($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // POST /api/v1/queues/{id}/generate-code — Generate join code (manager/admin)
                $router->post('/{id}/generate-code', function ($request) {
                    $controller = new QueuesController();
                    $controller->generateCode($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/queues/{id}/access-codes/{codeId}/deactivate — Deactivate code (manager/admin)
                $router->post('/{id}/access-codes/{codeId}/deactivate', function ($request) {
                    $controller = new QueuesController();
                    $controller->deactivateCode($request, (int)$request->getParam('id'), (int)$request->getParam('codeId'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/queues/{id} — Update queue (professional/manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new QueuesController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // DELETE /api/v1/queues/{id} — Delete queue (professional/manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new QueuesController();
                    $controller->delete($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // DELETE /api/v1/queues/{id}/access-codes/{codeId} — Delete access code (manager/admin)
                $router->delete('/{id}/access-codes/{codeId}', function ($request) {
                    $controller = new QueuesController();
                    $controller->deleteCode($request, (int)$request->getParam('id'), (int)$request->getParam('codeId'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ========================================================================
            // Appointment Routes
            // ========================================================================
        
            $router->group('/appointments', function ($router) {

            // GET /api/v1/appointments — List user's appointments
            $router->get('', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/appointments/available-slots — Available time slots (authenticated)
                // NOTE: registered before /{id} to avoid route shadowing
                $router->get('/available-slots', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->availableSlots($request);
                }
                );

                // GET /api/v1/appointments/{id} — Get single appointment
                $router->get('/{id}', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/appointments — Create appointment (extra rate limit)
                $router->post('', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->create($request);
                }
                    , [new RateLimiter(20, 60)]);

                // PUT /api/v1/appointments/{id} — Update appointment
                $router->put('/{id}', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                );

                // DELETE /api/v1/appointments/{id} — Cancel appointment
                $router->delete('/{id}', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->cancel($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/appointments/{id}/checkin — Check in for appointment
                $router->post('/{id}/checkin', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->checkIn($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/appointments/{id}/complete — Mark complete (professional/manager/admin)
                $router->post('/{id}/complete', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->complete($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // POST /api/v1/appointments/{id}/no-show — Mark no-show (professional/manager/admin)
                $router->post('/{id}/no-show', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->noShow($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

            }
            );

            // ========================================================================
            // Dashboard Routes (professional/manager/admin)
            // ========================================================================
        
            $router->group('/dashboard', function ($router) {

            // GET /api/v1/dashboard/queue-overview — Queue overview stats
            $router->get('/queue-overview', function ($request) {
                    $controller = new DashboardController();
                    $controller->queueOverview($request);
                }
                );

                // GET /api/v1/dashboard/appointments-list — Daily appointments list
                $router->get('/appointments-list', function ($request) {
                    $controller = new DashboardController();
                    $controller->appointmentsList($request);
                }
                );

            }
                , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

            // ========================================================================
            // User Management Routes
            // ========================================================================
        
            $router->group('/users', function ($router) {

            // GET /api/v1/users — List users (professional sees clients, manager sees more, admin sees all)
            $router->get('', function ($request) {
                    $controller = new UsersController();
                    $controller->list($request);
                }
                    , [new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // GET /api/v1/users/{id} — Get single user (self or permitted roles)
                $router->get('/{id}', function ($request) {
                    $controller = new UsersController();
                    $controller->show($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/users/{id}/queue-entries — Get user's queue entries
                $router->get('/{id}/queue-entries', function ($request) {
                    $controller = new UsersController();
                    $controller->getQueueEntries($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/users/{id}/appointments — Get user's appointments
                $router->get('/{id}/appointments', function ($request) {
                    $controller = new UsersController();
                    $controller->getAppointments($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/users/{id}/avatar — Get user's avatar image
                $router->get('/{id}/avatar', function ($request) {
                    $controller = new UsersController();
                    $controller->getAvatar($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/users — Create user (admin only)
                $router->post('', function ($request) {
                    $controller = new UsersController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['admin'])]);

                // PUT /api/v1/users/{id} — Update user (self or admin)
                $router->put('/{id}', function ($request) {
                    $controller = new UsersController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                );

                // DELETE /api/v1/users/{id} — Delete user (admin only)
                $router->delete('/{id}', function ($request) {
                    $controller = new UsersController();
                    $controller->delete($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

            }
            );

            // ========================================================================
            // Notification Routes
            // ========================================================================
        
            $router->group('/notifications', function ($router) {

            // GET /api/v1/notifications — List user's notifications
            $router->get('', function ($request) {
                    $controller = new NotificationsController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/notifications/unread-count — Get unread count
                $router->get('/unread-count', function ($request) {
                    $controller = new NotificationsController();
                    $controller->unreadCount($request);
                }
                );

                // POST /api/v1/notifications/mark-all-read — Mark all as read
                $router->post('/mark-all-read', function ($request) {
                    $controller = new NotificationsController();
                    $controller->markAllRead($request);
                }
                );

                // GET /api/v1/notifications/{id} — Get single notification
                $router->get('/{id}', function ($request) {
                    $controller = new NotificationsController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/notifications/{id}/read — Mark notification as read
                $router->post('/{id}/read', function ($request) {
                    $controller = new NotificationsController();
                    $controller->markRead($request, (int)$request->getParam('id'));
                }
                );

                // DELETE /api/v1/notifications/{id} — Delete notification
                $router->delete('/{id}', function ($request) {
                    $controller = new NotificationsController();
                    $controller->delete($request, (int)$request->getParam('id'));
                }
                );

            }
            );

            // ========================================================================
            // Business Routes (Multi-Tenant)
            // ========================================================================
        
            $router->group('/businesses', function ($router) {

            // GET /api/v1/businesses — List businesses (all authenticated; admin=all, manager=own)
            $router->get('', function ($request) {
                    $controller = new BusinessController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/businesses/{id} — Get single business
                $router->get('/{id}', function ($request) {
                    $controller = new BusinessController();
                    $controller->get($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/businesses/{id}/establishments — List establishments for business
                $router->get('/{id}/establishments', function ($request) {
                    $controller = new BusinessController();
                    $controller->listEstablishments($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/businesses/{id}/users — List users in business
                $router->get('/{id}/users', function ($request) {
                    $controller = new BusinessController();
                    $controller->listUsers($request, (int)$request->getParam('id'));
                }
                );

                // GET /api/v1/businesses/{id}/invitations — List invitations for business (manager/admin)
                $router->get('/{id}/invitations', function ($request) {
                    $controller = new InvitationsController();
                    $controller->listForBusiness($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/businesses — Create business (manager/admin, plan-limited)
                $router->post('', function ($request) {
                    $controller = new BusinessController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/businesses/{id} — Update business (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new BusinessController();
                    $controller->update($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/businesses/{id}/establishments — Create establishment in business (manager/admin)
                $router->post('/{id}/establishments', function ($request) {
                    $controller = new BusinessController();
                    $controller->createEstablishment($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/businesses/{id}/users — Add user to business (manager/admin)
                $router->post('/{id}/users', function ($request) {
                    $controller = new BusinessController();
                    $controller->addUser($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/businesses/{id}/users/{userId} — Remove user from business (manager/admin)
                $router->delete('/{id}/users/{userId}', function ($request) {
                    $controller = new BusinessController();
                    $controller->removeUser($request, (int)$request->getParam('id'), (int)$request->getParam('userId'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/businesses/{id}/invitations — Invite professional to business (manager/admin)
                $router->post('/{id}/invitations', function ($request) {
                    $controller = new InvitationsController();
                    $controller->invite($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/businesses/{id}/join-request — Professional requests to join a business
                $router->post('/{id}/join-request', function ($request) {
                    $controller = new InvitationsController();
                    $controller->joinRequest($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['professional'])]);

            }
            );

            // ========================================================================
            // Invitation Management Routes
            // ========================================================================
        
            $router->group('/invitations', function ($router) {

            // GET /api/v1/invitations — List my invitations (received + sent)
            $router->get('', function ($request) {
                    $controller = new InvitationsController();
                    $controller->list($request);
                }
                );

                // POST /api/v1/invitations/{id}/accept — Accept invitation
                $router->post('/{id}/accept', function ($request) {
                    $controller = new InvitationsController();
                    $controller->accept($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/invitations/{id}/reject — Reject invitation
                $router->post('/{id}/reject', function ($request) {
                    $controller = new InvitationsController();
                    $controller->reject($request, (int)$request->getParam('id'));
                }
                );

                // POST /api/v1/invitations/{id}/cancel — Cancel invitation (sender only)
                $router->post('/{id}/cancel', function ($request) {
                    $controller = new InvitationsController();
                    $controller->cancelInvitation($request, (int)$request->getParam('id'));
                }
                );

            }
            );

            // ========================================================================
            // Admin Routes
            //
            // C4: Routes are explicitly scoped by role.
            // - Audit logs: admin + manager (read-only overview)
            // - Subscriptions & Plans (CRUD): admin only
            // ========================================================================
        
            $router->group('/admin', function ($router) {

            // ---- Audit Logs (admin + manager can read) --------------------------
    
            // GET /api/v1/admin/audit-logs — List audit logs
            $router->get('/audit-logs', function ($request) {
                    $controller = new AdminController();
                    $controller->auditLogs($request);
                }
                    , [new RoleMiddleware(['admin', 'manager'])]);

                // GET /api/v1/admin/audit-logs/filters — Get available filter options
                $router->get('/audit-logs/filters', function ($request) {
                    $controller = new AdminController();
                    $controller->auditLogFilters($request);
                }
                    , [new RoleMiddleware(['admin', 'manager'])]);

                // ---- Subscriptions (admin only) -------------------------------------
        
                // GET /api/v1/admin/subscriptions — List subscriptions
                $router->get('/subscriptions', function ($request) {
                    $controller = new AdminController();
                    $controller->subscriptions($request);
                }
                    , [new RoleMiddleware(['admin'])]);

                // GET /api/v1/admin/subscriptions/{id} — Get single subscription
                $router->get('/subscriptions/{id}', function ($request) {
                    $controller = new AdminController();
                    $controller->getSubscription($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

                // POST /api/v1/admin/subscriptions — Create subscription
                $router->post('/subscriptions', function ($request) {
                    $controller = new AdminController();
                    $controller->createSubscription($request);
                }
                    , [new RoleMiddleware(['admin'])]);

                // PUT /api/v1/admin/subscriptions/{id} — Update subscription
                $router->put('/subscriptions/{id}', function ($request) {
                    $controller = new AdminController();
                    $controller->updateSubscription($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

                // DELETE /api/v1/admin/subscriptions/{id} — Delete subscription
                $router->delete('/subscriptions/{id}', function ($request) {
                    $controller = new AdminController();
                    $controller->deleteSubscription($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

                // ---- Plans (admin only) ---------------------------------------------
        
                // GET /api/v1/admin/plans — List plans
                $router->get('/plans', function ($request) {
                    $controller = new AdminController();
                    $controller->plans($request);
                }
                    , [new RoleMiddleware(['admin'])]);

                // GET /api/v1/admin/plans/{id} — Get single plan
                $router->get('/plans/{id}', function ($request) {
                    $controller = new AdminController();
                    $controller->getPlan($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

                // POST /api/v1/admin/plans — Create plan
                $router->post('/plans', function ($request) {
                    $controller = new AdminController();
                    $controller->createPlan($request);
                }
                    , [new RoleMiddleware(['admin'])]);

                // PUT /api/v1/admin/plans/{id} — Update plan
                $router->put('/plans/{id}', function ($request) {
                    $controller = new AdminController();
                    $controller->updatePlan($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

                // DELETE /api/v1/admin/plans/{id} — Delete plan
                $router->delete('/plans/{id}', function ($request) {
                    $controller = new AdminController();
                    $controller->deletePlan($request, (int)$request->getParam('id'));
                }
                    , [new RoleMiddleware(['admin'])]);

            }
            );

            // ========================================================================
            // Server-Sent Events (SSE) Routes
            // ========================================================================
        
            $router->group('/streams', function ($router) {

            // GET /api/v1/streams/queue/{id} — Queue updates stream
            $router->get('/queue/{id}', function ($request) {
                    $controller = new SseController();
                    $controller->queueStream($request);
                }
                );

                // GET /api/v1/streams/appointments — User appointments stream
                $router->get('/appointments', function ($request) {
                    $controller = new SseController();
                    $controller->appointmentsStream($request);
                }
                );

                // GET /api/v1/streams/notifications — User notifications stream
                $router->get('/notifications', function ($request) {
                    $controller = new SseController();
                    $controller->notificationsStream($request);
                }
                );

            }
            );
        }, [new AuthMiddleware(), new RateLimiter(100, 60)]);
