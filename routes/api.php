<?php
/**
 * API Routes Configuration
 * 
 * Defines all API routes for the QueueMaster application.
 * Routes are organized by feature/resource with appropriate middleware.
 * 
 * This file is loaded by RouteBuilder as a fallback when routes table
 * is not available in the database.
 * 
 * @var \QueueMaster\Core\Router $router
 */

use QueueMaster\Controllers\AuthController;
use QueueMaster\Controllers\EstablishmentController;
use QueueMaster\Controllers\QueuesController;
use QueueMaster\Controllers\AppointmentsController;
use QueueMaster\Controllers\DashboardController;
use QueueMaster\Controllers\NotificationsController;
use QueueMaster\Stream\SseController;
use QueueMaster\Middleware\AuthMiddleware;
use QueueMaster\Middleware\RoleMiddleware;
use QueueMaster\Middleware\RateLimiter;

// API v1 routes group
$router->group('/api/v1', function ($router) {
    
    // ============================================================================
    // Authentication Routes (Public)
    // ============================================================================
    
    $router->group('/auth', function ($router) {
        
        // POST /api/v1/auth/register - Register new user
        $router->post('/register', function ($request) {
            $controller = new AuthController();
            $controller->register($request);
        }, [new RateLimiter(5, 60)]); // 5 requests per minute
        
        // POST /api/v1/auth/login - Login user
        $router->post('/login', function ($request) {
            $controller = new AuthController();
            $controller->login($request);
        }, [new RateLimiter(10, 60)]); // 10 requests per minute
        
        // POST /api/v1/auth/refresh - Refresh access token
        $router->post('/refresh', function ($request) {
            $controller = new AuthController();
            $controller->refresh($request);
        }, [new RateLimiter(20, 60)]);
        
    });
    
    // Protected auth routes (require authentication)
    $router->group('/auth', function ($router) {
        
        // GET /api/v1/auth/me - Get current user profile
        $router->get('/me', function ($request) {
            $controller = new AuthController();
            $controller->me($request);
        });
        
        // POST /api/v1/auth/logout - Logout user
        $router->post('/logout', function ($request) {
            $controller = new AuthController();
            $controller->logout($request);
        });
        
    }, [new AuthMiddleware()]);
    
    // ============================================================================
    // Establishment Routes
    // ============================================================================
    
    $router->group('/establishments', function ($router) {
        
        // GET /api/v1/establishments - List all establishments
        $router->get('', function ($request) {
            $controller = new EstablishmentController();
            $controller->list($request);
        });
        
        // GET /api/v1/establishments/{id} - Get single establishment
        $router->get('/{id}', function ($request) {
            $controller = new EstablishmentController();
            $controller->get($request);
        });
        
        // POST /api/v1/establishments - Create establishment (admin only)
        $router->post('', function ($request) {
            $controller = new EstablishmentController();
            $controller->create($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['admin'])]);
        
        // PUT /api/v1/establishments/{id} - Update establishment (admin only)
        $router->put('/{id}', function ($request) {
            $controller = new EstablishmentController();
            $controller->update($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['admin'])]);
        
        // DELETE /api/v1/establishments/{id} - Delete establishment (admin only)
        $router->delete('/{id}', function ($request) {
            $controller = new EstablishmentController();
            $controller->delete($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['admin'])]);
        
    });
    
    // ============================================================================
    // Queue Routes
    // ============================================================================
    
    $router->group('/queues', function ($router) {
        
        // GET /api/v1/queues - List queues
        $router->get('', function ($request) {
            $controller = new QueuesController();
            $controller->list($request);
        });
        
        // GET /api/v1/queues/{id} - Get single queue
        $router->get('/{id}', function ($request) {
            $controller = new QueuesController();
            $controller->get($request);
        });
        
        // POST /api/v1/queues/{id}/join - Join queue (authenticated)
        $router->post('/{id}/join', function ($request) {
            $controller = new QueuesController();
            $controller->join($request);
        }, [new AuthMiddleware(), new RateLimiter(10, 60)]);
        
        // GET /api/v1/queues/{id}/status - Get queue status
        $router->get('/{id}/status', function ($request) {
            $controller = new QueuesController();
            $controller->status($request);
        });
        
        // POST /api/v1/queues/{id}/leave - Leave queue (authenticated)
        $router->post('/{id}/leave', function ($request) {
            $controller = new QueuesController();
            $controller->leave($request);
        }, [new AuthMiddleware()]);
        
        // POST /api/v1/queues/{id}/call-next - Call next in queue (attendant/admin)
        $router->post('/{id}/call-next', function ($request) {
            $controller = new QueuesController();
            $controller->callNext($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['attendant', 'admin'])]);
        
        // POST /api/v1/queues - Create queue (admin only)
        $router->post('', function ($request) {
            $controller = new QueuesController();
            $controller->create($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['admin'])]);
        
        // PUT /api/v1/queues/{id} - Update queue (admin only)
        $router->put('/{id}', function ($request) {
            $controller = new QueuesController();
            $controller->update($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['admin'])]);
        
        // DELETE /api/v1/queues/{id} - Delete queue (admin only)
        $router->delete('/{id}', function ($request) {
            $controller = new QueuesController();
            $controller->delete($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['admin'])]);
        
    });
    
    // ============================================================================
    // Appointment Routes
    // ============================================================================
    
    $router->group('/appointments', function ($router) {
        
        // GET /api/v1/appointments - List user's appointments (authenticated)
        $router->get('', function ($request) {
            $controller = new AppointmentsController();
            $controller->list($request);
        }, [new AuthMiddleware()]);
        
        // GET /api/v1/appointments/{id} - Get single appointment (authenticated)
        $router->get('/{id}', function ($request) {
            $controller = new AppointmentsController();
            $controller->get($request);
        }, [new AuthMiddleware()]);
        
        // POST /api/v1/appointments - Create appointment (authenticated)
        $router->post('', function ($request) {
            $controller = new AppointmentsController();
            $controller->create($request);
        }, [new AuthMiddleware(), new RateLimiter(20, 60)]);
        
        // PUT /api/v1/appointments/{id} - Update appointment (authenticated)
        $router->put('/{id}', function ($request) {
            $controller = new AppointmentsController();
            $controller->update($request);
        }, [new AuthMiddleware()]);
        
        // DELETE /api/v1/appointments/{id} - Cancel appointment (authenticated)
        $router->delete('/{id}', function ($request) {
            $controller = new AppointmentsController();
            $controller->cancel($request);
        }, [new AuthMiddleware()]);
        
        // POST /api/v1/appointments/{id}/checkin - Check in for appointment
        $router->post('/{id}/checkin', function ($request) {
            $controller = new AppointmentsController();
            $controller->checkin($request);
        }, [new AuthMiddleware()]);
        
        // POST /api/v1/appointments/{id}/complete - Mark appointment complete (attendant/admin)
        $router->post('/{id}/complete', function ($request) {
            $controller = new AppointmentsController();
            $controller->complete($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['attendant', 'admin'])]);
        
        // POST /api/v1/appointments/{id}/no-show - Mark appointment no-show (attendant/admin)
        $router->post('/{id}/no-show', function ($request) {
            $controller = new AppointmentsController();
            $controller->noShow($request);
        }, [new AuthMiddleware(), new RoleMiddleware(['attendant', 'admin'])]);
        
    });
    
    // ============================================================================
    // Dashboard Routes (Attendant/Admin)
    // ============================================================================
    
    $router->group('/dashboard', function ($router) {
        
        // GET /api/v1/dashboard/overview - Dashboard overview stats
        $router->get('/overview', function ($request) {
            $controller = new DashboardController();
            $controller->overview($request);
        });
        
        // GET /api/v1/dashboard/queue-stats - Queue statistics
        $router->get('/queue-stats', function ($request) {
            $controller = new DashboardController();
            $controller->queueStats($request);
        });
        
        // GET /api/v1/dashboard/appointment-stats - Appointment statistics
        $router->get('/appointment-stats', function ($request) {
            $controller = new DashboardController();
            $controller->appointmentStats($request);
        });
        
    }, [new AuthMiddleware(), new RoleMiddleware(['attendant', 'admin'])]);
    
    // ============================================================================
    // Notification Routes
    // ============================================================================
    
    $router->group('/notifications', function ($router) {
        
        // GET /api/v1/notifications - List user's notifications (authenticated)
        $router->get('', function ($request) {
            $controller = new NotificationsController();
            $controller->list($request);
        });
        
        // GET /api/v1/notifications/{id} - Get single notification (authenticated)
        $router->get('/{id}', function ($request) {
            $controller = new NotificationsController();
            $controller->get($request);
        });
        
        // POST /api/v1/notifications/{id}/read - Mark notification as read
        $router->post('/{id}/read', function ($request) {
            $controller = new NotificationsController();
            $controller->markRead($request);
        });
        
        // DELETE /api/v1/notifications/{id} - Delete notification
        $router->delete('/{id}', function ($request) {
            $controller = new NotificationsController();
            $controller->delete($request);
        });
        
    }, [new AuthMiddleware()]);
    
    // ============================================================================
    // Server-Sent Events (SSE) Routes
    // ============================================================================
    
    $router->group('/streams', function ($router) {
        
        // GET /api/v1/streams/queue/{id} - Queue updates stream (authenticated)
        $router->get('/queue/{id}', function ($request) {
            $controller = new SseController();
            $controller->queueStream($request);
        }, [new AuthMiddleware()]);
        
        // GET /api/v1/streams/appointments - User appointments stream (authenticated)
        $router->get('/appointments', function ($request) {
            $controller = new SseController();
            $controller->appointmentsStream($request);
        }, [new AuthMiddleware()]);
        
        // GET /api/v1/streams/notifications - User notifications stream (authenticated)
        $router->get('/notifications', function ($request) {
            $controller = new SseController();
            $controller->notificationsStream($request);
        }, [new AuthMiddleware()]);
        
    });
    
}, [new RateLimiter(100, 60)]); // Global rate limit: 100 requests per minute

// Health check endpoint (no rate limiting)
$router->get('/health', function ($request) {
    \QueueMaster\Core\Response::success([
        'status' => 'healthy',
        'timestamp' => time(),
        'version' => '1.0.0',
    ]);
});
