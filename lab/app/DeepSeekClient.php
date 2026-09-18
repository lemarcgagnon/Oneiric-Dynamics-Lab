<?php
declare(strict_types=1);
namespace ODLab;

final class DeepSeekClient implements Evaluator
{
    private array $provider;
    private string $key;
    private array $budget;
    private array $usage = ['api_calls'=>0,'input_tokens'=>0,'output_tokens'=>0,'latency_seconds'=>0.0];
    private array $events = [];
    private $transport;

    public function __construct(array $provider, string $key, array $budget, ?callable $transport=null)
    {
        $this->provider = $provider;
        $this->key = $key;
        $this->budget = $budget;
        $this->transport = $transport;
        if ($key === '') throw new \RuntimeException('DeepSeek API key missing.');
        if ($transport === null && !function_exists('curl_init')) throw new \RuntimeException('PHP cURL extension required.');
    }

    public function __destruct(){ $this->key=''; }

    public function usage(): array { return $this->usage; }
    public function events(): array { return $this->events; }

    public function evaluate(array $memory, array $records, string $split): array
    {
        if ($records === []) return ['accuracy'=>0.0,'correct'=>0,'n'=>0,'answers'=>[],'usage'=>$this->usage];
        $batchSize = max(1, (int)$this->provider['batch_size']);
        $correct=0; $answers=[];
        for ($offset=0; $offset<count($records); $offset += $batchSize) {
            $chunk = array_slice($records, $offset, $batchSize);
            $result = $this->call($memory, $chunk, $split);
            foreach ($chunk as $question) {
                $pred = (string)($result['answers'][$question['id']] ?? 'UNKNOWN');
                $ok = hash_equals((string)$question['label'], $pred);
                $correct += (int)$ok;
                $answers[] = [$question['id'],$pred,$question['label'],$ok];
            }
        }
        return ['accuracy'=>$correct/count($records),'correct'=>$correct,'n'=>count($records),'answers'=>$answers,'usage'=>$this->usage];
    }

    private function call(array $memory, array $questions, string $split): array
    {
        $examples = array_map(fn(array $x): array => ['factors'=>$x['factors'],'label'=>$x['label']], $memory);
        $qs = array_map(fn(array $x): array => ['id'=>$x['id'],'factors'=>$x['factors']], $questions);
        $system = 'You are a deterministic factor-code inference evaluator. Infer the mapping only from MEMORY examples. Do not use outside semantic knowledge. Return JSON only, exactly {"answers":{"question_id":"predicted_label"}}. Every question id must appear. If not inferable, use "UNKNOWN".';
        $user = "MEMORY:\n".json_encode($examples,JSON_UNESCAPED_SLASHES)."\nQUESTIONS ($split):\n".json_encode($qs,JSON_UNESCAPED_SLASHES)."\nReturn json.";
        $body = [
            'model'=>$this->provider['model'],
            'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]],
            'response_format'=>['type'=>'json_object'],
            'thinking'=>['type'=>'disabled'],
            'max_tokens'=>(int)$this->provider['max_output_tokens_per_call'],
            'temperature'=>0,
        ];

        // Conservative UTF-8 byte upper bound for byte/BPE-style tokenization; never use an optimistic chars/token heuristic in a fail-closed budget.
        $estimatedInput = strlen($system)+strlen($user);
        $maxAttempts = ((int)$this->provider['max_retries']) + 1;
        $this->reserveWorstCase($estimatedInput, $maxAttempts);
        $last = '';

        for ($attempt=1; $attempt <= $maxAttempts; $attempt++) {
            $started = microtime(true);
            $event = [
                'time'=>gmdate('c'), 'split'=>$split, 'attempt'=>$attempt,
                'memory_n'=>count($memory), 'question_n'=>count($questions),
                'model'=>$this->provider['model'], 'success'=>false,
            ];
            [$raw,$http,$error] = $this->httpPost($body);
            $latency = microtime(true)-$started;
            $this->usage['api_calls']++;
            $this->usage['latency_seconds'] += $latency;
            $event['http_status']=$http; $event['latency_seconds']=$latency;

            if ($raw === false || $http < 200 || $http >= 300) {
                $last = "HTTP $http $error";
                $event['error']=$last;
                $this->events[]=$event;
                continue;
            }
            $json = json_decode($raw,true);
            if (!is_array($json)) {
                $last='Invalid API JSON envelope'; $event['error']=$last; $this->events[]=$event; continue;
            }
            $usage=$json['usage']??[];
            $in=(int)($usage['prompt_tokens']??0); $out=(int)($usage['completion_tokens']??0);
            $this->usage['input_tokens'] += $in;
            $this->usage['output_tokens'] += $out;
            $event['input_tokens']=$in; $event['output_tokens']=$out; $event['response_id']=$json['id']??null;
            $finish=(string)($json['choices'][0]['finish_reason']??'');$event['finish_reason']=$finish;
            $content=(string)($json['choices'][0]['message']['content']??'');
            $decoded=json_decode($content,true);
            $expectedIds=array_map(fn(array $q):string=>(string)$q['id'],$questions);sort($expectedIds);
            $validAnswers=is_array($decoded)&&isset($decoded['answers'])&&is_array($decoded['answers']);
            if($validAnswers){
                $actualIds=array_map('strval',array_keys($decoded['answers']));sort($actualIds);
                $validAnswers=$actualIds===$expectedIds;
                if($validAnswers)foreach($decoded['answers'] as $answer)if(!is_string($answer)){$validAnswers=false;break;}
            }
            // A missing/partial answer is an evaluator failure, not a task error. Do not
            // silently convert transport/format failure into worse scientific accuracy.
            if ($finish==='stop' && $validAnswers) {
                $event['success']=true; $this->events[]=$event; return $decoded;
            }
            $last=$finish!==''&&$finish!=='stop'?'Incomplete DeepSeek completion: '.$finish:'Invalid/incomplete JSON output payload';
            $event['error']=$last;$event['content_sha256']=hash('sha256',$content);$this->events[]=$event;
        }
        throw new \RuntimeException('DeepSeek call failed after retries: '.$last);
    }

    private function httpPost(array $body): array
    {
        if (is_callable($this->transport)) {
            $result=($this->transport)(
                rtrim((string)$this->provider['base_url'],'/').'/chat/completions',
                ['Content-Type: application/json','Authorization: Bearer '.$this->key],
                $body,
                (int)$this->provider['timeout_seconds']
            );
            if (!is_array($result) || count($result)<3) throw new \RuntimeException('Invalid test transport response.');
            return [(string)$result[0],(int)$result[1],(string)$result[2]];
        }
        $ch = curl_init(rtrim((string)$this->provider['base_url'],'/').'/chat/completions');
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->key],
            CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT=>(int)$this->provider['timeout_seconds'],
        ]);
        $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        return [$raw===false?'':$raw,$http,$error];
    }

    private function reserveWorstCase(int $estimatedInput, int $maxAttempts): void
    {
        if ($this->usage['api_calls']+$maxAttempts > (int)$this->budget['max_api_calls']) throw new \RuntimeException('Fail-closed API call budget exhausted.');
        if ($this->usage['input_tokens']+$estimatedInput*$maxAttempts > (int)$this->budget['max_input_tokens']) throw new \RuntimeException('Fail-closed input-token budget exhausted.');
        if ($this->usage['output_tokens']+(int)$this->provider['max_output_tokens_per_call']*$maxAttempts > (int)$this->budget['max_output_tokens']) throw new \RuntimeException('Fail-closed output-token budget exhausted.');
    }
}
