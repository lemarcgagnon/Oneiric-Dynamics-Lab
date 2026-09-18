<?php
declare(strict_types=1);
namespace ODLab;

/**
 * Canonical research-analysis layer.
 * It never changes experimental states. It consumes persisted condition-level
 * evidence and computes predeclared descriptive/paired summaries only.
 */
final class Analysis
{
    public static function build(array $rows, array $cfg): array
    {
        $grouped=[];
        foreach ($rows as $r) $grouped[$r['condition']][]=$r;
        $conditions=[];
        foreach ($grouped as $name=>$group) {
            $conditions[$name]=[
                'n'=>count($group),
                'retention'=>self::stats(array_column($group,'committed_retention')),
                'known_transfer'=>self::stats(array_column($group,'final_known_accuracy')),
                'absent_factor'=>self::stats(array_column($group,'final_absent_accuracy')),
                'proposal_novelty'=>self::stats(array_values(array_filter(array_column($group,'proposal_novelty_mean'),fn($v)=>$v!==null))),
                'learning_novelty'=>self::stats(array_values(array_filter(array_column($group,'learning_novelty_mean'),fn($v)=>$v!==null))),
                'admission_rate'=>self::stats(array_values(array_filter(array_map(fn($r)=>$r['proposed_count']>0?$r['admitted_count']/$r['proposed_count']:null,$group),fn($v)=>$v!==null))),
                'api_calls'=>self::stats(array_map(fn($r)=>(float)$r['usage']['api_calls'],$group)),
                'tokens'=>self::stats(array_map(fn($r)=>(float)($r['usage']['input_tokens']+$r['usage']['output_tokens']),$group)),
                'memory_tv'=>self::stats(array_column($group,'memory_tv_proposal_reference')),
                'synthetic_influence'=>self::stats(array_column($group,'synthetic_influence')),
                'rollbacks'=>count(array_filter($group,fn($r)=>$r['rollback'])),
                'no_change'=>count(array_filter($group,fn($r)=>$r['no_change'])),
            ];
        }

        $analysisCfg=$cfg['analysis']??[];
        $thresholds=$analysisCfg['effect_thresholds']??[];
        $h1=[
            'recombination_vs_replay'=>[
                'known_transfer'=>self::pairedDelta($rows,'replay_recombination','replay','final_known_accuracy',(float)($thresholds['h1_min_transfer_gain']??0.02),'higher'),
                'retention'=>self::pairedDelta($rows,'replay_recombination','replay','committed_retention',(float)($thresholds['h1_max_retention_loss']??0.02),'noninferiority'),
            ],
            'recombination_vs_augmentation'=>[
                'known_transfer'=>self::pairedDelta($rows,'replay_recombination','ordinary_augmentation','final_known_accuracy',(float)($thresholds['h1_min_transfer_gain']??0.02),'higher'),
                'retention'=>self::pairedDelta($rows,'replay_recombination','ordinary_augmentation','committed_retention',(float)($thresholds['h1_max_retention_loss']??0.02),'noninferiority'),
            ],
        ];

        $h2points=[];
        foreach ($rows as $r) {
            if (str_starts_with($r['condition'],'novelty_') && $r['learning_novelty_mean']!==null) {
                $h2points[]=['condition'=>$r['condition'],'seed'=>(int)$r['seed'],'novelty'=>(float)$r['learning_novelty_mean'],'proposal_novelty'=>$r['proposal_novelty_mean'],'transfer'=>(float)$r['final_known_accuracy']];
            }
        }
        $h2=self::fitH2($h2points,$analysisCfg);

        $h3=[];
        foreach ($rows as $r) if (str_starts_with($r['condition'],'h3fapi_')) {
            $h3[]=['condition'=>$r['condition'],'seed'=>$r['seed'],'eta'=>$r['eta'],'memory_tv'=>$r['memory_tv_proposal_reference'],'proposal_check'=>$r['proposal_check'],'committed_check'=>$r['committed_check'],'rollback'=>$r['rollback'],'proposal_state_changed'=>$r['state_hash_before']!==$r['state_hash_proposed'],'committed_state_changed'=>$r['state_hash_before']!==$r['state_hash_committed']];
        }

        // H4 asks whether the *benefit* of an OD mechanism is greater for
        // withheld combinations of represented factors than for a genuinely
        // absent factor. Raw known-vs-absent accuracy is only diagnostic: the
        // primary contrast must remove the matched replay baseline seed by seed.
        $h4Raw=[];
        foreach ($grouped as $condition=>$group) {
            $d=[];
            foreach($group as $r){
                if($r['final_known_accuracy']===null||$r['final_absent_accuracy']===null)continue;
                $d[]=['seed'=>(int)$r['seed'],'known'=>(float)$r['final_known_accuracy'],'absent'=>(float)$r['final_absent_accuracy'],'delta'=>(float)$r['final_known_accuracy']-(float)$r['final_absent_accuracy']];
            }
            if($d)$h4Raw[$condition]=['pairs'=>$d,'known_minus_absent'=>self::stats(array_column($d,'delta'))];
        }
        $h4Threshold=(float)($thresholds['h4_min_structural_reuse_advantage']??0.02);
        $h4=[
            'primary_recombination_vs_replay'=>self::h4BenefitContrast($rows,'replay_recombination','replay',$h4Threshold),
            'custom_mixture_vs_replay'=>self::h4BenefitContrast($rows,'custom_mixture','replay',$h4Threshold),
            'raw_within_condition_diagnostic'=>$h4Raw,
            'interpretation'=>'Primary H4 is a seed-paired difference-in-differences: (OD-known - replay-known) - (OD-absent - replay-absent). Raw known-minus-absent gaps are diagnostic only.',
        ];

        $h5=[
            'known_transfer'=>self::pairedDelta($rows,'targeted_generation','uniform_generation','final_known_accuracy',(float)($thresholds['h5_min_transfer_gain']??0.0),'higher'),
            'api_calls'=>self::pairedUsageDelta($rows,'api_calls'),
            'tokens'=>self::pairedUsageDelta($rows,'tokens'),
            'wall_seconds'=>self::pairedUsageDelta($rows,'wall_seconds'),
            'efficiency_per_1k_tokens'=>self::pairedEfficiency($rows),
        ];

        $seedCount=count(array_unique(array_map(fn($r)=>(int)$r['seed'],$rows)));
        $status=$seedCount<3?'exploratory_insufficient_replication':(($cfg['suite']??'')==='all'?'controlled_analysis_ready':'partial_suite_analysis');

        return [
            'research_status'=>$status,
            'research_status_note'=>'Statistical summaries describe this controlled implementation. They do not establish external validity or a universal OD advantage.',
            'analysis_plan'=>[
                'paired_by_seed'=>true,
                'ci_method'=>'Student-t 95% CI for means/deltas; H2 vertex also uses deterministic seed-cluster bootstrap',
                'bootstrap_samples'=>(int)($analysisCfg['bootstrap_samples']??1000),
                'effect_thresholds'=>$thresholds,
                'final_test_policy'=>'sealed until after commit/rollback',
            ],
            'summary'=>[
                'condition_runs'=>count($rows),
                'unique_seeds'=>$seedCount,
                'gate_passes'=>count(array_filter($rows,fn($r)=>$r['gate_status']==='passed')),
                'gate_failures'=>count(array_filter($rows,fn($r)=>$r['gate_status']==='failed')),
                'no_change'=>count(array_filter($rows,fn($r)=>$r['gate_status']==='not_applicable_no_change')),
                'rollbacks'=>count(array_filter($rows,fn($r)=>$r['rollback'])),
                'mean_retention'=>self::mean(array_column($rows,'committed_retention')),
                'mean_known_transfer'=>self::mean(array_column($rows,'final_known_accuracy')),
                'mean_absent_factor'=>self::mean(array_column($rows,'final_absent_accuracy')),
            ],
            'conditions'=>$conditions,
            'h1'=>$h1,
            'h2'=>['points'=>$h2points,'models'=>$h2],
            'h3'=>$h3,
            'h4'=>$h4,
            'h5'=>$h5,
            'rows'=>$rows,
            'config'=>$cfg,
            'interpretation_policy'=>'Positive, null, and adverse results are retained. No hypothesis is auto-confirmed from a single run or from model-relative synthetic evidence.',
        ];
    }

