<?php

/**
 * API Routes Configuration
 * Maps HTTP methods and URIs to controller actions
 */

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Response.php';
require_once __DIR__ . '/../app/Utils/Validator.php';
require_once __DIR__ . '/../app/Utils/Sanitizer.php';

class Router
{
    private $routes = [];
    private $method;
    private $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $this->parseUri();
    }

    /**
     * Parse request URI
     */
    private function parseUri()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remove leading/trailing slashes
        $uri = trim($uri, '/');
        
        return $uri;
    }

    /**
     * Register GET route
     */
    public function get($path, $controller, $action)
    {
        $this->addRoute('GET', $path, $controller, $action);
    }

    /**
     * Register POST route
     */
    public function post($path, $controller, $action)
    {
        $this->addRoute('POST', $path, $controller, $action);
    }

    /**
     * Register PUT route
     */
    public function put($path, $controller, $action)
    {
        $this->addRoute('PUT', $path, $controller, $action);
    }

    /**
     * Register DELETE route
     */
    public function delete($path, $controller, $action)
    {
        $this->addRoute('DELETE', $path, $controller, $action);
    }

    /**
     * Add route to routes array
     */
    private function addRoute($method, $path, $controller, $action)
    {
        $path = trim($path, '/');
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    /**
     * Dispatch request to appropriate controller
     */
    public function dispatch()
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->method) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            
            if (preg_match($pattern, $this->uri, $matches)) {
                array_shift($matches); // Remove full match
                
                $controllerFile = __DIR__ . '/../app/Controllers/' . $route['controller'] . '.php';
                
                if (!file_exists($controllerFile)) {
                    Response::serverError("Controller not found");
                }
                
                require_once $controllerFile;
                
                $controller = new $route['controller']();
                $action = $route['action'];
                
                if (!method_exists($controller, $action)) {
                    Response::serverError("Action not found");
                }
                
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        Response::notFound("Endpoint not found");
    }

    /**
     * Convert route path to regex pattern
     */
    private function convertToRegex($path)
    {
        // Replace {param} with regex capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path);
        return '#^' . $pattern . '$#';
    }
}

// Initialize router
$router = new Router();

// ==================== Public API Routes ====================

// Health check
$router->get('', 'HealthController', 'index');
$router->get('health', 'HealthController', 'check');

// Products
$router->get('products', 'ProductController', 'index');
$router->get('products/{id}', 'ProductController', 'show');
$router->get('products/search', 'ProductController', 'search');

// Categories
$router->get('categories', 'ProductController', 'categories');
$router->get('categories/{id}/products', 'ProductController', 'productsByCategory');

// Brands
$router->get('brands', 'ProductController', 'brands');
$router->get('brands/{id}/products', 'ProductController', 'productsByBrand');

// Orders (Customer)
$router->post('orders', 'OrderController', 'create');
$router->get('orders/{orderId}', 'OrderController', 'show');
$router->post('orders/{orderId}/payment-success', 'OrderController', 'paymentSuccess');
$router->post('orders/{orderId}/payment-failed', 'OrderController', 'paymentFailed');

// Auth (Customer)
$router->post('auth/register', 'AuthController', 'register');
$router->post('auth/login', 'AuthController', 'login');

// ==================== Admin API Routes ====================

// Admin Auth
$router->post('admin/login', 'AdminController', 'login');

// Admin Dashboard
$router->get('admin/dashboard', 'AdminController', 'dashboard');

// Admin Orders
$router->get('admin/orders', 'AdminController', 'orders');
$router->get('admin/orders/{id}', 'AdminController', 'orderDetail');
$router->put('admin/orders/{id}/status', 'AdminController', 'updateOrderStatus');

// Admin Products
$router->get('admin/products', 'AdminController', 'products');
$router->put('admin/products/{id}', 'AdminController', 'updateProduct');

// Admin Pricing
$router->get('admin/pricing', 'AdminController', 'getPricing');
$router->put('admin/pricing/global', 'AdminController', 'updateGlobalMarkup');
$router->put('admin/pricing/category/{id}', 'AdminController', 'updateCategoryMarkup');

// Admin Sync
$router->post('admin/sync/products', 'AdminController', 'syncProducts');
$router->get('admin/sync/status', 'AdminController', 'syncStatus');

// Admin Vendor Orders
$router->post('admin/vendor/create-sales-order', 'AdminController', 'createSalesOrder');

return $router;

