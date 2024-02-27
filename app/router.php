<?php

class Router {
    protected $routes = [];

    public function add_route(string $method, string $url, closure $target) {
        $this->routes[$method][$url] = $target;
    }

    public function match_route() {
        $method = $_SERVER['REQUEST_METHOD'];
        $url = $_SERVER['REQUEST_URI'];
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route_url => $target) {
                $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $route_url);
                if (preg_match('#^' . $pattern . '$#', $url, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    call_user_func_array($target, $params);
                    exit;
                }
            }
        }
        http_response_code(404);
        exit;
    }
}