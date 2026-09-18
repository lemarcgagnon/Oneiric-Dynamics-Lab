<?php
declare(strict_types=1);
namespace ODLabTests;

final class DeterministicEvaluator implements \ODLab\Evaluator
{
    private array $usage=['api_calls'=>0,'input_tokens'=>0,'output_tokens'=>0,'latency_seconds'=>0.0];
    private array $events=[];
    public function __construct(private array $provider=[]){ }
    public function usage():array{return$this->usage;}
    public function events():array{return$this->events;}
    public function evaluate(array $memory,array $records,string $split):array
    {
        $start=microtime(true);$this->usage['api_calls']++;
        $map=[];
        foreach($memory as $m){$tokens=explode('|',(string)$m['label']);if(count($tokens)!==count(\ODLab\ResearchContract::FACTORS))continue;foreach(\ODLab\ResearchContract::FACTORS as $i=>$factor){$value=$m['factors'][$factor];if(!isset($map[$factor][$value]))$map[$factor][$value]=$tokens[$i];elseif($map[$factor][$value]!==$tokens[$i])$map[$factor][$value]=null;}}
        $answers=[];$correct=0;
        foreach($records as $r){$parts=[];$known=true;foreach(\ODLab\ResearchContract::FACTORS as $factor){$v=$r['factors'][$factor];$tok=$map[$factor][$v]??null;if($tok===null){$known=false;break;}$parts[]=$tok;}$pred=$known?implode('|',$parts):'UNKNOWN';$ok=hash_equals((string)$r['label'],$pred);$correct+=(int)$ok;$answers[]=[$r['id'],$pred,$r['label'],$ok];}
        $input=(int)ceil(strlen(json_encode([$memory,$records]))/3);$output=max(1,count($records)*8);$lat=microtime(true)-$start;$this->usage['input_tokens']+=$input;$this->usage['output_tokens']+=$output;$this->usage['latency_seconds']+=$lat;
        $this->events[]=['time'=>gmdate('c'),'split'=>$split,'attempt'=>1,'memory_n'=>count($memory),'question_n'=>count($records),'model'=>'deterministic-test-double','success'=>true,'http_status'=>200,'latency_seconds'=>$lat,'input_tokens'=>$input,'output_tokens'=>$output,'response_id'=>'mock-'.count($this->events)+1];
        return['accuracy'=>count($records)?$correct/count($records):0.0,'correct'=>$correct,'n'=>count($records),'answers'=>$answers,'usage'=>$this->usage];
    }
}
