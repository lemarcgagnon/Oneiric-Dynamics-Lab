<?php
declare(strict_types=1);
namespace ODLab;

/**
 * Canonical implementation of mathematical rules that actually govern the
 * fixed-API OD laboratory. Presentation code must never duplicate them.
 */
final class EquationSet
{
    /** Eq. (3)-(4): Q = alpha Krep + beta Krec + gamma Kcf. */
    public static function mixture(float $rho, float $counterfactualShare): array
    {
        if (!is_finite($rho) || !is_finite($counterfactualShare)) {
            throw new \InvalidArgumentException('Mixture values must be finite.');
        }
        if ($rho < 0.0 || $rho > 1.0 || $counterfactualShare < 0.0 || $counterfactualShare > 1.0) {
            throw new \InvalidArgumentException('Mixture values must be in [0,1].');
        }
        $mix = [
            'replay' => 1.0 - $rho,
            'recombination' => $rho * (1.0 - $counterfactualShare),
            'counterfactual' => $rho * $counterfactualShare,
        ];
        self::assertProbabilityVector($mix);
        return $mix;
    }


    /**
     * One categorical draw from Eq. (3). Repeating this method with the same
     * frozen state yields the finite-batch implementation of the paper's
     * conditionally independent draws from Q_k.
     */
    public static function sampleMixtureOperator(array $mix, RNG $rng): string
    {
        self::assertProbabilityVector($mix);
        $u = $rng->nextFloat();
        $cumulative = 0.0;
        foreach (['replay','recombination','counterfactual'] as $operator) {
            $cumulative += (float)$mix[$operator];
            if ($u < $cumulative || $operator === 'counterfactual') return $operator;
        }
        throw new \LogicException('Unreachable mixture draw.');
    }

    public static function assertProbabilityVector(array $mix): void
    {
        $expected = ['replay', 'recombination', 'counterfactual'];
        foreach ($expected as $k) {
            if (!array_key_exists($k, $mix) || !is_numeric($mix[$k]) || !is_finite((float)$mix[$k]) || (float)$mix[$k] < 0.0) {
                throw new \InvalidArgumentException("Invalid mixture coefficient: $k");
            }
        }
        if (array_diff(array_keys($mix), $expected) || array_diff($expected,array_keys($mix))) {
            throw new \InvalidArgumentException('Mixture must contain exactly replay, recombination, and counterfactual.');
        }
        if (abs(array_sum(array_map('floatval', $mix)) - 1.0) > 1e-9) {
            throw new \InvalidArgumentException('Mixture coefficients must sum to one.');
        }
    }

    /** Eq. (6): r_k(x)=lambda_N N_k(x)-lambda_C C_k(x)-lambda_A A_k(x). In this benchmark A is enforced absolutely, so lambda_A=0. */
    public static function rankingScore(
        float $novelty,
        float $coherenceCost,
        float $anchorCost,
        float $lambdaN,
        float $lambdaC,
        float $lambdaA
    ): float {
        foreach ([$novelty, $coherenceCost, $anchorCost, $lambdaN, $lambdaC, $lambdaA] as $v) {
            if (!is_finite($v)) throw new \InvalidArgumentException('Ranking inputs must be finite.');
        }
        if ($lambdaN < 0 || $lambdaC < 0 || $lambdaA < 0) {
            throw new \InvalidArgumentException('Ranking weights must be nonnegative.');
        }
        return $lambdaN * $novelty - $lambdaC * $coherenceCost - $lambdaA * $anchorCost;
    }

    /** Eq. (7): omega_j = exp((r_j-r_max)/T) / sum_i exp((r_i-r_max)/T), T>0. */
    public static function gibbsWeights(array $scores, float $temperature): array
    {
        if ($scores === []) return [];
        if (!is_finite($temperature) || $temperature <= 0.0) {
            throw new \InvalidArgumentException('Temperature must be finite and > 0.');
        }
        foreach ($scores as $s) if (!is_numeric($s) || !is_finite((float)$s)) {
            throw new \InvalidArgumentException('Scores must be finite.');
        }
        $max = max(array_map('floatval', $scores));
        $exp = [];$den = 0.0;
        foreach ($scores as $k => $s) {
            $e = exp(((float)$s - $max) / $temperature);
            $exp[$k] = $e;$den += $e;
        }
        if (!is_finite($den) || $den <= 0.0) throw new \RuntimeException('Invalid Gibbs denominator.');
        $weights = [];
        foreach ($exp as $k => $e) $weights[$k] = $e / $den;
        if (abs(array_sum($weights) - 1.0) > 1e-9) throw new \RuntimeException('Gibbs weights do not sum to one.');
        return $weights;
    }

    /**
     * Eq. (8): Qhat_k = sum_{j in J_k} omega_{k,j} delta_{x_j}; explicit
     * weighted empirical distribution over admitted
     * candidate draws. Candidate UID is retained as evidence of the draw; if two
     * x_j are mathematically identical, downstream measure operations aggregate
     * their mass by the canonical marked-record key.
     */
    public static function empiricalDistribution(array $assessments): array
    {
        $atoms=[];$sum=0.0;
        foreach($assessments as $a){
            if(empty($a['admitted']))continue;
            $w=(float)($a['weight']??0.0);
            if(!is_finite($w)||$w<0.0)throw new \InvalidArgumentException('Invalid empirical-distribution weight.');
            $atoms[]=['candidate_uid'=>(string)$a['candidate_uid'],'experience_id'=>(string)$a['experience_id'],'weight'=>$w];
            $sum+=$w;
        }
        if($atoms===[])return[];
        if(abs($sum-1.0)>1e-9)throw new \RuntimeException('Admitted empirical-distribution weights must sum to one.');
        return $atoms;
    }

