<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Repository.php';
require_once __DIR__.'/../app/Evaluator.php';
require_once __DIR__.'/TestRepository.php';
require_once __DIR__.'/../app/bootstrap.php';
use ODLab\{Evaluator,LabService,ResearchContract};
use ODLabTests\TestRepository;

final class SecretEchoEvaluator implements Evaluator
{
    public function __construct(private string $secret){}
    public function evaluate(array $memory,array $records,string $split):array
    {
        $answers=[];foreach($records as $r)$answers[]=[(string)$r['id'],$this->secret,(string)$r['label'],false];
        return ['accuracy'=>0.0,'correct'=>0,'n'=>count($records),'answers'=>$answers,'usage'=>$this->usage()];
    }
    public function usage():array{return['api_calls'=>0,'input_tokens'=>0,'output_tokens'=>0,'latency_seconds'=>0.0];}
    public function events():array{return[];}
}

$pass=0;$fail=[];function bchk(bool $ok,string $name):void{global$pass,$fail;if($ok){echo"PASS $name\n";$pass++;}else{$fail[]=$name;echo"FAIL $name\n";}}
$file=tempnam(sys_get_temp_dir(),'odlab-byok-runtime-');@unlink($file);$secret='RUNTIME_HOSTILE_SECRET_DO_NOT_PERSIST_123456789';
try{
    $repo=new TestRepository($file);
    $svc=new LabService($repo,fn(array $cfg):Evaluator=>new SecretEchoEvaluator($secret));
    $cfg=ResearchContract::defaults();$cfg['suite']='smoke';$cfg['seeds']=[101];$cfg['budget']['max_api_calls']=100;
    $mission=$svc->createMission(1,'BYOK hostile','Secret must never cross persistence boundary',$cfg);
    try{$svc->runMission($mission,$secret);bchk(false,'secret-bearing result is rejected before persistence');}
    catch(Throwable $e){bchk(str_contains($e->getMessage(),'Credential leak blocked before persistence'),'secret-bearing result is rejected before persistence');}
    $raw=(string)file_get_contents($file);
    bchk(!str_contains($raw,$secret),'BYOK secret absent from repository file after failure path');
    $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    $m=$decoded['missions'][$mission]??[];
    bchk(($m['status']??'')==='failed','mission fails closed when secret guard fires');
    bchk(!str_contains((string)($m['error_text']??''),$secret),'mission error text is secret-redacted');
    $events=$decoded['events']??[];$joined=json_encode($events,JSON_UNESCAPED_SLASHES);
    bchk(!str_contains((string)$joined,$secret),'failure events contain no BYOK secret');
    $missionCfg=(string)($m['config_json']??'');
    bchk(!str_contains($missionCfg,$secret),'frozen scientific config contains no BYOK secret');
}catch(Throwable $e){$fail[]='exception: '.$e->getMessage();echo'EXCEPTION '.$e->getMessage()."\n";}
@unlink($file);
echo"\nBYOK RUNTIME PASS $pass\n";if($fail){echo'BYOK RUNTIME FAIL '.count($fail)."\n- ".implode("\n- ",$fail)."\n";exit(1);}echo"BYOK RUNTIME FAIL 0\n";
