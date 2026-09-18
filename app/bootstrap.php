<?php
declare(strict_types=1);

const ODLAB_VERSION = '1.4.0';
const ODLAB_ROOT = __DIR__ . '/..';

spl_autoload_register(function(string $class): void {
    $prefix='ODLab\\';
    if(!str_starts_with($class,$prefix))return;
    $rel=substr($class,strlen($prefix));
    $file=__DIR__.'/'.str_replace('\\','/',$rel).'.php';
    if(is_file($file))require_once $file;
});

function config_path():string{return ODLAB_ROOT.'/config.php';}
function app_config():array{
    static $cfg=null;
    if($cfg!==null)return$cfg;
    if(!is_file(config_path()))throw new RuntimeException('Application not installed. Run install.php.');
    $cfg=require config_path();
    return$cfg;
}
function db():PDO{
    static $pdo=null;
    if($pdo instanceof PDO)return$pdo;
    if(!in_array('mysql',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO MySQL extension is required.');
    $c=app_config()['db'];
    $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$c['host'],$c['port'],$c['name']);
    $pdo=new PDO($dsn,$c['user'],$c['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return$pdo;
}
if(!function_exists('repo')){
    function repo():ODLab\Repository{
        static $repo=null;
        if(isset($GLOBALS['ODLAB_REPOSITORY_OVERRIDE'])&&$GLOBALS['ODLAB_REPOSITORY_OVERRIDE'] instanceof ODLab\Repository)return$GLOBALS['ODLAB_REPOSITORY_OVERRIDE'];
        return$repo??=new ODLab\PdoRepository(db());
    }
}
function health_service():ODLab\HealthService{return new ODLab\HealthService(db());}
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

function lang():string{return ODLab\I18n::lang();}
function t(string $key,array $vars=[]):string{return ODLab\I18n::t($key,$vars);}
function lang_url(string $target):string{return ODLab\I18n::languageUrl($target);}
function info_tip(string $key):string{$text=t('tip.'.$key);return '<button class="info-tip" type="button" aria-label="'.e($text).'" data-tip="'.e($text).'">i</button>';}
function json_response(array $data,int $status=200):never{
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;
}
function start_secure_session():void{
    if(session_status()===PHP_SESSION_ACTIVE)return;
    session_name('ODLABSESSID');
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
    if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function require_login():void{start_secure_session();if(empty($_SESSION['uid'])){header('Location: login.php');exit;}}
function csrf_check():void{
    start_secure_session();$token=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf']??'');
    if(!$token||!hash_equals($_SESSION['csrf']??'',$token))json_response(['ok'=>false,'error'=>'CSRF validation failed'],403);
}
