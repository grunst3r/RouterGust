# RouterGust
 Router PHP
Install
```
composer require luigu/router-gust:dev-main
```

```php

require('vendor/autoload.php');

use GustRouter\Request;
use GustRouter\Router;
$rutas = new Router;


function route(string $name, array $parements = []){ // Helper Global
    global $rutas;
    return $rutas->route($name,$parements);
}

class IndexController {

    public function index(){
        return '<h1>Hello world!!</h1> '.route('blog',['slug' => 'avatar', 'id' => 894654]);
    }

    public function blog($slug,$id){
        return $slug.'....'.$id;
    }

}

$rutas->domain('localhost:8080', function() use($rutas){
    $rutas->add('home', '/' ,[IndexController::class,'index'],['GET']); // http:localhost:8080/
    
    $rutas->add('blog', '/blog/{slug}-{id}',[IndexController::class,'blog'],['GET']);
    
});

$rutas->domain('subdomain.localhost:8080', function() use($rutas){
    $rutas->add('home.domain', '/' ,function(){ // http:subdomain.localhost:8080/
        return "Hello world subdomain!!";
    },['GET']);
    
    $rutas->add('search', '/search' ,function(){ // --- http:subdomain.localhost:8080/search <--- GET or POST
        $post = new Request;
        return json_encode($post->getBody());
    },['GET','POST']);
});

$rutas->group('/w-admin', function() use($rutas){
    $rutas->add('admin', '/' ,function(){ // http:localhost:8080/w-admin
        echo "holaa";
    },['GET']);
});

$rutas->setError(function(){
    return "404";
});

$rutas->run();
```

```
php -S localhost:8000
```