    private static function indexBySeed(array $rows,string $condition):array
    {
        $out=[];foreach($rows as $r)if($r['condition']===$condition)$out[(int)$r['seed']]=$r;ksort($out);return$out;
    }

    private static function pairedDelta(array $rows,string $a,string $b,string $metric,float $threshold,string $rule):array
    {
        $aa=self::indexBySeed($rows,$a);$bb=self::indexBySeed($rows,$b);$pairs=[];
        foreach(array_intersect(array_keys($aa),array_keys($bb)) as $seed){
            $av=$aa[$seed][$metric]??null;$bv=$bb[$seed][$metric]??null;if($av===null||$bv===null)continue;
            $pairs[]=['seed'=>$seed,'a'=>(float)$av,'b'=>(float)$bv,'delta'=>(float)$av-(float)$bv];
        }
        $stats=self::stats(array_column($pairs,'delta'));
        $decision='insufficient';
        if(($stats['n']??0)>=2 && $stats['ci95']!==null){
            if($rule==='higher')$decision=$stats['ci95'][0]>=$threshold?'threshold_supported':'threshold_not_supported';
            elseif($rule==='noninferiority')$decision=$stats['ci95'][0]>=-$threshold?'noninferiority_supported':'noninferiority_not_supported';
        }
        return ['a'=>$a,'b'=>$b,'metric'=>$metric,'pairs'=>$pairs,'delta_stats'=>$stats,'effect_threshold'=>$threshold,'rule'=>$rule,'decision'=>$decision];
    }

