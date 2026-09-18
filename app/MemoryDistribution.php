<?php
declare(strict_types=1);
namespace ODLab;

/**
 * Finite probability distributions over marked experience records X = Z x L.
 * This class is the canonical owner of the memory-side mathematics used by
 * the fixed-API laboratory.
 */
final class MemoryDistribution
{
    public static function recordKey(array $record): string
    {
        $copy = $record;
        unset($copy['candidate_uid']); // event identity is not part of x=(z,l)
        return hash('sha256', Util::stableJson($copy));
    }

    /** Uniform reference distribution over externally grounded records. */
    public static function uniform(array $records): array
    {
        if ($records === []) throw new \InvalidArgumentException('Memory support must be nonempty.');
        $mass = 1.0 / count($records);
        $dist = [];
        foreach ($records as $record) {
            $key = self::recordKey($record);
            if (isset($dist[$key])) {
                // Identical marked records denote the same atom; aggregate mass.
                $dist[$key]['weight'] += $mass;
            } else {
                $dist[$key] = ['atom_key'=>$key,'record'=>$record,'weight'=>$mass];
            }
        }
        return self::normalize($dist);
    }

    /** Build Q-hat from Eq. (8), aggregating repeated identical atoms. */
    public static function fromQhat(array $qhat, array $candidateByUid): array
    {
        if ($qhat === []) return [];
        $dist = [];
        foreach ($qhat as $atom) {
            $uid = (string)($atom['candidate_uid'] ?? '');
            $weight = (float)($atom['weight'] ?? NAN);
            if ($uid === '' || !isset($candidateByUid[$uid]) || !is_finite($weight) || $weight < 0.0) {
                throw new \InvalidArgumentException('Invalid Q-hat atom.');
            }
            $record = $candidateByUid[$uid];
            $key = self::recordKey($record);
            if (!isset($dist[$key])) $dist[$key] = ['atom_key'=>$key,'record'=>$record,'weight'=>0.0];
            $dist[$key]['weight'] += $weight;
        }
        return self::normalize($dist);
    }

    /** Eq. (12): mu* = (1-eta) muW + eta Qhat. */
    public static function mixture(array $reference, array $qhat, float $eta): array
    {
        self::assertDistribution($reference);
        if (!is_finite($eta) || $eta < 0.0 || $eta > 1.0) throw new \InvalidArgumentException('eta must be in [0,1].');
        if ($qhat === []) {
            if ($eta > 0.0) throw new \InvalidArgumentException('Cannot mix an empty Q-hat with positive eta.');
            return $reference;
        }
        self::assertDistribution($qhat);
        // Exact boundary cases preserve canonical state identity and avoid
        // introducing floating-point renormalization noise into Eq. (14).
        if ($eta === 0.0) return $reference;
        if ($eta === 1.0) return $qhat;
        $out = [];
        foreach ($reference as $key=>$atom) {
            $out[$key] = ['atom_key'=>$key,'record'=>$atom['record'],'weight'=>(1.0-$eta)*(float)$atom['weight']];
        }
        foreach ($qhat as $key=>$atom) {
            if (!isset($out[$key])) $out[$key] = ['atom_key'=>$key,'record'=>$atom['record'],'weight'=>0.0];
            $out[$key]['weight'] += $eta*(float)$atom['weight'];
        }
        // Keep zero-weight atoms out of the operational distribution; protected
        // external source records remain separately available in Benchmark::external_train.
        $out = array_filter($out, fn(array $a): bool => (float)$a['weight'] > 1e-15);
        return self::normalize($out);
    }

    /** Total variation on a finite discrete space: 1/2 ||p-q||_1. */
    public static function totalVariation(array $a, array $b): float
    {
        self::assertDistribution($a); self::assertDistribution($b);
        $keys = array_values(array_unique(array_merge(array_keys($a), array_keys($b))));
        $sum = 0.0;
        foreach ($keys as $key) $sum += abs((float)($a[$key]['weight'] ?? 0.0) - (float)($b[$key]['weight'] ?? 0.0));
        return 0.5*$sum;
    }

