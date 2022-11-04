<?php 

require('vendor/autoload.php');

use GustRouter\Request;
use GustRouter\Router;
$rutas = new Router;


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
        return '<h1>Hello world!!</h1> '.route('blog',['slug' => 'avatar', 'id' => 894654, 'page' => 2, 'pag' => 2]);
    }

    public function blog($slug, $id){
        return [
            'slug' => $slug,
            'id' => $id,
            'page' => $page,
            'route' => route('blog',['slug' => 'avatar', 'id' => $id, 'page' => $page ]),
            'isRoute' => isRoute('blog'),
        ];
    }

}

$rutas->domain('localhost:8080', function() use($rutas){
    $rutas->add('home', '/' ,[IndexController::class,'index'],['GET']); // localhost:8080/
    
    $rutas->add('blog', '/blog/{slug}-{id}',[IndexController::class,'blog'],['GET']);
    
    $rutas->add('buscar', '/buscar/{slug}' ,function($slug){
        return $slug;
    },['GET','POST']);    
});

$rutas->domain('subdomain.localhost:8080', function() use($rutas){
    $rutas->add('home.domain', '/' ,function(){ // subdomain.localhost:8080/
        return "Hello world subdomain!!";
    },['GET']);
    
    $rutas->add('search', '/search' ,function(){ // --- subdomain.localhost:8080/search <--- GET or POST
        $post = new Request;
        return json_encode($post->getBody());
    },['GET','POST']);
});

$rutas->group('/w-admin', function() use($rutas){
    $rutas->add('admin', '/' ,function(){ // localhost:8080/w-admin
        echo "holaa";
    },['GET']);
});

$rutas->setError(function(){
    return "404";
});

$rutas->run();