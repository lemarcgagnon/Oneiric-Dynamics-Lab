<?php
declare(strict_types=1);
namespace ODLab;

/**
 * Canonical OD experimental engine for the fixed-model API instantiation.
 *
 * The trainable neural parameter component theta is not accessible through the
 * DeepSeek API. The learning state used here is therefore the explicit memory
 * distribution mu, with the provider model held fixed. Equations that concern
 * theta are not claimed by this implementation.
 */
final class LabEngine
{
    public function __construct(
        private Benchmark $benchmark,
        private array $cfg,
        private Evaluator $evaluator
    ) {}

    /** Reference mu_k^W: uniform distribution over the externally grounded training archive. */
    private function referenceMemory(): array
    {
        return MemoryDistribution::uniform($this->benchmark->splits['external_train']);
    }

    /** Stable common-random-number seed for materializing a memory distribution. */
    private function contextSeed(int $seed, string $family): int
    {
        $hex = substr(hash('sha256', $seed.'|'.$family.'|'.$this->benchmark->splitHash), 0, 8);
        $n = (int)hexdec($hex);
        return ($n & 0x7fffffff) ?: 1;
    }

    private function eval(array $memoryDistribution, array $records, string $splitLabel, int $seed, string $family): array
    {
        $context = MemoryDistribution::materialize(
            $memoryDistribution,
            (int)$this->cfg['benchmark']['active_memory_capacity'],
            $this->contextSeed($seed,$family)
        );
        $result = $this->evaluator->evaluate($context, $records, $splitLabel);
        $result['memory_context_hash'] = Util::hash($context);
        $result['memory_context_n'] = count($context);
        return $result;
    }

    /** Eq. (19), exact finite-reference Euclidean formula under frozen phi_k and B_k. */
    private function novelty(array $candidate, array $frozenReferenceRecords): float
    {
        if ($frozenReferenceRecords === []) throw new \RuntimeException('Novelty reference B_k is empty.');
        $phi = $this->benchmark->evaluationEmbedding($candidate);
        $refs = array_map(fn(array $m): array => $this->benchmark->evaluationEmbedding($m), $frozenReferenceRecords);
        return EquationSet::finiteReferenceNovelty($phi,$refs,$this->benchmark->noveltyScale(),$this->benchmark->noveltyCap());
    }

    private function assignUid(array $candidate, int $seed, int $sequence): array
    {
        $candidate['candidate_uid'] = sprintf(
            'cand:%d:%04d:%s:%s',
            $seed,
            $sequence,
            $candidate['provenance']['production_operator'],
            substr(hash('sha256', Util::stableJson([$candidate['id'],$candidate['provenance']])),0,12)
        );
        return $candidate;
    }

    private function replayKernel(array $referenceRecords, RNG $rng): array
    {
        $source = $rng->choice($referenceRecords);
        $candidate = $source;
        $candidate['provenance']['historical_origin'] = 'external-observation';
        $candidate['provenance']['production_operator'] = 'replay';
        $candidate['provenance']['source_ids'] = [$source['id']];
        $candidate['provenance']['model_version'] = 'not-applicable';
        $candidate['provenance']['intervention'] = null;
        $candidate['provenance']['evidence_status'] = 'external-observation-replayed';
        return $candidate;
    }

    /** K_rec: components come from >=2 externally grounded sources and the conjunction was not observed externally. */
    private function recombinationKernel(array $referenceRecords, RNG $rng, array $targetValues=[]): array
    {
        $maxAttempts = 1000;
        for ($attempt=0;$attempt<$maxAttempts;$attempt++) {
            $factors=[];$sourceIds=[];
            foreach(ResearchContract::FACTORS as $factor) {
                $source=null;
                if ($targetValues && !empty($targetValues[$factor]) && $rng->nextFloat()<0.70) {
                    $want=$rng->choice($targetValues[$factor]);
                    $matches=array_values(array_filter($referenceRecords,fn($m)=>$m['factors'][$factor]===$want));
                    if($matches)$source=$rng->choice($matches);
                }
                if($source===null)$source=$rng->choice($referenceRecords);
                $factors[$factor]=$source['factors'][$factor];
                $sourceIds[]=$source['id'];
            }
            $sourceIds=array_values(array_unique($sourceIds));
            if(count($sourceIds)<2)continue;
            $candidate=$this->benchmark->makeSynthetic($factors,'recombination',$sourceIds);
            // Paper definition and Section 7.3: the particular conjunction must be withheld/unobserved.
            if($this->benchmark->isExternallyObserved($candidate['id']))continue;
            return $candidate;
        }
        throw new \RuntimeException('K_rec could not produce a substantive unobserved conjunction from the frozen reference state.');
    }

