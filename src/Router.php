<?php 
namespace GustRouter;


class Router extends Request {

    private $routers = [];
    private $domain = '';
    private $group = '';
    private $attributes = [];
    private $error;

    public function add(string $name, string $path, $callback, array $method){
        $this->routers[] = [
            'name' => $name,
            'path' => ( $this->group ) ? rtrim($this->getDomain().$this->group.$path, '/\\') : $this->getDomain().$path,
            'callback' => $callback,
            'method' => $method
        ];
    }

    private function getDomain(){
        return $this->domain ? $this->domain :$this->inDomain();
    }

    public function domain(string $domain, callable $callback ){
        $this->domain = $domain;
        $callback($callback);
        $this->domain = '';
        return $this;
    }

    public function group(string $path, callable $callback ){
        $this->group = $path;
        $callback($callback);
        $this->group = '';
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

        if (in_array($this->getMethod(), $method) && preg_match('#^' . $regex . '$#sD', $this->getPath(), $matches)) {
            $values = array_filter($matches, static function ($key) {
                return is_string($key);
            }, ARRAY_FILTER_USE_KEY);
            foreach ($values as $key => $value) {
                $this->attributes[$key] = $value;
            }
            return true;
        }
        return false;
    }

    public function route($name,$parements){
        foreach($this->routers as $ruta){
            if($ruta['name'] == $name){
                $pattern = $ruta['path'];
                foreach($parements as $k => $parameto){
                    $pattern = preg_replace('/{'.$k.'}/', $parameto, $pattern);
                }
                return !empty($pattern) ? $this->https().$pattern : $this->https().$ruta['path'];
            }
        }
    }

    public function run(){
        $callback = null;
        foreach($this->routers as $router){
            if( $this->match($router['path'],$router['method']) ){
                $callback = $router['callback'];
            }
        }
        if($callback){
            if(is_callable($callback)){
                echo call_user_func( $callback, ...array_values($this->attributes) );
            }else{
                $controller = new $callback[0];
                if (!is_callable($controller)) {
                    $controller =  [$controller, $callback[1]];
                }
                echo $controller(...array_values($this->attributes));
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

}