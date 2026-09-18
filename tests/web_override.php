<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Repository.php';
require_once __DIR__.'/../app/Evaluator.php';
require_once __DIR__.'/TestRepository.php';
require_once __DIR__.'/DeterministicEvaluator.php';
function repo():ODLab\Repository{static $r=null;$file=getenv('ODLAB_TEST_REPO_FILE')?:sys_get_temp_dir().'/odlab-test-repo.json';return$r??=new ODLabTests\TestRepository($file);}
function odlab_evaluator_override(array $cfg):ODLab\Evaluator{return new ODLabTests\DeterministicEvaluator($cfg['provider']??[]);}