    /** Runtime verification of Proposition 3 / Eq. (18) for Eq. (12). */
    public static function verifyMixtureBound(array $reference, array $qhat, array $proposal, float $eta): array
    {
        if ($qhat === []) {
            $tv = self::totalVariation($proposal,$reference);
            return ['tv_proposal_reference'=>$tv,'tv_qhat_reference'=>null,'eta'=>$eta,'rhs'=>0.0,'equality_residual'=>$tv,'bound_pass'=>$tv<=1e-12,'applicable'=>false];
        }
        $tvPR = self::totalVariation($proposal,$reference);
        $tvQR = self::totalVariation($qhat,$reference);
        $rhs = $eta*$tvQR;
        $residual = abs($tvPR-$rhs);
        return [
            'tv_proposal_reference'=>$tvPR,
            'tv_qhat_reference'=>$tvQR,
            'eta'=>$eta,
            'rhs'=>$rhs,
            'equality_residual'=>$residual,
            'bound_pass'=>$residual<=1e-9 && $tvPR <= $eta + 1e-9,
            'applicable'=>true,
        ];
    }

    /**
     * Deterministic systematic materialization of a finite distribution into a
     * fixed-size memory context. It uses one seed-derived offset and the same
     * rule for reference/proposal states, providing common-random-number style
     * pairing without changing the underlying Eq. (12) distribution.
     */
    public static function materialize(array $dist, int $n, int $seed): array
    {
        self::assertDistribution($dist);
        if ($n <= 0) throw new \InvalidArgumentException('Materialized memory size must be > 0.');
        $atoms = array_values($dist);
        usort($atoms, fn(array $a,array $b): int => strcmp((string)$a['atom_key'],(string)$b['atom_key']));
        $rng = new RNG($seed ?: 1);
        $offset = $rng->nextFloat() / $n;
        $out=[]; $j=0; $cdf=(float)$atoms[0]['weight'];
        for ($i=0;$i<$n;$i++) {
            $u = $offset + $i/$n;
            while ($u > $cdf + 1e-15 && $j < count($atoms)-1) { $j++; $cdf += (float)$atoms[$j]['weight']; }
            $out[] = $atoms[$j]['record'];
        }
        return $out;
    }

    public static function supportRecords(array $dist): array
    {
        self::assertDistribution($dist);
        return array_values(array_map(fn(array $a): array => $a['record'], $dist));
    }

    public static function trace(array $dist): array
    {
        self::assertDistribution($dist);
        $rows=[];
        foreach($dist as $key=>$atom)$rows[]=['atom_key'=>$key,'experience_id'=>$atom['record']['id']??null,'weight'=>(float)$atom['weight'],'provenance'=>$atom['record']['provenance']??null];
        usort($rows,fn($a,$b)=>strcmp((string)$a['atom_key'],(string)$b['atom_key']));
        return $rows;
    }

    public static function assertDistribution(array $dist): void
    {
        if ($dist === []) throw new \InvalidArgumentException('Probability distribution must be nonempty.');
        $sum=0.0;
        foreach($dist as $key=>$atom){
            if(!is_array($atom)||!isset($atom['record'],$atom['weight']))throw new \InvalidArgumentException('Malformed distribution atom.');
            $w=(float)$atom['weight'];
            if(!is_finite($w)||$w<0.0)throw new \InvalidArgumentException('Distribution weights must be finite and nonnegative.');
            if((string)($atom['atom_key']??$key)!==(string)$key)throw new \InvalidArgumentException('Distribution key mismatch.');
            $sum+=$w;
        }
        if(abs($sum-1.0)>1e-9)throw new \InvalidArgumentException('Distribution weights must sum to one.');
    }

    private static function normalize(array $dist): array
    {
        $sum=0.0; foreach($dist as $atom)$sum+=(float)$atom['weight'];
        if(!is_finite($sum)||$sum<=0.0)throw new \RuntimeException('Cannot normalize empty/invalid mass.');
        foreach($dist as $key=>$atom){$dist[$key]['atom_key']=(string)$key;$dist[$key]['weight']=(float)$atom['weight']/$sum;}
        self::assertDistribution($dist);
        return $dist;
    }
}
