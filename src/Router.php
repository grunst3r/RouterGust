<?php 
namespace GustRouter;


class Router extends Request {

    private $routers = [];
    private $domain = '';
    private $group = '';
    private $attributes = [];
    private $error;

    public function add(string $path, $callback, array $method){
        $this->routers[] = [
            'name' => null,
            'path' => ( $this->group ) ? rtrim($this->getDomain().$this->group.$path, '/\\') : $this->getDomain().$path,
            'callback' => $callback,
            'method' => $method
        ];
    }

    public function get(string $path, $callback){
        $this->add($path, $callback, ['GET']);
        return $this;
    }

    public function post(string $path, $callback){
        $this->add($path, $callback, ['POST']);
        return $this;
    }

    public function setDomain(string $domain){
        $this->domain = $domain;
    }

    private function getDomain(){
        return $this->domain ? $this->domain : $this->inDomain();
    }

    public function domain(string $domain, callable $callback ){
        $this->domain = $domain;
        $callback($callback);
        //$this->domain = '';
        return $this;
    }

    public function group(string $path, callable $callback ){
        $this->group = $path;
        $callback($callback);
        $this->group = '';
        return $this;
    }

    public function name(string $name){
        $lastRoute = end($this->routers);
        $lastRoute['name'] = $name;
        $this->routers[key($this->routers)] = $lastRoute;
        return $this;
    }

    public function middleware($middleware){
        $lastRoute = end($this->routers);
        $lastRoute['middleware'] = $middleware;
        $this->routers[key($this->routers)] = $lastRoute;
        return $this;
    }

    public function getVarsNames($path): array
    {
        preg_match_all('/{[^}]*}/', $path, $matches);
        return reset($matches) ?? [];
    }


    public function match(string $path, array $method): bool
    {
        $regex = $path;
        foreach ($this->getVarsNames($path) as $variable) {
            $varName = trim($variable, '{\}');
            $regex = str_replace($variable, '(?P<' . $varName . '>[^/]+)', $regex);
        }

        if ( in_array($this->getMethod(), $method) && preg_match('#^' . $regex . '$#sD', $this->getPath(), $matches) ) {
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $this->attributes[$key] = $value;
                }
            }
            return true;
        }else{
            return false;
        }

    }

    public function route($name,$parements){
        foreach($this->routers as $ruta){
            if($ruta['name'] == $name){
                $pattern = $ruta['path'];
                $pars = [];
                foreach($parements as $k => $parameto){
                    if(strpos($pattern, '{'.$k.'}') !== false){
                        $pattern = str_replace('{'.$k.'}', $parameto, $pattern);
                    }else{
                        $pars[$k] = $parameto;
                    }
                }

                $url = $pattern;
                if (!empty($pars)) {
                    $url .= '?' . http_build_query($pars);
                }
                return  !empty($pattern) ?  $this->https().$url : $this->https().$ruta['path'];
            }
        }
    }

    public function run(){

        $callback = null;
        foreach($this->routers as $router){
            if( $this->match($router['path'],$router['method']) ){
                $callback = $router['callback'];
                break;
            }
        }

        $oter_paramets = $this->getBody();
        $this->attributes = array_merge($this->attributes, $oter_paramets);
        
        if($callback){

            //middleware
            if(isset($router['middleware'])){
                $middleware = new $router['middleware'];
                return $middleware->handle();
            }

            if(!is_array($callback)){
                try {
                    $view = call_user_func_array( $callback, array_values($this->attributes) );
                    $this->view($view);
                } catch (\Throwable $th) {
                    $this->view($th->getMessage());
                }
            }else{
                $controller = new $callback[0];
                if (!is_callable($controller)) {
                    $controller =  [$controller, $callback[1]];
                }
                try {
                    $view = call_user_func_array($controller, array_values($this->attributes));
                    $this->view($view);
                } catch (\Throwable $th) {
                    $this->view($th->getMessage());
                }
            }
        }else{
            header("HTTP/1.0 404 Not Found");
            die($this->error);
        }
    }


    public function setError($error){
        $this->error = $error();
    }

    public function https(){
        if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
            $scheme = 'https://';
        else
            $scheme = 'http://';
        return $scheme;
    }

    public function view($view){
        if(is_string($view)){
            echo $view;
        }elseif(is_array($view)){
            header('Content-Type: application/json');
            echo json_encode($view);
        }elseif(is_object($view)){
            header('Content-Type: application/json');
            echo json_encode($view);
        }
    }


    // all routes
    public function getRouters(){
        return $this->routers;
    }

    // is_route
    public function isRoute($name){
        $uri = $this->getPath();
        foreach($this->routers as $router){
            if($router['name'] == $name){
                // /user/{id}/edit
                $pattern = $router['path'];
                $pars = [];
                foreach($this->getVarsNames($pattern) as $variable){
                    $varName = trim($variable, '{\}');
                    $pattern = str_replace($variable, '(?P<' . $varName . '>[^/]+)', $pattern);
                }
                if (preg_match('#^' . $pattern . '$#sD', $uri, $matches)) {
                    $values = array_filter($matches, static function ($key) {
                        return is_string($key);
                    }, ARRAY_FILTER_USE_KEY);
                    foreach ($values as $key => $value) {
                        $this->attributes[$key] = $value;
                    }
                    return true;
                }
            }
        }
        return false;
    }

}