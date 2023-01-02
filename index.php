<?php 

require('vendor/autoload.php');

use GustRouter\Router;


$rutas = new Router;
$rutas->setDomain('localhost:808');


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


$rutas->get('home', '/', [IndexController::class, 'index']);
$rutas->get('blog', '/blog/{slug}/{id}', [IndexController::class, 'blog']);

// subdominio wildcard
$rutas->domain('{domain}.localhost:808', function() use ($rutas){
    $rutas->get('domain', '/', [Subdomain::class, 'index']);
});


$rutas->setError(function(){
    return "404";
});

print_r( $rutas->getRouters() );

$rutas->run();