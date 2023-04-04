<?php 

require('vendor/autoload.php');

use GustRouter\Router;


$rutas = new Router;
$rutas->setDomain('localhost:8080');


function route(string $name, array $parements = []){
    global $rutas;
    return $rutas->route($name,$parements);
}

function isRoute(string $name){
    global $rutas;
    return $rutas->isRoute($name);
}

class IndexController {

    public function index(){
        return '<h1>Hello world!!</h1> '.route('blog',['slug' => 'avatar', 'id' => 894654, 'page' => 2]);
    }
    public function blog($slug, $id, $page = 1){
        return [
            'slug' => $slug,
            'id' => $id,
            'page' => $page,
            'route' => route('blog',['slug' => 'avatar', 'id' => $id, 'page' => $page ]),
            'isRoute' => isRoute('blog'),
        ];
    }
}

class Subdomain {
    public function index($domain){
        return '<h1>Hello Domain world!!</h1> '.route('domain',['domain' => $domain]);
    }
}


class AuthMiddleware {
    public function handle(){
        echo 'Middleware';
    }
}


$rutas->get('/', [IndexController::class, 'index'])->name('home');
$rutas->get('/blog/{slug}/{id}', [IndexController::class, 'blog'])->name('blog');


$rutas->group('/admin', function() use ($rutas){
    $rutas->get('/', function(){
        return 'Admin';
    });
    $rutas->get('/blog', function(){
        return 'Admin Blog';
    });
    $rutas->get('/blog/{slug}/{id}', function($slug, $id){
        return 'Admin Blog '.$slug.' '.$id;
    });
},AuthMiddleware::class);

// subdominio wildcard
$rutas->domain('{domain}.localhost:8080', function() use ($rutas){
    $rutas->get('/', [Subdomain::class, 'index'])->name('domain');
});




$rutas->setError(function(){
    return "404";
});

echo "<pre>";
print_r($rutas->getRouters());
echo "</pre>";

$rutas->run();