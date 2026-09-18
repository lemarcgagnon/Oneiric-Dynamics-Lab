<?php
declare(strict_types=1);
namespace ODLab;

final class ResearchContract
{
    public const PAPER_TITLE = 'Oneiric Dynamics for Artificial Intelligence — A Research Framework for Offline Generative Recombination, Memory Consolidation, and Constrained Self-Revision';
    public const PAPER_DATE = '2026-09-16';
    public const PAPER_SHA256 = '71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6';
    public const PROTOCOL_VERSION = 'ODLAB-PHP-1.4.0';
    public const PROVIDER_MODEL = 'deepseek-flash';
    public const PROVIDER_BASE_URL = 'https://api.deepseek.com';
    public const FACTORS = ['shape','color','position','texture'];
    public const KNOWN = [
        'shape'=>['orb','kite','arch','fork','ring','star'],
        'color'=>['amber','cobalt','jade','lilac','umber','silver'],
        'position'=>['north','south','east','west','center'],
        'texture'=>['plain','striped','dotted','crosshatched'],
    ];
    public const ABSENT_TEXTURE = 'velvet';

    public static function defaults(): array
    {
        return [
            'research_contract'=>self::contractMetadata(),
            'suite'=>'core',
            'seeds'=>[101],
            'benchmark'=>[
                'seed'=>20260916,
                'external_train_n'=>36,
                'target_dev_n'=>16,
                'commit_check_n'=>16,
                'candidate_pool_n'=>160,
                'final_known_n'=>40,
                'final_absent_n'=>20,
                'active_memory_capacity'=>12,
                'retention_eval_n'=>16,
            ],
            'od'=>[
                'rho'=>0.66,
                'counterfactual_share'=>0.50,
                'candidate_budget'=>24,
                'memory_replace_fraction'=>0.25,
                'gate_accuracy_tolerance'=>0.05,
                'gate_retention_tolerance'=>0.05,
                'weighting'=>'gibbs',
                'temperature'=>0.35,
                'novelty_weight'=>1.0,
                'coherence_weight'=>0.50,
                // Hard anchor compatibility is handled by absolute admission in this benchmark.
                'anchor_weight'=>0.0,
                'novelty_min'=>0.0,
                'novelty_max'=>1.0,
            ],
            'budget'=>[
                'max_api_calls'=>120,
                'max_input_tokens'=>500000,
                'max_output_tokens'=>100000,
            ],
            'analysis'=>[
                'bootstrap_samples'=>1000,
                'bootstrap_seed'=>20260916,
                'effect_thresholds'=>[
                    'h1_min_transfer_gain'=>0.02,
                    'h1_max_retention_loss'=>0.02,
                    'h2_min_peak_gain'=>0.02,
                    'h4_min_structural_reuse_advantage'=>0.02,
                    'h5_min_transfer_gain'=>0.00,
                ],
            ],
            'provider'=>[
                'model'=>self::PROVIDER_MODEL,
                'base_url'=>self::PROVIDER_BASE_URL,
                'max_retries'=>2,
                'timeout_seconds'=>90,
                'max_output_tokens_per_call'=>2048,
                'batch_size'=>64,
            ],
        ];
    }

    public static function contractMetadata(): array
    {
        return ['paper_title'=>self::PAPER_TITLE,'paper_date'=>self::PAPER_DATE,'paper_sha256'=>self::PAPER_SHA256,'protocol_version'=>self::PROTOCOL_VERSION,'math_profile'=>self::mathCoverage()];
    }