    private static function h4BenefitContrast(array $rows,string $treatment,string $baseline,float $threshold):array
    {
        $aa=self::indexBySeed($rows,$treatment);$bb=self::indexBySeed($rows,$baseline);$pairs=[];
        foreach(array_intersect(array_keys($aa),array_keys($bb)) as $seed){
            $ak=$aa[$seed]['final_known_accuracy']??null;$aaAbsent=$aa[$seed]['final_absent_accuracy']??null;
            $bk=$bb[$seed]['final_known_accuracy']??null;$bbAbsent=$bb[$seed]['final_absent_accuracy']??null;
            if($ak===null||$aaAbsent===null||$bk===null||$bbAbsent===null)continue;
            $knownGain=(float)$ak-(float)$bk;$absentGain=(float)$aaAbsent-(float)$bbAbsent;$contrast=$knownGain-$absentGain;
            $pairs[]=['seed'=>$seed,'known_gain'=>$knownGain,'absent_gain'=>$absentGain,'benefit_contrast'=>$contrast];
        }
        $stats=self::stats(array_column($pairs,'benefit_contrast'));$decision='insufficient';
        if(($stats['n']??0)>=2&&$stats['ci95']!==null)$decision=$stats['ci95'][0]>=$threshold?'structural_reuse_advantage_supported':'structural_reuse_advantage_not_supported';
        return ['treatment'=>$treatment,'baseline'=>$baseline,'pairs'=>$pairs,'benefit_contrast'=>$stats,'effect_threshold'=>$threshold,'decision'=>$decision];
    }

    private static function pairedUsageDelta(array $rows,string $metric):array
    {
        $a=self::indexBySeed($rows,'targeted_generation');$b=self::indexBySeed($rows,'uniform_generation');$pairs=[];
        foreach(array_intersect(array_keys($a),array_keys($b)) as $seed){
            $av=$metric==='tokens'?(float)($a[$seed]['usage']['input_tokens']+$a[$seed]['usage']['output_tokens']):(float)($a[$seed]['usage'][$metric]??0.0);
            $bv=$metric==='tokens'?(float)($b[$seed]['usage']['input_tokens']+$b[$seed]['usage']['output_tokens']):(float)($b[$seed]['usage'][$metric]??0.0);
            $pairs[]=['seed'=>$seed,'targeted'=>$av,'uniform'=>$bv,'delta'=>$av-$bv];
        }
        return ['pairs'=>$pairs,'delta_stats'=>self::stats(array_column($pairs,'delta'))];
    }

    private static function pairedEfficiency(array $rows):array
    {
        $a=self::indexBySeed($rows,'targeted_generation');$b=self::indexBySeed($rows,'uniform_generation');$pairs=[];
        foreach(array_intersect(array_keys($a),array_keys($b)) as $seed){
            $at=max(1.0,(float)($a[$seed]['usage']['input_tokens']+$a[$seed]['usage']['output_tokens']));
            $bt=max(1.0,(float)($b[$seed]['usage']['input_tokens']+$b[$seed]['usage']['output_tokens']));
            $ae=1000.0*(float)$a[$seed]['final_known_accuracy']/$at;$be=1000.0*(float)$b[$seed]['final_known_accuracy']/$bt;
            $pairs[]=['seed'=>$seed,'targeted'=>$ae,'uniform'=>$be,'delta'=>$ae-$be];
        }
        return ['definition'=>'final_known_accuracy per 1000 observed tokens; diagnostic ratio, reported alongside raw performance and cost','pairs'=>$pairs,'delta_stats'=>self::stats(array_column($pairs,'delta'))];
    }

