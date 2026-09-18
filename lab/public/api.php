<?php
declare(strict_types=1);
@set_time_limit(0);
require_once __DIR__.'/../app/bootstrap.php';
require_login();
use ODLab\LabService;use ODLab\ResearchContract;
$svc=new LabService();$action=(string)($_GET['action']??'');
try{
    if($action==='preview'){
        csrf_check();$raw=file_get_contents('php://input');$in=json_decode($raw?:'{}',true,512,JSON_THROW_ON_ERROR);
        $cfg=ResearchContract::defaults();$cfg=array_replace_recursive($cfg,$in['config']??[]);$cfg['suite']=$in['suite']??$cfg['suite'];
        $seedCount=max(1,min(20,(int)($in['seed_count']??1)));$baseSeed=(int)($in['base_seed']??101);$cfg['seeds']=array_map(fn($i)=>$baseSeed+$i,range(0,$seedCount-1));
        $p=$svc->previewConfig($cfg);json_response(['ok'=>true,'mix'=>$p['mix'],'estimated_api_calls'=>$p['estimated_api_calls'],'condition_count'=>$p['condition_count'],'math_coverage'=>$p['math_coverage']]);
    }
    if($action==='create_run'){
        csrf_check();$raw=file_get_contents('php://input');$in=json_decode($raw?:'{}',true,512,JSON_THROW_ON_ERROR);
        $cfg=ResearchContract::defaults();$cfg=array_replace_recursive($cfg,$in['config']??[]);$cfg['suite']=$in['suite']??$cfg['suite'];
        $seedCount=max(1,min(20,(int)($in['seed_count']??1)));$baseSeed=(int)($in['base_seed']??101);$cfg['seeds']=array_map(fn($i)=>$baseSeed+$i,range(0,$seedCount-1));
        $apiKey=trim((string)($in['api_key']??''));
        if($apiKey==='' || strlen($apiKey)>4096)throw new InvalidArgumentException(t('api.key_required_run'));
        $missionId=$svc->createMission((int)$_SESSION['uid'],(string)($in['title']??'OD Lab mission'),(string)($in['objective']??''),$cfg);
        $res=$svc->runMission($missionId,$apiKey);
        $apiKey='';unset($in['api_key']);json_response(['ok'=>true,'mission_id'=>$missionId,'run_id'=>$res['run_id']]);
    }
    json_response(['ok'=>false,'error'=>'Unknown action'],404);
}catch(Throwable $e){json_response(['ok'=>false,'error'=>$e->getMessage()],500);}
