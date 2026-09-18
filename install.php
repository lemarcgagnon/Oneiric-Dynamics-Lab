<?php
declare(strict_types=1);
const INSTALL_SCHEMA_VERSION='1.4.0';
require_once __DIR__.'/app/I18n.php';
function il(string $k,array $v=[]):string{return ODLab\I18n::t($k,$v);}
function ie(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function ilang():string{return ODLab\I18n::lang();}
function ilangurl(string $l):string{return ODLab\I18n::languageUrl($l);}
$root=__DIR__;$storage=$root.'/storage';$configFile=$root.'/config.php';$lockFile=$storage.'/.installed';
if(is_file($configFile)||is_file($lockFile)){http_response_code(409);die(ie(il('install.already')));}
$checks=[
 il('install.check.php')=>version_compare(PHP_VERSION,'8.1.0','>='),
 il('install.check.pdo_mysql')=>class_exists('PDO')&&in_array('mysql',PDO::getAvailableDrivers(),true),
 il('install.check.curl')=>function_exists('curl_init'),
 il('install.check.json')=>function_exists('json_encode'),
 il('install.check.random')=>function_exists('random_bytes'),
];
$err='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  foreach($checks as $name=>$pass)if(!$pass)throw new RuntimeException("Preflight failed: $name");
  foreach(['db_host','db_name','db_user','admin_user','admin_pass'] as $k)if(trim((string)($_POST[$k]??''))==='')throw new RuntimeException("Missing $k");
  if(strlen((string)$_POST['admin_pass'])<10)throw new RuntimeException('Admin password must contain at least 10 characters.');
  if(!is_dir($storage)&&!mkdir($storage,0700,true)&&!is_dir($storage))throw new RuntimeException('Cannot create storage directory.');
  if(!is_writable($root)||!is_writable($storage))throw new RuntimeException('Application directory/storage is not writable by PHP.');

  $port=(int)($_POST['db_port']??3306);if($port<1||$port>65535)throw new RuntimeException('Invalid database port.');
  $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',trim((string)$_POST['db_host']),$port,trim((string)$_POST['db_name']));
  $pdo=new PDO($dsn,trim((string)$_POST['db_user']),(string)($_POST['db_pass']??''),[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $pdo->query('SELECT 1')->fetchColumn();
  $sql=file_get_contents($root.'/schema.sql');if($sql===false)throw new RuntimeException('Cannot read schema.sql.');
  foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $stmt)$pdo->exec($stmt);

  $cfg=['db'=>['host'=>trim((string)$_POST['db_host']),'port'=>$port,'name'=>trim((string)$_POST['db_name']),'user'=>trim((string)$_POST['db_user']),'pass'=>(string)($_POST['db_pass']??'')],'installed_at'=>gmdate('c')];
  $php="<?php\ndeclare(strict_types=1);\nreturn ".var_export($cfg,true).";\n";
  if(file_put_contents($configFile,$php,LOCK_EX)===false)throw new RuntimeException('Cannot write config.php. Check directory permissions.');@chmod($configFile,0600);

  // Prove that the installation lock can be written before mutating the database.
  $probe=$storage.'/.install-write-probe';
  if(file_put_contents($probe,'ok',LOCK_EX)===false)throw new RuntimeException('Storage is not writable enough to create the installation lock.');
  @unlink($probe);

  $pdo->beginTransaction();
  try{
   $existing=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();if($existing!==0)throw new RuntimeException('Database already contains OD Lab users; use a fresh database for this installer.');
   $st=$pdo->prepare('INSERT INTO users(username,password_hash,created_at) VALUES(?,?,NOW())');$st->execute([trim((string)$_POST['admin_user']),password_hash((string)$_POST['admin_pass'],PASSWORD_DEFAULT)]);
   $st=$pdo->prepare('INSERT INTO app_settings(id,schema_version,created_at,updated_at) VALUES(1,?,NOW(),NOW())');$st->execute([INSTALL_SCHEMA_VERSION]);
   // Create the install lock before COMMIT so a lock failure can still roll back DB writes.
   if(file_put_contents($lockFile,gmdate('c')."\n",LOCK_EX)===false)throw new RuntimeException('Cannot create installation lock.');
   @chmod($lockFile,0600);
   $pdo->commit();
  }catch(Throwable $tx){
   if($pdo->inTransaction())$pdo->rollBack();
   if(is_file($lockFile))@unlink($lockFile);
   throw$tx;
  }
  $ok=true;
 }catch(Throwable $e){$err=$e->getMessage();if(!$ok&&is_file($configFile))@unlink($configFile);}
}
?><!doctype html><html lang="<?=ie(ilang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><link rel="stylesheet" href="public/assets/app.css"><title>OD Lab · <?=ie(il('install.title'))?></title></head><body><div class="install-wrap"><div class="sectionhead"><div class="hero"><p class="eyebrow">ONEIRIC DYNAMICS LABORATORY</p><h1><?=ie(il('install.title'))?></h1><p><?=ie(il('install.flow'))?></p></div><div class="langswitch"><a <?=ilang()==='fr'?'class="active" ':''?>href="<?=ie(ilangurl('fr'))?>">FR</a><a <?=ilang()==='en'?'class="active" ':''?>href="<?=ie(ilangurl('en'))?>">EN</a></div></div><div class="install-card"><h2><?=ie(il('install.preflight'))?></h2><div class="preflight"><?php foreach($checks as $name=>$pass):?><div class="check"><strong><?=ie($name)?></strong><span class="<?=$pass?'yes':'no'?>" style="float:right"><?=$pass?'PASS':'FAIL'?></span></div><?php endforeach?></div><?php if($ok):?><div class="install-ok"><h2><?=ie(il('install.done'))?></h2><p><a href="public/login.php?lang=<?=ie(ilang())?>"><?=ie(il('install.open'))?></a></p><p><strong><?=ie(il('install.delete'))?></strong></p></div><?php else:?><?php if($err):?><div class="install-err"><?=ie($err)?></div><?php endif?><form method="post"><div class="install-grid"><div><label><?=ie(il('install.db_host'))?></label><input name="db_host" value="localhost" required></div><div><label><?=ie(il('install.port'))?></label><input name="db_port" value="3306" inputmode="numeric" required></div></div><label><?=ie(il('install.db_name'))?></label><input name="db_name" required><label><?=ie(il('install.db_user'))?></label><input name="db_user" required><label><?=ie(il('install.db_pass'))?></label><input name="db_pass" type="password"><hr style="border:0;border-top:1px solid #d7e0eb;margin:24px 0"><label><?=ie(il('install.admin_user'))?></label><input name="admin_user" value="admin" required><label><?=ie(il('install.admin_pass'))?></label><input name="admin_pass" type="password" minlength="10" required><button class="primary" <?=in_array(false,$checks,true)?'disabled':''?>><?=ie(il('install.submit'))?></button></form><?php endif?></div></div></body></html>