    private static function mean(array $values): ?float { $v=array_values(array_filter($values,fn($x)=>$x!==null)); return $v?array_sum(array_map('floatval',$v))/count($v):null; }

    public static function stats(array $values): array
    {
        $v=array_values(array_filter($values,fn($x)=>$x!==null && is_numeric($x) && is_finite((float)$x)));
        if (!$v) return ['n'=>0,'mean'=>null,'sd'=>null,'se'=>null,'ci95'=>null];
        $v=array_map('floatval',$v);$n=count($v);$mean=array_sum($v)/$n;
        if ($n<2) return ['n'=>$n,'mean'=>$mean,'sd'=>null,'se'=>null,'ci95'=>null];
        $ss=0.0;foreach($v as $x)$ss+=($x-$mean)**2;$sd=sqrt($ss/($n-1));$se=$sd/sqrt($n);$t=self::t975($n-1);$half=$t*$se;
        return ['n'=>$n,'mean'=>$mean,'sd'=>$sd,'se'=>$se,'ci95'=>[$mean-$half,$mean+$half]];
    }

    private static function t975(int $df):float
    {
        $t=[1=>12.706,2=>4.303,3=>3.182,4=>2.776,5=>2.571,6=>2.447,7=>2.365,8=>2.306,9=>2.262,10=>2.228,11=>2.201,12=>2.179,13=>2.160,14=>2.145,15=>2.131,16=>2.120,17=>2.110,18=>2.101,19=>2.093,20=>2.086,21=>2.080,22=>2.074,23=>2.069,24=>2.064,25=>2.060,26=>2.056,27=>2.052,28=>2.048,29=>2.045,30=>2.042];
        return $t[min(30,max(1,$df))]??1.96;
    }

    private static function fitH2(array $points,array $analysisCfg):array
    {
        if(count($points)<3)return['status'=>'insufficient_points'];
        $x=array_column($points,'novelty');$y=array_column($points,'transfer');$n=count($x);
        $flatMean=array_sum($y)/$n;$flat=['k'=>1,'sse'=>self::sse($y,array_fill(0,$n,$flatMean))];$flat['aicc']=self::aicc($flat['sse'],$n,1);
        $linear=self::linearFit($x,$y);if($linear)$linear['aicc']=self::aicc($linear['sse'],$n,2);
        $quad=self::quadraticFit($x,$y);if($quad)$quad['aicc']=self::aicc($quad['sse'],$n,3);
        $min=min($x);$max=max($x);$vertex=null;$interior=false;$peakGain=null;
        if($quad && $quad['b2']<0 && abs($quad['b2'])>1e-12){$vertex=-$quad['b1']/(2*$quad['b2']);$interior=$vertex>$min&&$vertex<$max;if($interior){$pv=$quad['b0']+$quad['b1']*$vertex+$quad['b2']*$vertex*$vertex;$e1=$quad['b0']+$quad['b1']*$min+$quad['b2']*$min*$min;$e2=$quad['b0']+$quad['b1']*$max+$quad['b2']*$max*$max;$peakGain=$pv-max($e1,$e2);}}
        $seeds=array_values(array_unique(array_map(fn($p)=>(int)$p['seed'],$points)));
        $bootstrap=['status'=>'insufficient_seed_clusters'];
        if(count($seeds)>=3){
            $B=max(200,min(5000,(int)($analysisCfg['bootstrap_samples']??1000)));$state=(int)($analysisCfg['bootstrap_seed']??20260916);$vertices=[];$inverted=0;$valid=0;
            $bySeed=[];foreach($points as $p)$bySeed[(int)$p['seed']][]=$p;
            $randIndex=function(int $max)use(&$state):int{$state^=($state<<13)&0xffffffff;$state^=($state>>17);$state^=($state<<5)&0xffffffff;$u=$state&0x7fffffff;return$u%$max;};
            for($b=0;$b<$B;$b++){$bp=[];for($i=0;$i<count($seeds);$i++){$s=$seeds[$randIndex(count($seeds))];foreach($bySeed[$s] as $p)$bp[]=$p;}$bx=array_column($bp,'novelty');$by=array_column($bp,'transfer');$q=self::quadraticFit($bx,$by);if(!$q||$q['b2']>=0||abs($q['b2'])<1e-12)continue;$v=-$q['b1']/(2*$q['b2']);$valid++;if($v>min($bx)&&$v<max($bx)){$inverted++;$vertices[]=$v;}}
            sort($vertices);$bootstrap=['status'=>'ok','samples'=>$B,'valid_quadratic_samples'=>$valid,'interior_inverted_u_fraction'=>$valid?($inverted/$valid):null,'vertex_ci95'=>count($vertices)>=20?[self::quantile($vertices,.025),self::quantile($vertices,.975)]:null];
        }
        $threshold=(float)(($analysisCfg['effect_thresholds']['h2_min_peak_gain']??0.02));
        return ['status'=>'model_comparison','n'=>$n,'unique_seeds'=>count($seeds),'flat'=>$flat,'linear'=>$linear,'quadratic'=>$quad,'tested_range'=>[$min,$max],'quadratic_vertex'=>$vertex,'interior_inverted_u_candidate'=>$interior,'estimated_peak_gain'=>$peakGain,'effect_threshold'=>$threshold,'peak_threshold_met'=>$peakGain!==null&&$peakGain>=$threshold,'bootstrap'=>$bootstrap];
    }

