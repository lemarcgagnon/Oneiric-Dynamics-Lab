<?php
declare(strict_types=1);
namespace ODLab;

/**
 * Portable deterministic counter-based PRNG for reproducible experiments.
 * Each draw is derived from SHA-256(seed || counter), avoiding platform- and
 * overflow-sensitive integer recurrences. It is not used for cryptographic keys.
 */
final class RNG
{
    private int $counter = 0;
    public function __construct(private int $seed) {}

    public function nextFloat(): float
    {
        $raw = hash('sha256', $this->seed.':'.($this->counter++), true);
        $p = unpack('Nhi/Nlo', substr($raw,0,8));
        if (!is_array($p)) throw new \RuntimeException('RNG unpack failed.');
        // 53 random bits, exactly representable as an integer in IEEE-754 double.
        $hi21 = ((int)$p['hi']) & 0x001fffff;
        $value = $hi21 * 4294967296.0 + (float)((int)$p['lo']);
        return $value / 9007199254740992.0; // 2^53, result in [0,1)
    }

    public function int(int $min,int $max): int
    {
        if($max<$min)throw new \InvalidArgumentException('Invalid RNG range.');
        return $min+(int)floor($this->nextFloat()*($max-$min+1));
    }

    public function choice(array $values): mixed
    {
        if(!$values)throw new \RuntimeException('choice from empty array');
        return $values[$this->int(0,count($values)-1)];
    }

    public function shuffle(array $values): array
    {
        for($i=count($values)-1;$i>0;$i--){$j=$this->int(0,$i);[$values[$i],$values[$j]]=[$values[$j],$values[$i]];}
        return $values;
    }
}