    /** K_cf: one declared factor intervention relative to one grounded source record. */
    private function counterfactualKernel(array $referenceRecords, RNG $rng, array $targetValues=[]): array
    {
        $source=$rng->choice($referenceRecords);$factors=$source['factors'];
        $factor=($targetValues && $rng->nextFloat()<0.75)?$rng->choice(array_keys($targetValues)):$rng->choice(ResearchContract::FACTORS);
        $values=$this->benchmark->externalValues()[$factor];
        $alternatives=array_values(array_filter($values,fn($v)=>$v!==$factors[$factor]));
        if(!$alternatives)throw new \RuntimeException('K_cf has no admissible alternative value.');
        $old=$factors[$factor];
        $preferred=array_values(array_filter($targetValues[$factor]??[],fn($v)=>$v!==$old && in_array($v,$alternatives,true)));
        $factors[$factor]=$preferred?$rng->choice($preferred):$rng->choice($alternatives);
        return $this->benchmark->makeSynthetic($factors,'counterfactual',[$source['id']],"$factor:$old->{$factors[$factor]}");
    }

    /**
     * Eq. (3)-(4) finite batch: each candidate independently selects an operator
     * from the frozen mixture Q_k, then draws from the selected component kernel.
     */
    private function generate(array $condition, array $referenceRecords, RNG $rng, int $seed, array $targetValues=[]): array
    {
        $budget=(int)$this->cfg['od']['candidate_budget'];
        if ($budget === 0 || $condition['name'] === 'no_offline') return [];
        $out=[];

        if ($condition['name'] === 'ordinary_augmentation') {
            foreach (array_slice($rng->shuffle($this->benchmark->splits['candidate_pool']),0,$budget) as $x) {
                $out[]=$this->assignUid(
                    $this->benchmark->makeSynthetic($x['factors'],'ordinary_augmentation',[], 'declared_label_preserving_rule'),
                    $seed,
                    count($out)+1
                );
            }
            return $out;
        }

        $mix=$condition['mix'];
        EquationSet::assertProbabilityVector($mix);
        for($j=0;$j<$budget;$j++) {
            $operator=EquationSet::sampleMixtureOperator($mix,$rng);
            $candidate=match($operator){
                'replay'=>$this->replayKernel($referenceRecords,$rng),
                'recombination'=>$this->recombinationKernel($referenceRecords,$rng,$targetValues),
                'counterfactual'=>$this->counterfactualKernel($referenceRecords,$rng,$targetValues),
                default=>throw new \LogicException('Unknown OD operator.'),
            };
            $out[]=$this->assignUid($candidate,$seed,$j+1);
        }
        return $out;
    }