    public static function mathCoverage(): array
    {
        return [
            ['id'=>'eq1_2','label'=>'Eq. (1)-(2) state/reference cycle','status'=>'specialized','runtime'=>true,'owner'=>'LabEngine fixed_api_state','note'=>'theta is fixed outside the API; mu, anchors A, and auxiliary h are explicit and hashed'],
            ['id'=>'eq3_4','label'=>'Eq. (3)-(4) mixture kernel','status'=>'exact','runtime'=>true,'owner'=>'EquationSet::mixture/sampleMixtureOperator + LabEngine kernels','note'=>'candidate operators are drawn iid from the frozen categorical mixture'],
            ['id'=>'eq5','label'=>'Eq. (5) absolute admission','status'=>'specialized','runtime'=>true,'owner'=>'LabEngine::admit'],
            ['id'=>'eq6','label'=>'Eq. (6) ranking','status'=>'specialized','runtime'=>true,'owner'=>'EquationSet::rankingScore','note'=>'lambda_A=0 because anchor validity is a mandatory admission condition in this controlled benchmark'],
            ['id'=>'eq7','label'=>'Eq. (7) Gibbs weighting','status'=>'exact','runtime'=>true,'owner'=>'EquationSet::gibbsWeights'],
            ['id'=>'eq8','label'=>'Eq. (8) admitted empirical distribution','status'=>'exact','runtime'=>true,'owner'=>'EquationSet::empiricalDistribution + MemoryDistribution::fromQhat'],
            ['id'=>'eq9_11','label'=>'Eq. (9)-(11) neural parameter proposal','status'=>'not_claimed','runtime'=>false,'owner'=>'fixed external API boundary'],
            ['id'=>'eq12','label'=>'Eq. (12) memory-mixture proposal','status'=>'exact','runtime'=>true,'owner'=>'MemoryDistribution::mixture','note'=>'the paper constraint 0<=eta_k<=eta_bar_k<=1 is specialized with eta_bar_k=1 and enforced server-side'],
            ['id'=>'eq13_14','label'=>'Eq. (13)-(14) joint gate / rollback','status'=>'specialized','runtime'=>true,'owner'=>'EquationSet::lossGate/accuracyGate + LabEngine commit/rollback'],
            ['id'=>'eq15_markov','label'=>'Eq. (15) Markov cycle kernel','status'=>'documented','runtime'=>false,'owner'=>'no global sufficiency/time-homogeneity claim'],
            ['id'=>'eq16_17','label'=>'Eq. (16)-(17) parameter displacement bounds','status'=>'not_claimed','runtime'=>false,'owner'=>'theta inaccessible through fixed API'],
            ['id'=>'eq18','label'=>'Eq. (18) memory TV displacement','status'=>'exact','runtime'=>true,'owner'=>'MemoryDistribution::totalVariation/verifyMixtureBound'],
            ['id'=>'eq19','label'=>'Eq. (19) finite-reference novelty','status'=>'exact','runtime'=>true,'owner'=>'EquationSet::finiteReferenceNovelty + frozen one-hot phi'],
            ['id'=>'eq20','label'=>'Eq. (20) proposal/admission/influence separation','status'=>'exact','runtime'=>true,'owner'=>'EquationSet::noveltyDiagnostics'],
        ];
    }

    public static function deriveMix(array $od): array
    {
        return EquationSet::mixture((float)$od['rho'], (float)$od['counterfactual_share']);
    }

