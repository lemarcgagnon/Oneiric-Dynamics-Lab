<?php
declare(strict_types=1);
namespace ODLabTests;

final class TestRepository implements \ODLab\Repository
{
    private array $d=[];
    public function __construct(private ?string $file=null){$this->load();if(!$this->d)$this->init();}
    private function init():void{$this->d=['users'=>[['id'=>1,'username'=>'admin','password_hash'=>password_hash('testpass12345',PASSWORD_DEFAULT),'created_at'=>gmdate('Y-m-d H:i:s')]],'missions'=>[],'runs'=>[],'results'=>[],'events'=>[],'seq'=>['mission'=>0,'run'=>0,'event'=>0]];$this->save();}
    private function load():void{if($this->file&&is_file($this->file)){$x=json_decode((string)file_get_contents($this->file),true);if(is_array($x))$this->d=$x;}}
    private function refresh():void{if($this->file)$this->load();}
    private function save():void{if($this->file){$dir=dirname($this->file);if(!is_dir($dir))mkdir($dir,0770,true);file_put_contents($this->file,json_encode($this->d,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);}}
    private function now():string{return gmdate('Y-m-d H:i:s');}
    public function findUserByUsername(string $username):?array{$this->refresh();foreach($this->d['users'] as $u)if($u['username']===$username)return$u;return null;}
    public function createMission(int $userId,string $title,string $objective,string $suite,string $configJson,string $configHash):int{$this->refresh();$id=++$this->d['seq']['mission'];$this->d['missions'][$id]=['id'=>$id,'user_id'=>$userId,'title'=>$title,'objective'=>$objective,'suite'=>$suite,'config_json'=>$configJson,'config_hash'=>$configHash,'status'=>'queued','error_text'=>null,'created_at'=>$this->now(),'started_at'=>null,'completed_at'=>null];$this->save();return$id;}
    public function getMission(int $missionId):?array{$this->refresh();return$this->d['missions'][$missionId]??null;}
    public function markMissionRunning(int $missionId):void{$this->refresh();$this->d['missions'][$missionId]['status']='running';$this->d['missions'][$missionId]['started_at']=$this->now();$this->save();}
    public function markMissionCompleted(int $missionId):void{$this->refresh();$this->d['missions'][$missionId]['status']='completed';$this->d['missions'][$missionId]['completed_at']=$this->now();$this->save();}
    public function markMissionFailed(int $missionId,string $error):void{$this->refresh();$this->d['missions'][$missionId]['status']='failed';$this->d['missions'][$missionId]['error_text']=$error;$this->d['missions'][$missionId]['completed_at']=$this->now();$this->save();}
    public function createRun(int $missionId,string $configHash,string $splitHash,string $splitManifestJson,string $providerModel,string $codeManifestHash):int{$this->refresh();$id=++$this->d['seq']['run'];$this->d['runs'][$id]=['id'=>$id,'mission_id'=>$missionId,'config_hash'=>$configHash,'split_hash'=>$splitHash,'split_manifest_json'=>$splitManifestJson,'software_version'=>defined('ODLAB_VERSION')?ODLAB_VERSION:'test','provider_model'=>$providerModel,'code_manifest_hash'=>$codeManifestHash,'status'=>'running','analysis_json'=>null,'usage_json'=>null,'error_text'=>null,'started_at'=>$this->now(),'completed_at'=>null];$this->save();return$id;}
    public function saveConditionResult(int $runId,string $condition,int $seed,string $resultJson):void{$this->refresh();$this->d['results'][]=['run_id'=>$runId,'condition_name'=>$condition,'seed'=>$seed,'result_sha256'=>hash('sha256',$resultJson),'result_json'=>$resultJson,'created_at'=>$this->now()];$this->save();}
    public function saveRunEvent(int $runId,string $eventType,string $eventJson):void{$this->refresh();$id=++$this->d['seq']['event'];$this->d['events'][]=['id'=>$id,'run_id'=>$runId,'event_type'=>$eventType,'event_sha256'=>hash('sha256',$eventType.'\n'.$eventJson),'event_json'=>$eventJson,'created_at'=>$this->now()];$this->save();}
    public function saveRunAnalysis(int $runId,string $analysisJson,string $usageJson):void{$this->refresh();$this->d['runs'][$runId]['analysis_json']=$analysisJson;$this->d['runs'][$runId]['analysis_sha256']=hash('sha256',$analysisJson);$this->d['runs'][$runId]['usage_json']=$usageJson;$this->d['runs'][$runId]['usage_sha256']=hash('sha256',$usageJson);$this->save();}
    public function sealRunEvidence(int $runId):string{$this->refresh();$results=[];foreach($this->d['results'] as $r)if($r['run_id']===$runId)$results[]=(string)$r['result_sha256'];$events=[];foreach($this->d['events'] as $e)if($e['run_id']===$runId)$events[]=(string)$e['event_sha256'];$root=\ODLab\Util::evidenceRoot($results,$events);$this->d['runs'][$runId]['evidence_root_sha256']=$root;$this->save();return$root;}
    public function finalizeRun(int $runId):void{$this->refresh();$this->d['runs'][$runId]['status']='completed';$this->d['runs'][$runId]['completed_at']=$this->now();$this->save();}
    public function failRun(int $runId,string $error):void{$this->refresh();$this->d['runs'][$runId]['status']='failed';$this->d['runs'][$runId]['error_text']=$error;$this->d['runs'][$runId]['completed_at']=$this->now();$this->save();}
    public function listMissions(int $limit=100):array{$this->refresh();$m=array_values($this->d['missions']);usort($m,fn($a,$b)=>$b['id']<=>$a['id']);foreach($m as &$row){$runId=null;foreach($this->d['runs'] as $r)if($r['mission_id']===$row['id'])$runId=max($runId??0,$r['id']);$row['run_id']=$runId;}return array_slice($m,0,$limit);}
    public function getRun(int $runId):?array{$this->refresh();$r=$this->d['runs'][$runId]??null;if(!$r)return null;$m=$this->d['missions'][$r['mission_id']];return array_merge($r,['title'=>$m['title'],'objective'=>$m['objective'],'config_json'=>$m['config_json']]);}
    public function getRunEvents(int $runId):array{$this->refresh();return array_values(array_filter($this->d['events'],fn($e)=>$e['run_id']===$runId));}
    public function getConditionResults(int $runId):array{$this->refresh();return array_values(array_filter($this->d['results'],fn($r)=>$r['run_id']===$runId));}
    public function raw():array{$this->refresh();return$this->d;}
}
