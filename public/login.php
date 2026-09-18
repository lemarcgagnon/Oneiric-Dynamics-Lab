<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
start_secure_session();
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $user=repo()->findUserByUsername(trim((string)($_POST['username']??'')));
    if($user&&password_verify((string)($_POST['password']??''),(string)$user['password_hash'])){
        session_regenerate_id(true);$_SESSION['uid']=(int)$user['id'];header('Location: index.php?lang='.rawurlencode(lang()));exit;
    }
    $err=t('login.invalid');
}
?><!doctype html><html lang="<?=e(lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><link rel="stylesheet" href="assets/app.css"><title><?=e(t('login.title'))?> · OD Lab</title></head><body><main class="narrow"><div class="panel login-card"><div class="sectionhead"><div><p class="eyebrow">ONEIRIC DYNAMICS LAB</p><h1><?=e(t('login.title'))?></h1></div><div class="langswitch"><a <?=lang()==='fr'?'class="active" ':''?>href="<?=e(lang_url('fr'))?>">FR</a><a <?=lang()==='en'?'class="active" ':''?>href="<?=e(lang_url('en'))?>">EN</a></div></div><p class="muted"><?=e(t('login.subtitle'))?></p><?php if($err):?><p class="error"><?=e($err)?></p><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><label><?=e(t('login.user'))?><input name="username" autocomplete="username" required></label><label><?=e(t('login.password'))?><input name="password" type="password" autocomplete="current-password" required></label><button class="primary"><?=e(t('login.submit'))?></button></form></div></main></body></html>