    public static function validate(array $input): array
    {
        $c = array_replace_recursive(self::defaults(), $input);
        // Research authority is not user-editable from API or Mission Control.
        $c['research_contract'] = self::contractMetadata();
        $allowedSuites = ['smoke','core','h2','h3','h5','all','custom'];
        if (!in_array($c['suite'], $allowedSuites, true)) throw new \InvalidArgumentException('Invalid suite.');

        $o =& $c['od'];
        foreach (['rho','counterfactual_share','memory_replace_fraction','gate_accuracy_tolerance','gate_retention_tolerance','temperature','novelty_weight','coherence_weight','anchor_weight','novelty_min','novelty_max'] as $k) {
            if (!is_numeric($o[$k]) || !is_finite((float)$o[$k])) throw new \InvalidArgumentException("Invalid OD parameter: $k");
            $o[$k] = (float)$o[$k];
        }
        if ($o['rho'] < 0 || $o['rho'] > 1 || $o['counterfactual_share'] < 0 || $o['counterfactual_share'] > 1) throw new \InvalidArgumentException('Mixture parameters outside [0,1].');
        if ($o['memory_replace_fraction'] < 0 || $o['memory_replace_fraction'] > 1) throw new \InvalidArgumentException('Memory replacement fraction outside [0,1].');
        if ($o['temperature'] <= 0) throw new \InvalidArgumentException('Temperature must be > 0.');
        if ($o['gate_accuracy_tolerance'] < 0 || $o['gate_accuracy_tolerance'] > 1 || $o['gate_retention_tolerance'] < 0 || $o['gate_retention_tolerance'] > 1) throw new \InvalidArgumentException('Gate tolerance outside [0,1].');
        if ($o['novelty_min'] < 0 || $o['novelty_max'] > 1 || $o['novelty_min'] > $o['novelty_max']) throw new \InvalidArgumentException('Invalid novelty band.');
        foreach (['novelty_weight','coherence_weight','anchor_weight'] as $k) if ($o[$k] < 0) throw new \InvalidArgumentException("$k must be nonnegative.");
        if ($o['anchor_weight'] != 0.0) throw new \InvalidArgumentException('anchor_weight is fixed at 0 in this benchmark because anchor disagreement is a hard admission criterion.');
        $o['candidate_budget'] = max(0, min(200, (int)$o['candidate_budget']));
        if (!in_array($o['weighting'], ['gibbs','uniform'], true)) throw new \InvalidArgumentException('Invalid weighting policy.');

        $b =& $c['benchmark'];
        foreach (['seed','external_train_n','target_dev_n','commit_check_n','candidate_pool_n','final_known_n','final_absent_n','active_memory_capacity','retention_eval_n'] as $k) {
            if (!is_numeric($b[$k])) throw new \InvalidArgumentException("Invalid benchmark parameter: $k");
            $b[$k] = (int)$b[$k];
        }
        foreach (['external_train_n','target_dev_n','commit_check_n','candidate_pool_n','final_known_n','final_absent_n','active_memory_capacity','retention_eval_n'] as $k) if ($b[$k] <= 0) throw new \InvalidArgumentException("$k must be > 0.");
        $knownUniverse = count(self::KNOWN['shape']) * count(self::KNOWN['color']) * count(self::KNOWN['position']) * count(self::KNOWN['texture']);
        if ($b['external_train_n'] + $b['target_dev_n'] + $b['commit_check_n'] + $b['candidate_pool_n'] + $b['final_known_n'] > $knownUniverse) throw new \InvalidArgumentException('Known split sizes exceed benchmark universe.');
        if ($b['active_memory_capacity'] > $b['external_train_n']) throw new \InvalidArgumentException('Active memory cannot exceed external training set.');
        if ($b['retention_eval_n'] > $b['external_train_n']) throw new \InvalidArgumentException('Retention evaluation exceeds external training set.');
        if ($b['final_absent_n'] > $b['final_known_n']) throw new \InvalidArgumentException('Absent-factor final size cannot exceed final-known size.');
        if ($o['candidate_budget'] > $b['candidate_pool_n']) throw new \InvalidArgumentException('Candidate budget cannot exceed candidate-pool capacity for matched augmentation control.');

        $a =& $c['analysis'];
        $a['bootstrap_samples']=max(200,min(5000,(int)($a['bootstrap_samples']??1000)));
        $a['bootstrap_seed']=(int)($a['bootstrap_seed']??20260916);
        foreach(['h1_min_transfer_gain','h1_max_retention_loss','h2_min_peak_gain','h4_min_structural_reuse_advantage','h5_min_transfer_gain'] as $k){
            if(!isset($a['effect_thresholds'][$k])||!is_numeric($a['effect_thresholds'][$k])||!is_finite((float)$a['effect_thresholds'][$k]))throw new \InvalidArgumentException('Invalid analysis threshold: '.$k);
            $a['effect_thresholds'][$k]=(float)$a['effect_thresholds'][$k];
            if($a['effect_thresholds'][$k]<0.0||$a['effect_thresholds'][$k]>1.0)throw new \InvalidArgumentException('Analysis thresholds must be in [0,1].');
        }

        foreach (['max_api_calls','max_input_tokens','max_output_tokens'] as $k) $c['budget'][$k] = max(1, (int)$c['budget'][$k]);
        $p =& $c['provider'];
        // The BYOK credential must never be redirectable to an arbitrary client-supplied host.
        // Model/endpoint are part of this frozen laboratory implementation, not UI authority.
        if((string)($p['model']??'')!==self::PROVIDER_MODEL || rtrim((string)($p['base_url']??''),'/')!==self::PROVIDER_BASE_URL){
            throw new \InvalidArgumentException('Provider model/base URL are fixed by the research contract.');
        }
        $p['model']=self::PROVIDER_MODEL;$p['base_url']=self::PROVIDER_BASE_URL;
        $p['max_retries'] = max(0, min(5, (int)$p['max_retries']));
        $p['timeout_seconds'] = max(5, min(300, (int)$p['timeout_seconds']));
        $p['max_output_tokens_per_call'] = max(64, min(393216, (int)$p['max_output_tokens_per_call']));
        $p['batch_size'] = max(1, min(256, (int)$p['batch_size']));
        if (!is_string($p['model']) || trim($p['model']) === '' || !is_string($p['base_url']) || !str_starts_with($p['base_url'], 'https://')) throw new \InvalidArgumentException('Invalid provider configuration.');

        $c['seeds'] = array_values(array_unique(array_map('intval', $c['seeds'] ?: [101])));
        if (count($c['seeds']) > 20) throw new \InvalidArgumentException('At most 20 seeds per mission.');
        $c['derived_mix'] = self::deriveMix($o);
        EquationSet::assertProbabilityVector($c['derived_mix']);
        return $c;
    }

