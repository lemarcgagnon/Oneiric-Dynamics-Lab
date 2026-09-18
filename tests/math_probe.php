<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
use ODLab\{EquationSet,MemoryDistribution};
$in=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$out=[];
foreach($in['cases']??[] as $case){
    $type=$case['type']??'';
    if($type==='mixture'){
        $out[]=EquationSet::mixture((float)$case['rho'],(float)$case['cf']);
    }elseif($type==='ranking'){
        $out[]=EquationSet::rankingScore((float)$case['n'],(float)$case['c'],(float)$case['a'],(float)$case['ln'],(float)$case['lc'],(float)$case['la']);
    }elseif($type==='gibbs'){
        $out[]=EquationSet::gibbsWeights($case['scores'],(float)$case['t']);
    }elseif($type==='novelty'){
        $out[]=EquationSet::finiteReferenceNovelty($case['x'],$case['refs'],(float)$case['sigma'],(float)$case['cap']);
    }elseif($type==='gate'){
        $out[]=EquationSet::lossGate($case['ref'],$case['prop'],$case['tol']);
    }elseif($type==='diag'){
        $out[]=['qhat'=>EquationSet::empiricalDistribution($case['assessments']),'diag'=>EquationSet::noveltyDiagnostics($case['assessments'])];
    }elseif($type==='memory'){
        $mk=function(array $weights):array{$d=[];foreach($weights as $i=>$w){$key='k'.$i;$d[$key]=['atom_key'=>$key,'record'=>['id'=>$key,'factors'=>['shape'=>'s','color'=>'c','position'=>'p','texture'=>'t'],'label'=>'l','provenance'=>['historical_origin'=>'external-observation','production_operator'=>'external','source_ids'=>[],'source_version'=>'x','model_version'=>'n/a','intervention'=>null,'evidence_status'=>'external-observation','scope'=>'test']],'weight'=>(float)$w];}return$d;};
        $p=$mk($case['p']);$q=$mk($case['q']);$m=MemoryDistribution::mixture($p,$q,(float)$case['eta']);$v=MemoryDistribution::verifyMixtureBound($p,$q,$m,(float)$case['eta']);$out[]=['weights'=>array_values(array_map(fn($a)=>(float)$a['weight'],$m)),'tv'=>$v];
    }else throw new RuntimeException('unknown case');
}
echo json_encode(['results'=>$out],JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
