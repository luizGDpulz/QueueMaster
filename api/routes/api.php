<?php
/**
 * API Routes Configuration
 * 
 * Defines all API routes for the QueueMaster application.
 * Routes are organized by feature/resource with appropriate middleware.
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
use QueueMaster\Middleware\BusinessMiddleware;
use QueueMaster\Middleware\RateLimiter;
use QueueMaster\Core\Response;

// ============================================================================
// API Documentation (Swagger UI)
// ============================================================================

// GET /swagger - Serve Swagger UI
$router->get('/swagger', function ($request) {
    $swaggerPath = __DIR__ . '/../public/swagger/index.html';
    if (file_exists($swaggerPath)) {
        header('Content-Type: text/html');
        readfile($swaggerPath);
        exit;
    }
    Response::notFound('Swagger UI not found');
});

// GET /swagger/{file} - Serve Swagger static files
$router->get('/swagger/{file}', function ($request) {
    $file = $request->getParam('file');
    $filePath = __DIR__ . '/../public/swagger/' . $file;

    // Security: prevent directory traversal
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

// GET /api/docs - Redirect to Swagger UI
$router->get('/api/docs', function ($request) {
    header('Location: /swagger');
    exit;
});

// GET /docs - Alternative shortcut
$router->get('/docs', function ($request) {
    header('Location: /swagger');
    exit;
});

// GET /api/openapi.yaml - Serve OpenAPI spec directly
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

// GET /api/openapi.json - Serve OpenAPI spec as JSON
$router->get('/api/openapi.json', function ($request) {
    $specPath = __DIR__ . '/../public/swagger/openapi.yaml';
    if (file_exists($specPath)) {
        $yaml = file_get_contents($specPath);
        $data = yaml_parse($yaml);
        if ($data === false) {
            // Fallback: serve raw YAML with JSON content type
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

// API v1 routes group
$router->group('/api/v1', function ($router) {

    // ============================================================================
    // Health Check / API Status Route
    // ============================================================================

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
                ]
            ]);
        }
        );

        // ============================================================================
        // Authentication Routes (Public)
        // ============================================================================
    
        $router->group('/auth', function ($router) {

            // POST /api/v1/auth/google - Authenticate with Google OAuth
            $router->post('/google', function ($request) {
                    $controller = new AuthController();
                    $controller->google($request);
                }
                    , [new RateLimiter(10, 60)]); // 10 requests per minute
        
                // POST /api/v1/auth/refresh - Refresh access token
                $router->post('/refresh', function ($request) {
                    $controller = new AuthController();
                    $controller->refresh($request);
                }
                    , [new RateLimiter(20, 60)]);

            }
            );

            // Protected auth routes (require authentication)
            $router->group('/auth', function ($router) {

            // GET /api/v1/auth/me - Get current user profile
            $router->get('/me', function ($request) {
                    $controller = new AuthController();
                    $controller->me($request);
                }
                );

                // POST /api/v1/auth/logout - Logout user
                $router->post('/logout', function ($request) {
                    $controller = new AuthController();
                    $controller->logout($request);
                }
                );

                // GET /api/v1/auth/dev-token - Generate token for Swagger (admin only)
                $router->get('/dev-token', function ($request) {
                    $controller = new AuthController();
                    $controller->devToken($request);
                }
                );

            }
                , [new AuthMiddleware()]);

            // ============================================================================
            // Establishment Routes
            // ============================================================================
        
            $router->group('/establishments', function ($router) {

            // GET /api/v1/establishments - List all establishments
            $router->get('', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/establishments/{id} - Get single establishment
                $router->get('/{id}', function ($request) {
                    $controller = new EstablishmentController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                );

                // GET /api/v1/establishments/{id}/services - Get establishment services
                $router->get('/{id}/services', function ($request) {
                    $controller = new EstablishmentController();
                    $id = (int)$request->getParam('id');
                    $controller->getServices($request, $id);
                }
                );

                // GET /api/v1/establishments/{id}/professionals - Get establishment professionals
                $router->get('/{id}/professionals', function ($request) {
                    $controller = new EstablishmentController();
                    $id = (int)$request->getParam('id');
                    $controller->getProfessionals($request, $id);
                }
                );

                // POST /api/v1/establishments - Create establishment (manager/admin)
                $router->post('', function ($request) {
                    $controller = new EstablishmentController();
                    $controller->create($request);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/establishments/{id} - Update establishment (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new EstablishmentController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/establishments/{id} - Delete establishment (manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new EstablishmentController();
                    $id = (int)$request->getParam('id');
                    $controller->delete($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ============================================================================
            // Services Routes
            // ============================================================================
        
            $router->group('/services', function ($router) {

            // GET /api/v1/services - List all services
            $router->get('', function ($request) {
                    $controller = new ServicesController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/services/{id} - Get single service
                $router->get('/{id}', function ($request) {
                    $controller = new ServicesController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                );

                // POST /api/v1/services - Create service (manager/admin)
                $router->post('', function ($request) {
                    $controller = new ServicesController();
                    $controller->create($request);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/services/{id} - Update service (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new ServicesController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/services/{id} - Delete service (manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new ServicesController();
                    $id = (int)$request->getParam('id');
                    $controller->delete($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ============================================================================
            // Professionals Routes
            // ============================================================================
        
            $router->group('/professionals', function ($router) {

            // GET /api/v1/professionals - List all professionals
            $router->get('', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/professionals/{id} - Get single professional
                $router->get('/{id}', function ($request) {
                    $controller = new ProfessionalsController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                );

                // GET /api/v1/professionals/{id}/appointments - Get professional's appointments
                $router->get('/{id}/appointments', function ($request) {
                    $controller = new ProfessionalsController();
                    $id = (int)$request->getParam('id');
                    $controller->getAppointments($request, $id);
                }
                );

                // POST /api/v1/professionals - Create professional (manager/admin)
                $router->post('', function ($request) {
                    $controller = new ProfessionalsController();
                    $controller->create($request);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/professionals/{id} - Update professional (manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new ProfessionalsController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/professionals/{id} - Delete professional (manager/admin)
                $router->delete('/{id}', function ($request) {
                    $controller = new ProfessionalsController();
                    $id = (int)$request->getParam('id');
                    $controller->delete($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

            }
            );

            // ============================================================================
            // Queue Routes
            // ============================================================================
        
            $router->group('/queues', function ($router) {

            // GET /api/v1/queues - List queues
            $router->get('', function ($request) {
                    $controller = new QueuesController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/queues/{id} - Get single queue
                $router->get('/{id}', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                );

                // POST /api/v1/queues/{id}/join - Join queue (authenticated, supports access_code)
                $router->post('/{id}/join', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->join($request, $id);
                }
                    , [new AuthMiddleware(), new RateLimiter(10, 60)]);

                // GET /api/v1/queues/{id}/status - Get queue status
                $router->get('/{id}/status', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->status($request, $id);
                }
                );

                // POST /api/v1/queues/{id}/leave - Leave queue (authenticated)
                $router->post('/{id}/leave', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->leave($request, $id);
                }
                    , [new AuthMiddleware()]);

                // POST /api/v1/queues/{id}/call-next - Call next in queue (attendant/professional/manager/admin)
                $router->post('/{id}/call-next', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->callNext($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // POST /api/v1/queues/{id}/generate-code - Generate join code (manager/admin)
                $router->post('/{id}/generate-code', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->generateCode($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // GET /api/v1/queues/{id}/access-codes - List access codes (manager/admin)
                $router->get('/{id}/access-codes', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->listCodes($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // GET /api/v1/queues/{id}/access-codes/{codeId} - Get single access code (manager/admin)
                $router->get('/{id}/access-codes/{codeId}', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $codeId = (int)$request->getParam('codeId');
                    $controller->getCode($request, $id, $codeId);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/queues/{id}/access-codes/{codeId}/deactivate - Deactivate code (manager/admin)
                $router->post('/{id}/access-codes/{codeId}/deactivate', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $codeId = (int)$request->getParam('codeId');
                    $controller->deactivateCode($request, $id, $codeId);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/queues/{id}/access-codes/{codeId} - Delete code (manager/admin)
                $router->delete('/{id}/access-codes/{codeId}', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $codeId = (int)$request->getParam('codeId');
                    $controller->deleteCode($request, $id, $codeId);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/queues - Create queue (professional/manager/admin)
                $router->post('', function ($request) {
                    $controller = new QueuesController();
                    $controller->create($request);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // PUT /api/v1/queues/{id} - Update queue (professional/manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // DELETE /api/v1/queues/{id} - Delete queue (admin only)
                $router->delete('/{id}', function ($request) {
                    $controller = new QueuesController();
                    $id = (int)$request->getParam('id');
                    $controller->delete($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['admin'])]);

            }
            );

            // ============================================================================
            // Appointment Routes
            // ============================================================================
        
            $router->group('/appointments', function ($router) {

            // GET /api/v1/appointments - List user's appointments (authenticated)
            $router->get('', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->list($request);
                }
                    , [new AuthMiddleware()]);

                // GET /api/v1/appointments/available-slots - Get available time slots
                // IMPORTANT: Must be registered BEFORE /{id} to avoid being shadowed
                $router->get('/available-slots', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->availableSlots($request);
                }
                );

                // GET /api/v1/appointments/{id} - Get single appointment (authenticated)
                $router->get('/{id}', function ($request) {
                    $controller = new AppointmentsController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                    , [new AuthMiddleware()]);

                // POST /api/v1/appointments - Create appointment (authenticated)
                $router->post('', function ($request) {
                    $controller = new AppointmentsController();
                    $controller->create($request);
                }
                    , [new AuthMiddleware(), new RateLimiter(20, 60)]);

                // PUT /api/v1/appointments/{id} - Update appointment (authenticated)
                $router->put('/{id}', function ($request) {
                    $controller = new AppointmentsController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                    , [new AuthMiddleware()]);

                // DELETE /api/v1/appointments/{id} - Cancel appointment (authenticated)
                $router->delete('/{id}', function ($request) {
                    $controller = new AppointmentsController();
                    $id = (int)$request->getParam('id');
                    $controller->cancel($request, $id);
                }
                    , [new AuthMiddleware()]);

                // POST /api/v1/appointments/{id}/check-in - Check in for appointment
                $router->post('/{id}/checkin', function ($request) {
                    $controller = new AppointmentsController();
                    $id = (int)$request->getParam('id');
                    $controller->checkIn($request, $id);
                }
                    , [new AuthMiddleware()]);

                // POST /api/v1/appointments/{id}/complete - Mark appointment complete (professional/manager/admin)
                $router->post('/{id}/complete', function ($request) {
                    $controller = new AppointmentsController();
                    $id = (int)$request->getParam('id');
                    $controller->complete($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // POST /api/v1/appointments/{id}/no-show - Mark appointment no-show (professional/manager/admin)
                $router->post('/{id}/no-show', function ($request) {
                    $controller = new AppointmentsController();
                    $id = (int)$request->getParam('id');
                    $controller->noShow($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

            }
            );

            // ============================================================================
            // Dashboard Routes (Attendant/Professional/Manager/Admin)
            // ============================================================================
        
            $router->group('/dashboard', function ($router) {

            // GET /api/v1/dashboard/queue-overview - Queue overview stats
            $router->get('/queue-overview', function ($request) {
                    $controller = new DashboardController();
                    $controller->queueOverview($request);
                }
                );

                // GET /api/v1/dashboard/appointments-list - Appointments list
                $router->get('/appointments-list', function ($request) {
                    $controller = new DashboardController();
                    $controller->appointmentsList($request);
                }
                );


            }
                , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

            // ============================================================================
            // User Management Routes (Admin)
            // ============================================================================
        
            $router->group('/users', function ($router) {

            // GET /api/v1/users - List users (role-based visibility)
            $router->get('', function ($request) {
                    $controller = new UsersController();
                    $controller->list($request);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['professional', 'manager', 'admin'])]);

                // GET /api/v1/users/{id} - Get single user (user themselves or admin)
                $router->get('/{id}', function ($request) {
                    $controller = new UsersController();
                    $id = (int)$request->getParam('id');
                    $controller->show($request, $id);
                }
                    , [new AuthMiddleware()]);

                // POST /api/v1/users - Create user (admin only)
                $router->post('', function ($request) {
                    $controller = new UsersController();
                    $controller->create($request);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['admin'])]);

                // PUT /api/v1/users/{id} - Update user (user themselves or admin)
                $router->put('/{id}', function ($request) {
                    $controller = new UsersController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                    , [new AuthMiddleware()]);

                // DELETE /api/v1/users/{id} - Delete user (admin only)
                $router->delete('/{id}', function ($request) {
                    $controller = new UsersController();
                    $id = (int)$request->getParam('id');
                    $controller->delete($request, $id);
                }
                    , [new AuthMiddleware(), new RoleMiddleware(['admin'])]);

                // GET /api/v1/users/{id}/queue-entries - Get user's queue entries
                $router->get('/{id}/queue-entries', function ($request) {
                    $controller = new UsersController();
                    $id = (int)$request->getParam('id');
                    $controller->getQueueEntries($request, $id);
                }
                    , [new AuthMiddleware()]);

                // GET /api/v1/users/{id}/appointments - Get user's appointments
                $router->get('/{id}/appointments', function ($request) {
                    $controller = new UsersController();
                    $id = (int)$request->getParam('id');
                    $controller->getAppointments($request, $id);
                }
                    , [new AuthMiddleware()]);

                // GET /api/v1/users/{id}/avatar - Get user's avatar image
                $router->get('/{id}/avatar', function ($request) {
                    $controller = new UsersController();
                    $id = (int)$request->getParam('id');
                    $controller->getAvatar($request, $id);
                }
                    , [new AuthMiddleware()]);

            }
            );

            // ============================================================================
            // Notification Routes
            // ============================================================================
        
            $router->group('/notifications', function ($router) {

            // GET /api/v1/notifications - List user's notifications (authenticated)
            $router->get('', function ($request) {
                    $controller = new NotificationsController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/notifications/unread-count - Get unread notification count
                $router->get('/unread-count', function ($request) {
                    $controller = new NotificationsController();
                    $controller->unreadCount($request);
                }
                );

                // POST /api/v1/notifications/mark-all-read - Mark all as read
                $router->post('/mark-all-read', function ($request) {
                    $controller = new NotificationsController();
                    $controller->markAllRead($request);
                }
                );

                // GET /api/v1/notifications/{id} - Get single notification (authenticated)
                $router->get('/{id}', function ($request) {
                    $controller = new NotificationsController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                );

                // POST /api/v1/notifications/{id}/read - Mark notification as read
                $router->post('/{id}/read', function ($request) {
                    $controller = new NotificationsController();
                    $id = (int)$request->getParam('id');
                    $controller->markRead($request, $id);
                }
                );

                // DELETE /api/v1/notifications/{id} - Delete notification
                $router->delete('/{id}', function ($request) {
                    $controller = new NotificationsController();
                    $id = (int)$request->getParam('id');
                    $controller->delete($request, $id);
                }
                );

            }
                , [new AuthMiddleware()]);

            // ============================================================================
            // Business Routes (Multi-Tenant)
            // ============================================================================
        
            $router->group('/businesses', function ($router) {

            // GET /api/v1/businesses - List businesses (admin=all, manager=own)
            $router->get('', function ($request) {
                    $controller = new BusinessController();
                    $controller->list($request);
                }
                );

                // GET /api/v1/businesses/{id} - Get single business
                $router->get('/{id}', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $controller->get($request, $id);
                }
                );

                // POST /api/v1/businesses - Create business (manager/admin)
                $router->post('', function ($request) {
                    $controller = new BusinessController();
                    $controller->create($request);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // PUT /api/v1/businesses/{id} - Update business (owner/manager/admin)
                $router->put('/{id}', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $controller->update($request, $id);
                }
                );

                // GET /api/v1/businesses/{id}/establishments - List establishments for business
                $router->get('/{id}/establishments', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $controller->listEstablishments($request, $id);
                }
                );

                // POST /api/v1/businesses/{id}/establishments - Create establishment in business
                $router->post('/{id}/establishments', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $controller->createEstablishment($request, $id);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // GET /api/v1/businesses/{id}/users - List business users
                $router->get('/{id}/users', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $controller->listUsers($request, $id);
                }
                );

                // POST /api/v1/businesses/{id}/users - Add user to business
                $router->post('/{id}/users', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $controller->addUser($request, $id);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // DELETE /api/v1/businesses/{id}/users/{userId} - Remove user from business
                $router->delete('/{id}/users/{userId}', function ($request) {
                    $controller = new BusinessController();
                    $id = (int)$request->getParam('id');
                    $userId = (int)$request->getParam('userId');
                    $controller->removeUser($request, $id, $userId);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // POST /api/v1/businesses/{id}/invitations - Invite professional to business
                $router->post('/{id}/invitations', function ($request) {
                    $controller = new InvitationsController();
                    $id = (int)$request->getParam('id');
                    $controller->invite($request, $id);
                }
                    , [new RoleMiddleware(['manager', 'admin'])]);

                // GET /api/v1/businesses/{id}/invitations - List invitations for business
                $router->get('/{id}/invitations', function ($request) {
                    $controller = new InvitationsController();
                    $id = (int)$request->getParam('id');
                    $controller->listForBusiness($request, $id);
                }
                );

                $router->post('/{id}/join-request', function ($request) {
                    $controller = new InvitationsController();
                    $id = (int)$request->getParam('id');
                    $controller->joinRequest($request, $id);
                }
                    , [new RoleMiddleware(['professional'])]);

            }
                , [new AuthMiddleware()]);

            // ============================================================================
            // Invitation Management Routes
            // ============================================================================
        
            $router->group('/invitations', function ($router) {

            // GET /api/v1/invitations - List my invitations (received + sent)
            $router->get('', function ($request) {
                    $controller = new InvitationsController();
                    $controller->list($request);
                }
                );

                // POST /api/v1/invitations/{id}/accept - Accept invitation
                $router->post('/{id}/accept', function ($request) {
                    $controller = new InvitationsController();
                    $id = (int)$request->getParam('id');
                    $controller->accept($request, $id);
                }
                );

                // POST /api/v1/invitations/{id}/reject - Reject invitation
                $router->post('/{id}/reject', function ($request) {
                    $controller = new InvitationsController();
                    $id = (int)$request->getParam('id');
                    $controller->reject($request, $id);
                }
                );

                // POST /api/v1/invitations/{id}/cancel - Cancel invitation (sender)
                $router->post('/{id}/cancel', function ($request) {
                    $controller = new InvitationsController();
                    $id = (int)$request->getParam('id');
                    $controller->cancelInvitation($request, $id);
                }
                );

            }
                , [new AuthMiddleware()]);

            // ============================================================================
            // Admin Routes
            // ============================================================================
        
            $router->group('/admin', function ($router) {

            // GET /api/v1/admin/audit-logs - List audit logs (admin sees all, manager sees own businesses)
            $router->get('/audit-logs', function ($request) {
                    $controller = new AdminController();
                    $controller->auditLogs($request);
                }
                );

                // GET /api/v1/admin/audit-logs/filters - Get available filter options
                $router->get('/audit-logs/filters', function ($request) {
                    $controller = new AdminController();
                    $controller->auditLogFilters($request);
                }
                );

                // GET /api/v1/admin/subscriptions - List subscriptions
                $router->get('/subscriptions', function ($request) {
                    $controller = new AdminController();
                    $controller->subscriptions($request);
                }
                );

                // GET /api/v1/admin/subscriptions/{id} - Get single subscription
                $router->get('/subscriptions/{id}', function ($request) {
                    $controller = new AdminController();
                    $id = (int)$request->getParam('id');
                    $controller->getSubscription($request, $id);
                }
                );

                // POST /api/v1/admin/subscriptions - Create subscription (admin only)
                $router->post('/subscriptions', function ($request) {
                    $controller = new AdminController();
                    $controller->createSubscription($request);
                }
                );

                // PUT /api/v1/admin/subscriptions/{id} - Update subscription (admin only)
                $router->put('/subscriptions/{id}', function ($request) {
                    $controller = new AdminController();
                    $id = (int)$request->getParam('id');
                    $controller->updateSubscription($request, $id);
                }
                );

                // DELETE /api/v1/admin/subscriptions/{id} - Delete subscription (admin only)
                $router->delete('/subscriptions/{id}', function ($request) {
                    $controller = new AdminController();
                    $id = (int)$request->getParam('id');
                    $controller->deleteSubscription($request, $id);
                }
                );

                // GET /api/v1/admin/plans - List plans
                $router->get('/plans', function ($request) {
                    $controller = new AdminController();
                    $controller->plans($request);
                }
                );

                // GET /api/v1/admin/plans/{id} - Get single plan
                $router->get('/plans/{id}', function ($request) {
                    $controller = new AdminController();
                    $id = (int)$request->getParam('id');
                    $controller->getPlan($request, $id);
                }
                );

                // POST /api/v1/admin/plans - Create plan (admin only)
                $router->post('/plans', function ($request) {
                    $controller = new AdminController();
                    $controller->createPlan($request);
                }
                );

                // PUT /api/v1/admin/plans/{id} - Update plan (admin only)
                $router->put('/plans/{id}', function ($request) {
                    $controller = new AdminController();
                    $id = (int)$request->getParam('id');
                    $controller->updatePlan($request, $id);
                }
                );

                // DELETE /api/v1/admin/plans/{id} - Delete plan (admin only)
                $router->delete('/plans/{id}', function ($request) {
                    $controller = new AdminController();
                    $id = (int)$request->getParam('id');
                    $controller->deletePlan($request, $id);
                }
                );

            }
                , [new AuthMiddleware(), new RoleMiddleware(['admin', 'manager'])]);

            // ============================================================================
            // Server-Sent Events (SSE) Routes
            // ============================================================================
        
            $router->group('/streams', function ($router) {

            // GET /api/v1/streams/queue/{id} - Queue updates stream (authenticated)
            $router->get('/queue/{id}', function ($request) {
                    $controller = new SseController();
                    $controller->queueStream($request);
                }
                    , [new AuthMiddleware()]);

                // GET /api/v1/streams/appointments - User appointments stream (authenticated)
                $router->get('/appointments', function ($request) {
                    $controller = new SseController();
                    $controller->appointmentsStream($request);
                }
                    , [new AuthMiddleware()]);

                // GET /api/v1/streams/notifications - User notifications stream (authenticated)
                $router->get('/notifications', function ($request) {
                    $controller = new SseController();
                    $controller->notificationsStream($request);
                }
                    , [new AuthMiddleware()]);

            }
            );




        
}, [new RateLimiter(100, 60)]); // Global rate limit: 100 requests per minute

// Health check endpoint (no rate limiting)
$router->get('/health', function ($request) {
    Response::success([
        'status' => 'healthy',
        'timestamp' => time(),
        'version' => '1.0.0',
    ]);
});