    public static function conditions(string $suite, array $cfg): array
    {
        $mix = $cfg['derived_mix'];
        $smoke = [
            ['name'=>'no_offline','mix'=>[]],
            ['name'=>'replay_recombination','mix'=>['replay'=>0.5,'recombination'=>0.5,'counterfactual'=>0.0]],
        ];
        $core = [
            ['name'=>'no_offline','mix'=>[]],
            ['name'=>'ordinary_augmentation','mix'=>[],'weighting'=>'uniform'],
            ['name'=>'replay','mix'=>['replay'=>1.0,'recombination'=>0.0,'counterfactual'=>0.0]],
            ['name'=>'replay_recombination','mix'=>['replay'=>0.5,'recombination'=>0.5,'counterfactual'=>0.0]],
            ['name'=>'replay_counterfactual','mix'=>['replay'=>0.5,'recombination'=>0.0,'counterfactual'=>0.5]],
            ['name'=>'custom_mixture','mix'=>$mix],
            ['name'=>'custom_mixture_uniform','mix'=>$mix,'weighting'=>'uniform'],
        ];
        $h2 = [
            ['name'=>'novelty_low','mix'=>$mix,'novelty_band'=>[0.0,0.49]],
            ['name'=>'novelty_mid','mix'=>$mix,'novelty_band'=>[0.50,0.79]],
            ['name'=>'novelty_high','mix'=>$mix,'novelty_band'=>[0.80,1.00]],
        ];
        // Fixed-API analogue of H3: Eq. (12) proposal magnitude eta versus hard Eq. (13) gate.
        // This does not claim the neural proximal-regularization theorem of Eq. (9)-(11).
        $h3 = [
            ['name'=>'h3fapi_eta_low_gate','mix'=>$mix,'eta'=>0.25,'gate_enabled'=>true],
            ['name'=>'h3fapi_eta_high_gate','mix'=>$mix,'eta'=>0.75,'gate_enabled'=>true],
            ['name'=>'h3fapi_eta_high_no_gate','mix'=>$mix,'eta'=>0.75,'gate_enabled'=>false],
        ];
        $h5 = [
            ['name'=>'uniform_generation','mix'=>$mix,'targeted'=>false],
            ['name'=>'targeted_generation','mix'=>$mix,'targeted'=>true],
        ];
        $list = match ($suite) {
            'smoke'=>$smoke,
            'core'=>$core,
            'h2'=>$h2,
            'h3'=>$h3,
            'h5'=>$h5,
            'all'=>array_merge($core,$h2,$h3,$h5),
            'custom'=>[['name'=>'custom_mixture','mix'=>$mix]],
            default=>throw new \InvalidArgumentException('Invalid suite.'),
        };
        foreach ($list as $condition) if (($condition['mix'] ?? []) !== []) EquationSet::assertProbabilityVector($condition['mix']);
        return $list;
    }

    /** Estimated evaluator calls: baseline assumes first-attempt success; worst includes every retry. */
    public static function estimateApiCalls(array $cfg): array
    {
        $conditions = self::conditions($cfg['suite'], $cfg);
        $batchSize = (int)$cfg['provider']['batch_size'];
        $batches = static fn(int $n): int => (int)ceil($n / $batchSize);
        $baseline = 0;
        foreach ($conditions as $condition) {
            // reference retention/check + proposal retention/check + final-known/final-absent.
            // no-change may skip proposal calls, so this remains conservative for first-attempt success.
            $per = $batches($cfg['benchmark']['retention_eval_n'])
                + $batches($cfg['benchmark']['commit_check_n'])
                + $batches($cfg['benchmark']['retention_eval_n'])
                + $batches($cfg['benchmark']['commit_check_n'])
                + $batches($cfg['benchmark']['final_known_n'])
                + $batches($cfg['benchmark']['final_absent_n']);
            if (!empty($condition['targeted'])) $per += $batches($cfg['benchmark']['target_dev_n']);
            $baseline += $per;
        }
        $baseline *= count($cfg['seeds']);
        return ['baseline'=>$baseline,'worst_case_attempts'=>$baseline * ((int)$cfg['provider']['max_retries'] + 1)];
    }
}
