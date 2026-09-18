<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
use ODLab\{EquationSet,MemoryDistribution,ResearchContract,RNG};
$pass=0;$fail=[];function ck2(bool $ok,string $n):void{global$pass,$fail;if($ok){echo"PASS $n\n";$pass++;}else{$fail[]=$n;echo"FAIL $n\n";}}
// Eq. 3-4 / Proposition 1 implementation conditions.
$m=EquationSet::mixture(.6,.25);ck2(abs(array_sum($m)-1)<1e-12 && min($m)>=0,'Eq3-4 mixture is a probability vector');
// Appendix C.2: softmax cannot rescue a rejected batch.
$w=EquationSet::gibbsWeights(['x'=>999.0],.2);ck2(abs($w['x']-1)<1e-12,'Appendix C.2 softmax alone assigns singleton weight one');
$ass=[['candidate_uid'=>'u','experience_id'=>'x','admitted'=>false,'weight'=>0.0,'novelty'=>1.0]];ck2(EquationSet::empiricalDistribution($ass)===[],'Eq5 reject-all branch prevents invalid singleton from entering Qhat');
// Eq. 12 / 18 exact finite discrete specialization.
$p=MemoryDistribution::uniform([['id'=>'a','factors'=>[],'label'=>'A','provenance'=>['origin'=>'external']],['id'=>'b','factors'=>[],'label'=>'B','provenance'=>['origin'=>'external']]]);
$c=['candidate_uid'=>'u','id'=>'c','factors'=>[],'label'=>'C','provenance'=>['origin'=>'synthetic']];
$q=MemoryDistribution::fromQhat([['candidate_uid'=>'u','experience_id'=>'c','weight'=>1.0]],['u'=>$c]);
$star=MemoryDistribution::mixture($p,$q,.25);$tv=MemoryDistribution::verifyMixtureBound($p,$q,$star,.25);ck2($tv['bound_pass'] && abs($tv['equality_residual'])<1e-10,'Eq12 proposal satisfies exact Eq18 total-variation identity');
// Eq. 13-14: finite penalties are not enough; hard loss gate can reject.
ck2(!EquationSet::lossGate(['anchor'=>0.0],['anchor'=>16.0],['anchor'=>0.0]),'Appendix C.3 hard gate rejects anchor degradation');
// Eq. 19 full-support shortcut is not used: finite explicit reference required.
$thrown=false;try{EquationSet::finiteReferenceNovelty([100.0],[],1.0,1000);}catch(Throwable){$thrown=true;}ck2($thrown,'Eq19 refuses missing finite reference instead of silently using support-distance novelty');
// Eq. 20 does not multiply proposal novelty by rho again.
$d=EquationSet::noveltyDiagnostics([['novelty'=>.8,'admitted'=>true,'weight'=>1.0]]);ck2(abs($d['proposal_novelty']-.8)<1e-12,'Appendix C.1 / Eq20 proposal novelty is not double-multiplied by exploratory share');
// Fixed API boundary: theta theorems explicitly unclaimed.
$coverage=array_column(ResearchContract::mathCoverage(),null,'id');ck2(($coverage['eq9_11']['status']??'')==='not_claimed'&&($coverage['eq16_17']['status']??'')==='not_claimed','fixed-API implementation does not counterfeit neural theta guarantees');
ck2(($coverage['eq15_markov']['status']??'')==='documented','Markov/invariant-measure claims remain conditional and unclaimed globally');
echo "\nPAPER IVV PASS $pass\n";if($fail){echo'PAPER IVV FAIL '.count($fail)."\n- ".implode("\n- ",$fail)."\n";exit(1);}echo"PAPER IVV FAIL 0\n";
