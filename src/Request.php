<?php 
namespace GustRouter;


class Request {
    
    public function getPath(){
        // server url
        $path = $_SERVER['REQUEST_URI'] ?? false;
        $position = strpos($path, '?');

        if($position !== false){
            $path = substr($path, 0, $position);
        }else{
            $path = $path;
        }
        return $this->inDomain().$path;//rtrim($this->inDomain().$path, '/\\');
    }

    public function inDomain(){
        return $_SERVER['HTTP_HOST'];
    }


    public function getMethod(){
        return $_SERVER['REQUEST_METHOD'] ?? false;
    }

    public function isGet()
    {
        return $this->getMethod() === 'GET';
    }

    public function isPost()
    {
        return $this->getMethod() === 'POST';
    }

    public function getBody()
    {
        $data = [];
        if ($this->isGet()) {
            foreach ($_GET as $key => $value) {
                $data[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }
        if ($this->isPost()) {
            if(!empty($_GET)){

                foreach ($_GET as $key => $value) {
                    $data[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
                }
            }
            if(!empty($_FILES)){
                foreach ($_FILES as $key => $value) {
                    $data[$key] = $value;
                }
            }
            foreach ($_POST as $key => $value) {
                if(is_array($value)){
                    $data[$key] = filter_input(INPUT_POST, $key, FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);
                }else{
                    $data[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
                }
            }
        }
        $data = !$data ? json_decode(file_get_contents('php://input'), true) : $data;
        return !empty($data) ? $data: [];
    }

}