    /**
     * Eq. (13) in its native lower-is-better loss form:
     * L(proposal) <= L(reference) + delta, for every protected check.
     */
    public static function lossGate(array $referenceLosses, array $proposalLosses, array $tolerances): bool
    {
        if(array_keys($referenceLosses)!==array_keys($proposalLosses) || array_keys($referenceLosses)!==array_keys($tolerances)){
            throw new \InvalidArgumentException('Gate vectors must have identical ordered keys.');
        }
        foreach($referenceLosses as $k=>$ref){
            $prop=(float)$proposalLosses[$k];$delta=(float)$tolerances[$k];$ref=(float)$ref;
            if(!is_finite($ref)||!is_finite($prop)||!is_finite($delta)||$delta<0.0)throw new \InvalidArgumentException('Gate losses/tolerances must be finite and tolerances nonnegative.');
            if($prop > $ref + $delta + 1e-12)return false;
        }
        return true;
    }

    /** Accuracy specialization of Eq. (13): L=1-accuracy. */
    public static function accuracyGate(
        float $referenceRetention,
        float $proposalRetention,
        float $referenceCheck,
        float $proposalCheck,
        float $retentionTolerance,
        float $checkTolerance
    ): bool {
        foreach ([$referenceRetention,$proposalRetention,$referenceCheck,$proposalCheck] as $v) {
            if (!is_finite($v) || $v<0.0 || $v>1.0) throw new \InvalidArgumentException('Accuracies must be finite in [0,1].');
        }
        return self::lossGate(
            ['retention'=>1.0-$referenceRetention,'check'=>1.0-$referenceCheck],
            ['retention'=>1.0-$proposalRetention,'check'=>1.0-$proposalCheck],
            ['retention'=>$retentionTolerance,'check'=>$checkTolerance]
        );
    }

    /** Backward-compatible alias; accuracyGate is canonical. */
    public static function gate(float $referenceRetention,float $proposalRetention,float $referenceCheck,float $proposalCheck,float $retentionTolerance,float $checkTolerance): bool
    {
        return self::accuracyGate($referenceRetention,$proposalRetention,$referenceCheck,$proposalCheck,$retentionTolerance,$checkTolerance);
    }

    /** Eq. (19): N_k(x)=min{Nmax, min_{z_i in B_k} ||phi(z)-phi(z_i)||_2 / sigma_k}. */
    public static function finiteReferenceNovelty(array $vector, array $referenceVectors, float $sigma, float $cap): float
    {
        if($referenceVectors===[])throw new \InvalidArgumentException('Novelty reference must be nonempty.');
        if(!is_finite($sigma)||$sigma<=0.0||!is_finite($cap)||$cap<=0.0)throw new \InvalidArgumentException('Novelty sigma/cap must be positive and finite.');
        $n=count($vector);if($n===0)throw new \InvalidArgumentException('Novelty vector must be nonempty.');
        foreach($vector as $x)if(!is_numeric($x)||!is_finite((float)$x))throw new \InvalidArgumentException('Novelty vector must be finite.');
        $best=INF;
        foreach($referenceVectors as $ref){
            if(count($ref)!==$n)throw new \InvalidArgumentException('Novelty representation dimensions differ.');
            $ss=0.0;foreach($vector as $i=>$x){$y=$ref[$i]??null;if(!is_numeric($y)||!is_finite((float)$y))throw new \InvalidArgumentException('Reference novelty vector must be finite.');$d=(float)$x-(float)$y;$ss+=$d*$d;}
            $best=min($best,sqrt($ss)/$sigma);
        }
        return min($cap,$best);
    }

    /** Eq. (20): I_gen=(1/m)sum_j N(x_j), I_learn=sum_{j in J} omega_j N(x_j), plus |J|/m. */
    public static function noveltyDiagnostics(array $assessments): array
    {
        if($assessments===[])return['proposal_novelty'=>null,'learning_novelty'=>null,'admission_fraction'=>null];
        $proposal=0.0;$admitted=0;$learning=0.0;$weightSum=0.0;
        foreach($assessments as $a){
            $n=(float)$a['novelty'];if(!is_finite($n))throw new \InvalidArgumentException('Novelty must be finite.');$proposal+=$n;
            if(!empty($a['admitted'])){$admitted++;$w=(float)$a['weight'];$learning+=$w*$n;$weightSum+=$w;}
        }
        if($admitted>0 && abs($weightSum-1.0)>1e-9)throw new \RuntimeException('Learning novelty requires normalized admitted weights.');
        return [
            'proposal_novelty'=>$proposal/count($assessments),
            'learning_novelty'=>$admitted>0?$learning:null,
            'admission_fraction'=>$admitted/count($assessments),
        ];
    }
}