    /** Absolute admission Eq. (5), before any relative score/weight. */
    private function admit(array $candidate, array $condition, array $frozenReferenceRecords): array
    {
        $reasons=[];
        if (!$this->benchmark->oracleValid($candidate)) $reasons[]='oracle_or_structure_failed';
        $p=$candidate['provenance']??[];$operator=$p['production_operator']??'';
        if (($p['historical_origin']??'')==='') $reasons[]='provenance_missing_origin';
        if (!in_array($operator,['replay','recombination','counterfactual','ordinary_augmentation'],true)) $reasons[]='invalid_operator';

        if($operator==='replay') {
            $source=$this->benchmark->externalById((string)($p['source_ids'][0]??''));
            if(!$source || $source['id']!==$candidate['id'] || !hash_equals($source['label'],$candidate['label'])) $reasons[]='invalid_replay_lineage';
        } elseif($operator==='recombination') {
            $ids=array_values(array_unique($p['source_ids']??[]));
            if(count($ids)<2)$reasons[]='recombination_requires_multiple_sources';
            $sources=[];foreach($ids as $id){$s=$this->benchmark->externalById((string)$id);if(!$s){$reasons[]='recombination_unknown_source';break;}$sources[]=$s;}
            if($this->benchmark->isExternallyObserved((string)$candidate['id']))$reasons[]='recombination_conjunction_previously_observed';
            if($sources){
                foreach(ResearchContract::FACTORS as $factor){
                    $ok=false;foreach($sources as $source)if($source['factors'][$factor]===$candidate['factors'][$factor]){$ok=true;break;}
                    if(!$ok){$reasons[]='recombination_factor_without_lineage';break;}
                }
            }
        } elseif($operator==='counterfactual') {
            $source=$this->benchmark->externalById((string)($p['source_ids'][0]??''));
            $intervention=(string)($p['intervention']??'');
            if(!$source || $intervention==='') $reasons[]='invalid_counterfactual_lineage';
            else {
                $diff=[];foreach(ResearchContract::FACTORS as $f)if($source['factors'][$f]!==$candidate['factors'][$f])$diff[]=$f;
                if(count($diff)!==1)$reasons[]='counterfactual_must_change_one_factor';
                else {
                    $f=$diff[0];$expected=$f.':'.$source['factors'][$f].'->'.$candidate['factors'][$f];
                    if($intervention!==$expected)$reasons[]='counterfactual_intervention_mismatch';
                }
            }
        } elseif($operator==='ordinary_augmentation') {
            if(($p['intervention']??null)!=='declared_label_preserving_rule')$reasons[]='augmentation_rule_missing';
        }

        $novelty=$this->novelty($candidate,$frozenReferenceRecords);
        if(isset($condition['novelty_band'])) {
            [$lo,$hi]=$condition['novelty_band'];
            if($novelty+1e-12<$lo || $novelty-1e-12>$hi)$reasons[]='novelty_band_failed';
        }
        return ['admitted'=>$reasons===[],'reasons'=>array_values(array_unique($reasons)),'novelty'=>$novelty];
    }

    private function assessAndWeight(array $candidates,array $condition,array $frozenReferenceRecords,array $od):array
    {
        $assessments=[];$admittedIndexes=[];
        foreach($candidates as $i=>$candidate) {
            $admission=$this->admit($candidate,$condition,$frozenReferenceRecords);
            $coherence=$this->benchmark->pairwiseCoherenceCost($candidate);
            // Anchor validity is mandatory in this controlled benchmark, so A_k=0 for admitted candidates.
            $anchorCost=$this->benchmark->oracleValid($candidate)?0.0:1.0;
            $score=EquationSet::rankingScore(
                $admission['novelty'],$coherence,$anchorCost,
                (float)$od['novelty_weight'],(float)$od['coherence_weight'],(float)$od['anchor_weight']
            );
            $assessments[$i]=[
                'candidate_uid'=>$candidate['candidate_uid'],'experience_id'=>$candidate['id'],
                'admitted'=>$admission['admitted'],'reasons'=>$admission['reasons'],
                'novelty'=>$admission['novelty'],'coherence'=>$coherence,'anchor'=>$anchorCost,'score'=>$score,'weight'=>0.0,
            ];
            if($admission['admitted'])$admittedIndexes[]=$i;
        }
        if(!$admittedIndexes)return $assessments;
        $weighting=$condition['weighting']??$od['weighting'];
        if($weighting==='uniform') {
            $w=1.0/count($admittedIndexes);foreach($admittedIndexes as $i)$assessments[$i]['weight']=$w;
        } else {
            $scores=[];foreach($admittedIndexes as $i)$scores[$i]=$assessments[$i]['score'];
            $weights=EquationSet::gibbsWeights($scores,(float)$od['temperature']);
            foreach($weights as $i=>$w)$assessments[$i]['weight']=$w;
        }
        return $assessments;
    }

    /** Persist answer-level evidence without duplicating cumulative provider usage. */
    private function evaluationEvidence(array $evaluation): array
    {
        return [
            'accuracy'=>$evaluation['accuracy']??null,
            'correct'=>$evaluation['correct']??null,
            'n'=>$evaluation['n']??null,
            'answers'=>$evaluation['answers']??[],
            'memory_context_hash'=>$evaluation['memory_context_hash']??null,
            'memory_context_n'=>$evaluation['memory_context_n']??null,
        ];
    }

    private function distributionHash(array $dist): string { return Util::hash(MemoryDistribution::trace($dist)); }

    private function syntheticMass(array $dist): float
    {
        $mass=0.0;
        foreach($dist as $atom)if(($atom['record']['provenance']['historical_origin']??'')==='synthetic')$mass+=(float)$atom['weight'];
        return $mass;
    }

