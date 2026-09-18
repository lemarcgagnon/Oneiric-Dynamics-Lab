<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Repository.php';require_once __DIR__.'/../app/Evaluator.php';require_once __DIR__.'/TestRepository.php';require_once __DIR__.'/DeterministicEvaluator.php';
$file=$argv[1]??sys_get_temp_dir().'/odlab-visual-repo.json';@unlink($file);$repo=new ODLabTests\TestRepository($file);$GLOBALS['ODLAB_REPOSITORY_OVERRIDE']=$repo;require_once __DIR__.'/../app/bootstrap.php';
$cfg=ODLab\ResearchContract::defaults();$cfg['suite']='all';$cfg['seeds']=[101,102,103];$cfg['budget']['max_api_calls']=1000;$svc=new ODLab\LabService($repo,fn($c)=>new ODLabTests\DeterministicEvaluator($c['provider']??[]));$mid=$svc->createMission(1,'OD research qualification','H1–H5 paired research-analysis and paper-fidelity qualification',$cfg);$r=$svc->runMission($mid);echo $r['run_id'];
