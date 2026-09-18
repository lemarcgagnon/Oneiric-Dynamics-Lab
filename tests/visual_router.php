<?php
declare(strict_types=1);
require_once __DIR__.'/web_override.php';
require_once __DIR__.'/../app/bootstrap.php';
start_secure_session();$_SESSION['uid']=1;$_SESSION['username']='admin';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/index.php';if($path==='/')$path='/index.php';
$file=realpath(__DIR__.'/../public'.$path);$public=realpath(__DIR__.'/../public');
if(!$file||!str_starts_with($file,$public)||!is_file($file)){http_response_code(404);echo'Not found';return;}
$ext=pathinfo($file,PATHINFO_EXTENSION);if($ext!=='php'){$mime=['css'=>'text/css','js'=>'application/javascript'][$ext]??'application/octet-stream';header('Content-Type: '.$mime);readfile($file);return;}require$file;
