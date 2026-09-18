<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';require_login();header('Cache-Control: no-store, private');
$health=health_service()->check(is_file(__DIR__.'/../install.php'));$checks=$health['checks'];$all=$health['ok'];
function health_label(string $id):string{
    if(str_starts_with($id,'table:'))return t('health.check.table',['name'=>substr($id,6)]);
    if(str_starts_with($id,'run_column:'))return t('health.check.run_column',['name'=>substr($id,11)]);
    return t('health.check.'.$id);
}
?><!doctype html><html lang="<?=e(lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><link rel="stylesheet" href="assets/app.css"><title>OD Lab · <?=e(t('nav.health'))?></title></head><body><header><div><strong>OD Lab</strong><span><?=e(t('health.title'))?></span></div><nav><a href="index.php"><?=e(t('nav.mission_control'))?></a><a href="health.php?lang=fr">FR</a><a href="health.php?lang=en">EN</a></nav></header><main><section class="hero"><p class="eyebrow"><?=e(t('health.eyebrow'))?></p><h1><?=e(t('health.title'))?></h1><p><?=e(t('health.body'))?></p></section><section class="panel"><div class="sectionhead"><h2><?=e($all?t('health.pass'):t('health.attention'))?></h2><span class="badge <?=$all?'good':'failed'?>"><?=e($all?t('health.pass'):'FAIL')?></span></div><div class="preflight"><?php foreach($checks as $c):?><div class="check"><strong><?=e(health_label((string)$c['id']))?></strong><span class="<?=$c['ok']?'yes':'no'?>" style="float:right"><?=$c['ok']?'PASS':'FAIL'?></span><?php if($c['detail']!==''):?><div class="micro"><?=e($c['detail'])?></div><?php endif?></div><?php endforeach?></div></section><section class="panel"><h2><?=e(t('health.byok_title'))?></h2><p><?=e(t('health.byok_body'))?></p></section></main></body></html>
