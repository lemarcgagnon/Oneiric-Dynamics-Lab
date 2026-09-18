<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Repository.php';
require_once __DIR__.'/../app/Evaluator.php';
require_once __DIR__.'/TestRepository.php';
require_once __DIR__.'/DeterministicEvaluator.php';
require_once __DIR__.'/../app/bootstrap.php';
use ODLab\{LabService,ResearchContract};use ODLabTests\{TestRepository,DeterministicEvaluator};
$pass=0;$fail=[];function echk(bool $ok,string $name):void{global$pass,$fail;if($ok){echo"PASS $name\n";$pass++;}else{$fail[]=$name;echo"FAIL $name\n";}}
function fixture():array{
    $file=tempnam(sys_get_temp_dir(),'odlab-evidence-');@unlink($file);$repo=new TestRepository($file);$svc=new LabService($repo,fn($c)=>new DeterministicEvaluator($c['provider']??[]));
    $cfg=ResearchContract::defaults();$cfg['suite']='smoke';$cfg['seeds']=[101];$cfg['budget']['max_api_calls']=100;
    $m=$svc->createMission(1,'Evidence integrity','Hostile tamper detection',$cfg);$run=$svc->runMission($m)['run_id'];return[$file,$run];
}
try{
    [$file,$run]=fixture();$repo=new TestRepository($file);$svc=new LabService($repo);$clean=$svc->getRun($run);echk(($clean['integrity']['ok']??false)===true,'clean persisted evidence verifies before report/export');
    $raw=json_decode((string)file_get_contents($file),true);$raw['results'][0]['result_json']=str_replace('"seed":101','"seed":999',$raw['results'][0]['result_json']);file_put_contents($file,json_encode($raw,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    try{(new LabService(new TestRepository($file)))->getRun($run);echk(false,'tampered condition result is rejected');}catch(Throwable $e){echk(str_contains($e->getMessage(),'condition-result hash mismatch'),'tampered condition result is rejected');}@unlink($file);

    [$file,$run]=fixture();$raw=json_decode((string)file_get_contents($file),true);$raw['events'][0]['event_json']=str_replace('run_started','run_tampered',$raw['events'][0]['event_json']);if($raw['events'][0]['event_json']===$raw['events'][0]['event_json']){$raw['events'][0]['event_json'].=' ';}file_put_contents($file,json_encode($raw,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    try{(new LabService(new TestRepository($file)))->getRun($run);echk(false,'tampered event is rejected');}catch(Throwable $e){echk(str_contains($e->getMessage(),'event hash mismatch'),'tampered event is rejected');}@unlink($file);

    [$file,$run]=fixture();$raw=json_decode((string)file_get_contents($file),true);$raw['runs'][$run]['analysis_json'].=' ';file_put_contents($file,json_encode($raw,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    try{(new LabService(new TestRepository($file)))->getRun($run);echk(false,'tampered run-level analysis is rejected');}catch(Throwable $e){echk(str_contains($e->getMessage(),'run-level hash mismatch'),'tampered run-level analysis is rejected');}@unlink($file);

    [$file,$run]=fixture();$raw=json_decode((string)file_get_contents($file),true);array_pop($raw['results']);file_put_contents($file,json_encode($raw,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    try{(new LabService(new TestRepository($file)))->getRun($run);echk(false,'deleted evidence row is rejected by run evidence root');}catch(Throwable $e){echk(str_contains($e->getMessage(),'run-level hash mismatch'),'deleted evidence row is rejected by run evidence root');}@unlink($file);
}catch(Throwable $e){$fail[]='exception: '.$e->getMessage();echo'EXCEPTION '.$e->getMessage()."\n";}
echo"\nEVIDENCE PASS $pass\n";if($fail){echo'EVIDENCE FAIL '.count($fail)."\n- ".implode("\n- ",$fail)."\n";exit(1);}echo"EVIDENCE FAIL 0\n";