    private static function aicc(float $sse,int $n,int $k):?float
    {
        if($n<=$k+1)return null;$s=max($sse,1e-15);$aic=$n*log($s/$n)+2*$k;return$aic+(2*$k*($k+1))/($n-$k-1);
    }
    private static function quantile(array $sorted,float $p):float{$n=count($sorted);if($n===1)return(float)$sorted[0];$pos=($n-1)*$p;$lo=(int)floor($pos);$hi=(int)ceil($pos);if($lo===$hi)return(float)$sorted[$lo];$w=$pos-$lo;return(1-$w)*(float)$sorted[$lo]+$w*(float)$sorted[$hi];}
    private static function sse(array $y,array $pred):float{$s=0.0;foreach($y as $i=>$v)$s+=((float)$v-(float)$pred[$i])**2;return$s;}
    private static function linearFit(array $x,array $y):?array{$n=count($x);$sx=array_sum($x);$sy=array_sum($y);$sxx=0.0;$sxy=0.0;for($i=0;$i<$n;$i++){$sxx+=(float)$x[$i]*(float)$x[$i];$sxy+=(float)$x[$i]*(float)$y[$i];}$den=$n*$sxx-$sx*$sx;if(abs($den)<1e-12)return null;$b1=($n*$sxy-$sx*$sy)/$den;$b0=($sy-$b1*$sx)/$n;$pred=array_map(fn($v)=>$b0+$b1*(float)$v,$x);return['k'=>2,'b0'=>$b0,'b1'=>$b1,'sse'=>self::sse($y,$pred)];}
    private static function quadraticFit(array $x,array $y):?array
    {
        $n=count($x);$sx=$sx2=$sx3=$sx4=$sy=$sxy=$sx2y=0.0;
        for($i=0;$i<$n;$i++){$xi=(float)$x[$i];$yi=(float)$y[$i];$x2=$xi*$xi;$sx+=$xi;$sx2+=$x2;$sx3+=$x2*$xi;$sx4+=$x2*$x2;$sy+=$yi;$sxy+=$xi*$yi;$sx2y+=$x2*$yi;}
        $coef=self::solve3([[(float)$n,$sx,$sx2],[$sx,$sx2,$sx3],[$sx2,$sx3,$sx4]],[$sy,$sxy,$sx2y]);if(!$coef)return null;[$b0,$b1,$b2]=$coef;$pred=array_map(fn($v)=>$b0+$b1*(float)$v+$b2*(float)$v*(float)$v,$x);return['k'=>3,'b0'=>$b0,'b1'=>$b1,'b2'=>$b2,'sse'=>self::sse($y,$pred)];
    }
    private static function solve3(array $A,array $b):?array
    {
        for($i=0;$i<3;$i++){$pivot=$i;for($r=$i+1;$r<3;$r++)if(abs($A[$r][$i])>abs($A[$pivot][$i]))$pivot=$r;if(abs($A[$pivot][$i])<1e-12)return null;[$A[$i],$A[$pivot]]=[$A[$pivot],$A[$i]];[$b[$i],$b[$pivot]]=[$b[$pivot],$b[$i]];$d=$A[$i][$i];for($c=$i;$c<3;$c++)$A[$i][$c]/=$d;$b[$i]/=$d;for($r=0;$r<3;$r++){if($r===$i)continue;$f=$A[$r][$i];for($c=$i;$c<3;$c++)$A[$r][$c]-=$f*$A[$i][$c];$b[$r]-=$f*$b[$i];}}
        return$b;
    }
}
