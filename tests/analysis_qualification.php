<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
use ODLab\{Analysis,ResearchContract};
$pass=0;$fail=[];function ck(bool $ok,string $name):void{global$pass,$fail;if($ok){echo "PASS $name\n";$pass++;}else{$fail[]=$name;echo "FAIL $name\n";}}
function r(string $c,int $seed,float $ret,float $known,float $abs,float $nov=.5,int $calls=10,int $tokens=1000,float $wall=1.0):array{return[
 'condition'=>$c,'seed'=>$seed,'committed_retention'=>$ret,'final_known_accuracy'=>$known,'final_absent_accuracy'=>$abs,
 'proposal_novelty_mean'=>$nov,'learning_novelty_mean'=>$nov,'proposed_count'=>10,'admitted_count'=>8,'rejected_count'=>2,
 'usage'=>['api_calls'=>$calls,'input_tokens'=>(int)($tokens*.7),'output_tokens'=>(int)($tokens*.3),'wall_seconds'=>$wall],
 'memory_tv_proposal_reference'=>.25,'synthetic_influence'=>.25,'rollback'=>false,'no_change'=>false,'gate_status'=>'passed',
 'eta'=>.25,'proposal_check'=>.9,'committed_check'=>.9,'state_hash_before'=>'a','state_hash_proposed'=>'b','state_hash_committed'=>'b'
];}
$rows=[];
for($s=101;$s<=105;$s++){
 $j=($s-103)*.002;
 $rows[]=r('replay',$s,.90+$j,.70+$j,.50+$j,.2,10,1000);
 $rows[]=r('replay_recombination',$s,.89+$j,.75+$j,.54+$j,.55,11,1050);
 $rows[]=r('ordinary_augmentation',$s,.90+$j,.72+$j,.51+$j,.35,10,1000);
 $rows[]=r('uniform_generation',$s,.88+$j,.70+$j,.50+$j,.45,10,1000,1.0);
 $rows[]=r('targeted_generation',$s,.88+$j,.75+$j,.53+$j,.50,12,1100,1.2);
 $rows[]=r('novelty_low',$s,.90,.60+$j,.40,.20);
 $rows[]=r('novelty_mid',$s,.90,.80+$j,.55,.60);
 $rows[]=r('novelty_high',$s,.90,.65+$j,.48,.90);
}
$cfg=ResearchContract::validate(array_replace_recursive(ResearchContract::defaults(),['suite'=>'all','seeds'=>[101,102,103,104,105]]));
$a=Analysis::build($rows,$cfg);
$h1=$a['h1']['recombination_vs_replay']['known_transfer']['delta_stats'];
ck($h1['n']===5 && abs($h1['mean']-.05)<1e-12,'H1 paired known-transfer delta is exact by seed');
ck($a['h1']['recombination_vs_replay']['retention']['decision']==='noninferiority_supported','H1 retention noninferiority uses prespecified tolerance and paired CI');
ck(($a['h2']['models']['status']??'')==='model_comparison','H2 compares flat/linear/quadratic models');
ck(!empty($a['h2']['models']['interior_inverted_u_candidate']),'H2 detects controlled interior inverted-U candidate');
ck(($a['h2']['models']['bootstrap']['status']??'')==='ok' && ($a['h2']['models']['bootstrap']['samples']??0)>=200,'H2 seed-cluster bootstrap uncertainty executed');
$h4=$a['h4']['primary_recombination_vs_replay']['benefit_contrast'];
ck($h4['n']===5 && abs($h4['mean']-.01)<1e-12,'H4 primary benefit contrast is paired difference-in-differences by seed');
$h4raw=$a['h4']['raw_within_condition_diagnostic']['replay_recombination']['known_minus_absent'];
ck($h4raw['n']===5 && abs($h4raw['mean']-.21)<1e-12,'H4 raw known-minus-absent remains diagnostic rather than primary inference');
$h5=$a['h5']['known_transfer']['delta_stats'];
ck($h5['n']===5 && abs($h5['mean']-.05)<1e-12,'H5 targeted-minus-uniform performance is paired by seed');
ck(abs(($a['h5']['tokens']['delta_stats']['mean']??0)-100.0)<1e-12,'H5 observed token overhead is preserved separately from performance');
ck(($a['research_status']??'')==='controlled_analysis_ready','multi-seed all-suite run receives analysis-ready status without claiming external validity');
ck(str_contains($a['research_status_note'],'do not establish external validity'),'analysis explicitly prevents overclaiming scientific validity');
echo "\nANALYSIS PASS $pass\n";if($fail){echo 'ANALYSIS FAIL '.count($fail)."\n- ".implode("\n- ",$fail)."\n";exit(1);}echo "ANALYSIS FAIL 0\n";