    private function provenanceIntegrity(array $dist): bool
    {
        try { MemoryDistribution::assertDistribution($dist); } catch (\Throwable) { return false; }
        foreach($dist as $atom){
            $record=$atom['record']??null;$p=is_array($record)?($record['provenance']??null):null;
            if(!is_array($p))return false;
            foreach(['historical_origin','production_operator','source_ids','source_version','model_version','evidence_status','scope'] as $k)if(!array_key_exists($k,$p))return false;
            if(!is_array($p['source_ids']))return false;
            if(!in_array($p['historical_origin'],['external-observation','synthetic'],true))return false;
        }
        return true;
    }

    private function operatorCounts(array $candidates): array
    {
        $out=['replay'=>0,'recombination'=>0,'counterfactual'=>0,'ordinary_augmentation'=>0];
        foreach($candidates as $candidate){$op=$candidate['provenance']['production_operator']??'unknown';$out[$op]=($out[$op]??0)+1;}
        return $out;
    }

    public function runCondition(array $condition,int $seed):array
    {
        $started=microtime(true);
        $usageBefore=$this->evaluator->usage();
        $od=$this->cfg['od'];
        if(isset($condition['eta']))$od['memory_replace_fraction']=(float)$condition['eta'];
        $eta=(float)$od['memory_replace_fraction'];
        $rng=new RNG($seed);

        // Fixed-API specialization of S_k^W: provider theta is fixed; mu, A, and h are explicit.
        $reference=$this->referenceMemory();
        $frozenReferenceRecords=$this->benchmark->splits['external_train'];
        $referenceState=[
            'fixed_model'=>(string)$this->cfg['provider']['model'],
            'mu_hash'=>$this->distributionHash($reference),
            'anchors_hash'=>Util::hash([
                'external_train'=>$this->benchmark->splitManifest()['external_train'],
                'commit_check'=>$this->benchmark->splitManifest()['commit_check'],
                'target_dev'=>$this->benchmark->splitManifest()['target_dev'],
            ]),
            'aux'=>['seed'=>$seed,'benchmark_seed'=>$this->cfg['benchmark']['seed'],'split_hash'=>$this->benchmark->splitHash],
        ];
        $retention=array_slice($this->benchmark->splits['external_train'],0,(int)$this->cfg['benchmark']['retention_eval_n']);

        $referenceRetention=$this->eval($reference,$retention,'retention_reference',$seed,'retention');
        $referenceCheck=$this->eval($reference,$this->benchmark->splits['commit_check'],'commit_check_reference',$seed,'commit_check');

        $targetValues=[];$targetDevEvaluation=null;
        if(!empty($condition['targeted'])) {
            $dev=$this->eval($reference,$this->benchmark->splits['target_dev'],'target_dev_targeting',$seed,'target_dev');
            $targetDevEvaluation=$dev;
            $missed=[];foreach($dev['answers'] as $answer)if(!$answer[3])$missed[$answer[0]]=true;
            foreach($this->benchmark->splits['target_dev'] as $x)if(isset($missed[$x['id']]))foreach($x['factors'] as $factor=>$value)$targetValues[$factor][$value]=$value;
            foreach($targetValues as $factor=>$values)$targetValues[$factor]=array_values($values);
        }
        // h_k specialization: every controller variable that affects the next law is recorded.
        $referenceState['aux']['targeting']=['enabled'=>!empty($condition['targeted']),'target_values'=>$targetValues];

        $candidates=$this->generate($condition,$frozenReferenceRecords,$rng,$seed,$targetValues);
        $assessments=$this->assessAndWeight($candidates,$condition,$frozenReferenceRecords,$od);
        $admitted=array_values(array_filter($assessments,fn($a)=>$a['admitted']));
        $noAdmitted=$admitted===[];
        $qhatAtoms=EquationSet::empiricalDistribution($assessments);
        $candidateByUid=[];foreach($candidates as $candidate)$candidateByUid[$candidate['candidate_uid']]=$candidate;
        $qhat=$qhatAtoms?MemoryDistribution::fromQhat($qhatAtoms,$candidateByUid):[];

        if($noAdmitted) {
            $proposal=$reference;
            $memoryBound=MemoryDistribution::verifyMixtureBound($reference,[],$proposal,0.0);
        } else {
            $proposal=MemoryDistribution::mixture($reference,$qhat,$eta);
            $memoryBound=MemoryDistribution::verifyMixtureBound($reference,$qhat,$proposal,$eta);
            if(!$memoryBound['bound_pass'])throw new \RuntimeException('Eq. (18) runtime verification failed.');
        }
        $stateChanged=$this->distributionHash($proposal)!==$this->distributionHash($reference);
        $noChange=!$stateChanged;
        $noChangeReason=$noAdmitted?'no_admitted_candidates':(!$stateChanged?($eta<=1e-15?'eta_zero':'identical_distribution'):null);

        if($noChange) {
            $proposalRetention=$referenceRetention;$proposalCheck=$referenceCheck;
            $gateStatus='not_applicable_no_change';$committed=$reference;$rollback=false;
            $committedRetention=$referenceRetention;$committedCheck=$referenceCheck;
        } else {
            $proposalRetention=$this->eval($proposal,$retention,'retention_proposal',$seed,'retention');
            $proposalCheck=$this->eval($proposal,$this->benchmark->splits['commit_check'],'commit_check_proposal',$seed,'commit_check');
            $structuralPass=$memoryBound['bound_pass'] && $this->provenanceIntegrity($proposal);
            if(!$structuralPass)throw new \RuntimeException('Proposed memory state violates structural/provenance invariants.');
            if(($condition['gate_enabled']??true)===false) {
                $gateStatus='disabled_ablation';$committed=$proposal;$rollback=false;$committedRetention=$proposalRetention;$committedCheck=$proposalCheck;
            } else {
                $pass=$structuralPass && EquationSet::accuracyGate(
                    $referenceRetention['accuracy'],$proposalRetention['accuracy'],
                    $referenceCheck['accuracy'],$proposalCheck['accuracy'],
                    $od['gate_retention_tolerance'],$od['gate_accuracy_tolerance']
                );
                if($pass){$gateStatus='passed';$committed=$proposal;$rollback=false;$committedRetention=$proposalRetention;$committedCheck=$proposalCheck;}
                else{$gateStatus='failed';$committed=$reference;$rollback=true;$committedRetention=$referenceRetention;$committedCheck=$referenceCheck;}
            }
        }

        // Sealed final data is first touched only after Eq. (14) commit/rollback decision.
        $finalKnown=$this->eval($committed,$this->benchmark->splits['final_known'],'final_known',$seed,'final_known');
        $finalAbsent=$this->eval($committed,$this->benchmark->sealedFinalAbsent(),'final_absent',$seed,'final_absent');

        $noveltyDiag=EquationSet::noveltyDiagnostics($assessments);
        $rejectionReasons=[];foreach($assessments as $a)foreach($a['reasons'] as $reason)$rejectionReasons[$reason]=($rejectionReasons[$reason]??0)+1;
        $usageAfter=$this->evaluator->usage();
        $proposalState=['fixed_model'=>(string)$this->cfg['provider']['model'],'mu_hash'=>$this->distributionHash($proposal),'anchors_hash'=>$referenceState['anchors_hash'],'aux'=>$referenceState['aux']];
        $committedState=['fixed_model'=>(string)$this->cfg['provider']['model'],'mu_hash'=>$this->distributionHash($committed),'anchors_hash'=>$referenceState['anchors_hash'],'aux'=>$referenceState['aux']];
        $eq3Applicable=!in_array($condition['name'],['no_offline','ordinary_augmentation'],true);
        $evaluationEvidence=[
            'retention_reference'=>$this->evaluationEvidence($referenceRetention),
            'commit_check_reference'=>$this->evaluationEvidence($referenceCheck),
            'retention_proposal'=>$this->evaluationEvidence($proposalRetention),
            'commit_check_proposal'=>$this->evaluationEvidence($proposalCheck),
            'final_known'=>$this->evaluationEvidence($finalKnown),
            'final_absent'=>$this->evaluationEvidence($finalAbsent),
        ];
        if($targetDevEvaluation!==null)$evaluationEvidence['target_dev_targeting']=$this->evaluationEvidence($targetDevEvaluation);

        return [
            'condition'=>$condition['name'],'seed'=>$seed,
            'reference_retention'=>$referenceRetention['accuracy'],'reference_check'=>$referenceCheck['accuracy'],
            'proposal_retention'=>$proposalRetention['accuracy'],'proposal_check'=>$proposalCheck['accuracy'],
            'committed_retention'=>$committedRetention['accuracy'],'committed_check'=>$committedCheck['accuracy'],
            'final_known_accuracy'=>$finalKnown['accuracy'],'final_absent_accuracy'=>$finalAbsent['accuracy'],
            'proposed_count'=>count($candidates),'admitted_count'=>count($admitted),'rejected_count'=>count($candidates)-count($admitted),
            'operator_counts'=>$this->operatorCounts($candidates),
            'proposal_novelty_mean'=>$noveltyDiag['proposal_novelty'],'learning_novelty_mean'=>$noveltyDiag['learning_novelty'],
            'admission_fraction'=>$noveltyDiag['admission_fraction'],
            'eta_requested'=>$eta,'eta'=>$noAdmitted?0.0:$eta,
            'memory_tv_proposal_reference'=>$memoryBound['tv_proposal_reference'],
            'memory_tv_qhat_reference'=>$memoryBound['tv_qhat_reference'],
            'synthetic_influence'=>$this->syntheticMass($committed),
            'gate_status'=>$gateStatus,'gate_passed'=>$gateStatus==='passed','rollback'=>$rollback,'no_change'=>$noChange,'no_change_reason'=>$noChangeReason,
            'rejection_reasons'=>$rejectionReasons,
            'state_hash_before'=>$this->distributionHash($reference),'state_hash_proposed'=>$this->distributionHash($proposal),'state_hash_committed'=>$this->distributionHash($committed),
            'candidates'=>$candidates,'assessments'=>$assessments,'targeted_values'=>$targetValues,'evaluation_evidence'=>$evaluationEvidence,
            'equation_trace'=>[
                'fixed_api_state'=>['reference'=>$referenceState,'proposal'=>$proposalState,'committed'=>$committedState,'theta_status'=>'fixed_external_model_not_accessible'],
                'eq3_4'=>['applicable'=>$eq3Applicable,'mixture'=>$condition['mix']??[],'sampling'=>$eq3Applicable?'iid_categorical_operator_draws':'control_condition_not_drawn_from_Qk','realized_operator_counts'=>$this->operatorCounts($candidates)],
                'eq8_qhat'=>$qhatAtoms,
                'eq12_memory'=>[
                    'formula'=>'mu_star=(1-eta)mu_W+eta*Qhat',
                    'eta_requested'=>$eta,'eta'=>$noAdmitted?0.0:$eta,'eta_cap'=>1.0,
                    'reference'=>MemoryDistribution::trace($reference),
                    'qhat'=>$qhat?MemoryDistribution::trace($qhat):[],
                    'proposal'=>MemoryDistribution::trace($proposal),
                ],
                'eq18_tv_bound'=>$memoryBound,
                'eq19'=>[
                    'reference'=>'external_train_frozen_Bk',
                    'representation'=>'categorical_one_hot_frozen',
                    'sigma'=>$this->benchmark->noveltyScale(),
                    'cap'=>$this->benchmark->noveltyCap(),
                    'proposal_novelty'=>$noveltyDiag['proposal_novelty'],
                    'learning_novelty'=>$noveltyDiag['learning_novelty'],
                    'admission_fraction'=>$noveltyDiag['admission_fraction'],
                ],
                'eq13_14_gate'=>[
                    'reference_losses'=>['retention'=>1.0-$referenceRetention['accuracy'],'check'=>1.0-$referenceCheck['accuracy']],
                    'proposal_losses'=>['retention'=>1.0-$proposalRetention['accuracy'],'check'=>1.0-$proposalCheck['accuracy']],
                    'tolerances'=>['retention'=>$od['gate_retention_tolerance'],'check'=>$od['gate_accuracy_tolerance']],
                    'structural_invariants'=>['memory_distribution'=>true,'provenance_integrity'=>$this->provenanceIntegrity($proposal),'eq18_bound'=>$memoryBound['bound_pass']],
                    'status'=>$gateStatus,
                    'committed_is_reference'=>$rollback || $noChange,
                ],
            ],
            'memory_contexts'=>[
                'reference_retention'=>$referenceRetention['memory_context_hash'],
                'proposal_retention'=>$proposalRetention['memory_context_hash'],
                'committed_final_known'=>$finalKnown['memory_context_hash'],
            ],
            'usage'=>[
                'api_calls'=>$usageAfter['api_calls']-$usageBefore['api_calls'],
                'input_tokens'=>$usageAfter['input_tokens']-$usageBefore['input_tokens'],
                'output_tokens'=>$usageAfter['output_tokens']-$usageBefore['output_tokens'],
                'latency_seconds'=>$usageAfter['latency_seconds']-$usageBefore['latency_seconds'],
                'wall_seconds'=>microtime(true)-$started,
            ],
        ];
    }
}
