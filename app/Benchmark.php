<?php
declare(strict_types=1);
namespace ODLab;

final class Benchmark
{
    private array $cfg;
    private RNG $rng;
    private array $tokenMap = [];
    public array $splits = [];
    public string $splitHash;

    private const TOKENS = ['vek','lom','tir','qas','nup','zel','bri','cav','dor','fim','gex','hul','jir','kep','mav','pon','rax','siv','tul','wex','yob','zan','kiv','lur','mes','nax','piv','ros','sul','tev','vax','wem','xis','yor','zup','bex'];

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->rng = new RNG((int)$cfg['seed']);
        $this->makeTokenMap();
        $this->build();
        $this->assertIsolation();
        $this->splitHash = Util::hash($this->splitManifest());
    }

    private function makeTokenMap(): void
    {
        $r = new RNG(((int)$this->cfg['seed']) ^ 0x0A51CE);
        $tokens = $r->shuffle(self::TOKENS);
        $i = 0;
        foreach (ResearchContract::FACTORS as $factor) {
            $values = ResearchContract::KNOWN[$factor];
            foreach ($values as $value) $this->tokenMap[$factor][$value] = $tokens[$i++];
        }
    }

    public function label(array $factors): string
    {
        return implode('|', array_map(fn(string $k): string => $this->tokenMap[$k][$factors[$k]], ResearchContract::FACTORS));
    }

    public static function id(array $factors): string
    {
        return 'item:' . implode(':', array_map(fn(string $k): string => (string)$factors[$k], ResearchContract::FACTORS));
    }

    private function experience(array $factors, string $origin='external-observation', string $operator='external', array $sourceIds=[], ?string $intervention=null): array
    {
        return [
            'id'=>self::id($factors),
            'factors'=>$factors,
            'label'=>$this->label($factors),
            'provenance'=>[
                'historical_origin'=>$origin,
                'production_operator'=>$operator,
                'source_ids'=>array_values($sourceIds),
                'source_version'=>'factorized-opaque-php-v1.4',
                'model_version'=>$origin === 'external-observation' ? 'not-applicable' : 'generator-rule-v1.4',
                'intervention'=>$intervention,
                'evidence_status'=>$origin === 'external-observation' ? 'external-observation' : 'synthetic',
                'scope'=>'learning-example',
            ],
        ];
    }

    private function universe(): array
    {
        $out = [];
        foreach (ResearchContract::KNOWN['shape'] as $shape)
            foreach (ResearchContract::KNOWN['color'] as $color)
                foreach (ResearchContract::KNOWN['position'] as $position)
                    foreach (ResearchContract::KNOWN['texture'] as $texture)
                        $out[] = $this->experience(compact('shape','color','position','texture'));
        return $out;
    }

    private function take(array &$pool, int $n, RNG $rng): array
    {
        $pool = $rng->shuffle($pool);
        if ($n > count($pool)) throw new \RuntimeException('Split exceeds benchmark universe.');
        return array_splice($pool, 0, $n);
    }

    private function externalCoverage(array &$pool, int $n, RNG $rng): array
    {
        $shuffled = $rng->shuffle($pool);
        $need = [];
        foreach (ResearchContract::KNOWN as $factor=>$values) foreach ($values as $value) $need["$factor=$value"] = true;
        $chosen=[]; $ids=[];
        foreach ($shuffled as $x) {
            $covers = false;
            foreach ($x['factors'] as $factor=>$value) {
                $key = "$factor=$value";
                if (isset($need[$key])) { $covers=true; unset($need[$key]); }
            }
            if ($covers && count($chosen) < $n) { $chosen[]=$x; $ids[$x['id']]=true; }
        }
        foreach ($shuffled as $x) {
            if (count($chosen) >= $n) break;
            if (!isset($ids[$x['id']])) { $chosen[]=$x; $ids[$x['id']]=true; }
        }
        if ($need) throw new \RuntimeException('External split does not cover all known factor values.');
        $pool = array_values(array_filter($pool, fn(array $x): bool => !isset($ids[$x['id']])));
        return $chosen;
    }

    private function build(): void
    {
        $r = new RNG((int)$this->cfg['seed']);
        $pool = $this->universe();
        $external = $this->externalCoverage($pool, (int)$this->cfg['external_train_n'], $r);
        $dev = $this->take($pool, (int)$this->cfg['target_dev_n'], $r);
        $check = $this->take($pool, (int)$this->cfg['commit_check_n'], $r);
        $candidatePool = $this->take($pool, (int)$this->cfg['candidate_pool_n'], $r);
        $finalKnown = $this->take($pool, (int)$this->cfg['final_known_n'], $r);
        $this->splits = [
            'external_train'=>$external,
            'target_dev'=>$dev,
            'commit_check'=>$check,
            'candidate_pool'=>$candidatePool,
            'final_known'=>$finalKnown,
        ];
    }

    public function assertIsolation(): void
    {
        $names = ['external_train','target_dev','commit_check','candidate_pool','final_known'];
        for ($i=0;$i<count($names);$i++) for ($j=$i+1;$j<count($names);$j++) {
            $overlap = array_intersect(array_column($this->splits[$names[$i]], 'id'), array_column($this->splits[$names[$j]], 'id'));
            if ($overlap) throw new \RuntimeException("Split leakage: {$names[$i]} vs {$names[$j]}");
        }
        $known = array_merge(...array_map(fn(string $n): array => array_column($this->splits[$n], 'id'), $names));
        if (array_intersect($known, $this->sealedFinalAbsentIds())) throw new \RuntimeException('Sealed absent-factor final split leaks into known splits.');
    }

    public function splitManifest(): array
    {
        $out = [];
        foreach ($this->splits as $name=>$rows) $out[$name] = array_column($rows, 'id');
        // IDs only: split hashing/isolation never instantiate sealed labels or their token.
        $out['final_absent'] = $this->sealedFinalAbsentIds();
        return $out;
    }

    /** IDs only for split hashing/isolation. This path creates no sealed label/token. */
    private function sealedFinalAbsentIds(): array
    {
        $ids=[];
        foreach(array_slice($this->splits['final_known'],0,(int)$this->cfg['final_absent_n']) as $x){
            $f=$x['factors'];
            $f['texture']=ResearchContract::ABSENT_TEXTURE;
            $ids[]=self::id($f);
        }
        return $ids;
    }

    /**
     * Sealed external-evaluation set for H4. The absent texture and its opaque
     * label token are deliberately outside tokenMap, externalValues(), the
     * novelty representation, candidate generation, and admission.
     */
    public function sealedFinalAbsent(): array
    {
        $rows=[];
        $token=$this->sealedAbsentTextureToken();
        foreach(array_slice($this->splits['final_known'],0,(int)$this->cfg['final_absent_n']) as $x){
            $f=$x['factors'];$f['texture']=ResearchContract::ABSENT_TEXTURE;
            $parts=[];
            foreach(ResearchContract::FACTORS as $factor){
                $parts[]=$factor==='texture'?$token:$this->tokenMap[$factor][$f[$factor]];
            }
            $row=[
                'id'=>self::id($f),'factors'=>$f,'label'=>implode('|',$parts),
                'provenance'=>[
                    'historical_origin'=>'external-observation','production_operator'=>'sealed-final-evaluator',
                    'source_ids'=>[],'source_version'=>'factorized-opaque-php-v1.4-sealed',
                    'model_version'=>'not-applicable','intervention'=>null,'evidence_status'=>'sealed-external-test',
                    'scope'=>'final-evaluation-only',
                ],
            ];
            $rows[]=$row;
        }
        return $rows;
    }

    private function sealedAbsentTextureToken(): string
    {
        // Separate namespace from the generator's token map; never exposed in prompts.
        return 'abs'.substr(hash('sha256','sealed-absent-texture|'.$this->cfg['seed']),0,9);
    }

    public function externalValues(): array
    {
        $out=[];
        foreach (ResearchContract::FACTORS as $factor) {
            $out[$factor] = array_values(array_unique(array_map(fn(array $x): string => $x['factors'][$factor], $this->splits['external_train'])));
        }
        return $out;
    }

    public function externalById(string $id): ?array
    {
        foreach ($this->splits['external_train'] as $x) if ($x['id'] === $id) return $x;
        return null;
    }

    public function isExternallyObserved(string $id): bool
    {
        return $this->externalById($id) !== null;
    }

    public function makeSynthetic(array $factors, string $operator, array $sourceIds=[], ?string $intervention=null): array
    {
        return $this->experience($factors, 'synthetic', $operator, $sourceIds, $intervention);
    }


    /** Frozen evaluation representation phi_k for Eq. (19): concatenated categorical one-hot. */
    public function evaluationEmbedding(array $x): array
    {
        if(!isset($x['factors'])||!is_array($x['factors']))throw new \InvalidArgumentException('Missing factors for evaluation embedding.');
        $vector=[];
        foreach(ResearchContract::FACTORS as $factor){
            $domain=ResearchContract::KNOWN[$factor];
            $value=$x['factors'][$factor]??null;
            if(!in_array($value,$domain,true))throw new \InvalidArgumentException('Unknown factor value in evaluation embedding.');
            foreach($domain as $candidate)$vector[]=$value===$candidate?1.0:0.0;
        }
        return $vector;
    }

    /** With one-hot categorical phi, four simultaneous mismatches have Euclidean distance sqrt(8). */
    public function noveltyScale(): float{return sqrt(2.0*count(ResearchContract::FACTORS));}
    public function noveltyCap(): float{return 1.0;}

    /** Independent benchmark oracle; candidate-pool membership is not treated as truth. */
    public function oracleValid(array $x): bool
    {
        if (!isset($x['factors'],$x['label'],$x['provenance']) || !is_array($x['factors']) || !is_array($x['provenance'])) return false;
        if (array_keys($x['factors']) !== ResearchContract::FACTORS) return false;
        foreach (ResearchContract::FACTORS as $factor) {
            if (!in_array($x['factors'][$factor], ResearchContract::KNOWN[$factor], true)) return false;
        }
        return hash_equals($this->label($x['factors']), (string)$x['label']);
    }

    public function pairwiseCoherenceCost(array $x): float
    {
        $observed = [];
        foreach ($this->splits['external_train'] as $row) {
            for ($i=0;$i<count(ResearchContract::FACTORS);$i++) for ($j=$i+1;$j<count(ResearchContract::FACTORS);$j++) {
                $a=ResearchContract::FACTORS[$i]; $b=ResearchContract::FACTORS[$j];
                $observed["$a={$row['factors'][$a]}|$b={$row['factors'][$b]}"] = true;
            }
        }
        $pairs=0; $unseen=0;
        for ($i=0;$i<count(ResearchContract::FACTORS);$i++) for ($j=$i+1;$j<count(ResearchContract::FACTORS);$j++) {
            $a=ResearchContract::FACTORS[$i]; $b=ResearchContract::FACTORS[$j];
            $pairs++;
            if (!isset($observed["$a={$x['factors'][$a]}|$b={$x['factors'][$b]}"])) $unseen++;
        }
        return $pairs ? $unseen/$pairs : 0.0;
    }
}
