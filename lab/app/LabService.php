<?php
declare(strict_types=1);
namespace ODLab;

final class LabService
{
    public function __construct(
        private ?Repository $repository = null,
        private $evaluatorFactory = null
    ) {
        $this->repository ??= \repo();
    }

    public function previewConfig(array $input): array
    {
        $cfg=ResearchContract::validate($input);
        return [
            'config'=>$cfg,
            'mix'=>$cfg['derived_mix'],
            'estimated_api_calls'=>ResearchContract::estimateApiCalls($cfg),
            'condition_count'=>count(ResearchContract::conditions($cfg['suite'],$cfg)),
            'math_coverage'=>ResearchContract::mathCoverage(),
        ];
    }

    public function createMission(int $userId,string $title,string $objective,array $cfg): int
    {
        $title=trim($title);$objective=trim($objective);
        if($title==='')throw new \InvalidArgumentException('Mission title is required.');
        if(strlen($title)>180)throw new \InvalidArgumentException('Mission title is too long.');
        if($objective==='')throw new \InvalidArgumentException('Mission objective is required.');
        $frozen=ResearchContract::validate($cfg);
        $estimate=ResearchContract::estimateApiCalls($frozen);
        if((int)$frozen['budget']['max_api_calls']<$estimate['baseline'])throw new \InvalidArgumentException('API-call budget is below the baseline estimate for this mission. Increase the budget or reduce the suite/seeds.');
        $json=Util::stableJson($frozen);$hash=hash('sha256',$json);
        return $this->repository->createMission($userId,$title,$objective,$frozen['suite'],$json,$hash);
    }

    public function runMission(int $missionId, string $sessionApiKey = ''): array
    {
        $mission=$this->repository->getMission($missionId);
        if(!$mission)throw new \RuntimeException('Mission not found.');
        $cfg=json_decode($mission['config_json'],true,512,JSON_THROW_ON_ERROR);
        $cfg=ResearchContract::validate($cfg);
        $this->repository->markMissionRunning($missionId);
        $runId=null;
        try{
            $benchmark=new Benchmark($cfg['benchmark']);
            $evaluator=$this->makeEvaluator($cfg,$sessionApiKey);
            $engine=new LabEngine($benchmark,$cfg,$evaluator);
            $runId=$this->repository->createRun(
                $missionId,(string)$mission['config_hash'],$benchmark->splitHash,
                Util::stableJson($benchmark->splitManifest()),(string)$cfg['provider']['model'],Util::codeManifestHash()
            );
            $this->repository->saveRunEvent($runId,'run_started',Util::stableJson([
                'time'=>gmdate('c'),'config_hash'=>$mission['config_hash'],'split_hash'=>$benchmark->splitHash,
                'software_version'=>ODLAB_VERSION,'provider_model'=>$cfg['provider']['model'],
                'analysis_plan'=>$cfg['analysis'],
                'credential_policy'=>'BYOK request-memory only; API key is never persisted in DB/files/logs/config/results',
            ]));
            $results=[];$eventCursor=0;
            foreach($cfg['seeds'] as $seed){
                foreach(ResearchContract::conditions($cfg['suite'],$cfg) as $condition){
                    $this->repository->saveRunEvent($runId,'condition_started',Util::stableJson(['condition'=>$condition['name'],'seed'=>(int)$seed,'time'=>gmdate('c')]));
                    $result=$engine->runCondition($condition,(int)$seed);
                    $results[]=$result;
                    $resultJson=Util::stableJson($result);Util::assertSecretAbsent($sessionApiKey,$resultJson,'condition result');
                    $this->repository->saveConditionResult($runId,$result['condition'],(int)$seed,$resultJson);
                    $events=$evaluator->events();
                    for($i=$eventCursor;$i<count($events);$i++){ $eventJson=Util::stableJson($events[$i]);Util::assertSecretAbsent($sessionApiKey,$eventJson,'evaluator event');$this->repository->saveRunEvent($runId,'evaluator_call',$eventJson); }
                    $eventCursor=count($events);
                    $this->repository->saveRunEvent($runId,'condition_completed',Util::stableJson([
                        'condition'=>$result['condition'],'seed'=>$seed,'gate_status'=>$result['gate_status'],'rollback'=>$result['rollback'],
                        'state_hash_before'=>$result['state_hash_before'],'state_hash_proposed'=>$result['state_hash_proposed'],'state_hash_committed'=>$result['state_hash_committed'],
                        'result_sha256'=>hash('sha256',Util::stableJson($result)),
                    ]));
                }
            }
            $analysis=Analysis::build($results,$cfg);$analysisJson=Util::stableJson($analysis);$usageJson=Util::stableJson($evaluator->usage());
            Util::assertSecretAbsent($sessionApiKey,$analysisJson,'analysis');Util::assertSecretAbsent($sessionApiKey,$usageJson,'usage');
            $this->repository->saveRunEvent($runId,'analysis_completed',Util::stableJson(['time'=>gmdate('c'),'analysis_sha256'=>hash('sha256',$analysisJson),'analysis_status'=>$analysis['research_status']??null]));
            // Do not mark the run completed until analysis, the terminal event, and the
            // evidence root have all been persisted. A crash during finalization must
            // never leave an unsealed run falsely labelled completed.
            $this->repository->saveRunAnalysis($runId,$analysisJson,$usageJson);
            $this->repository->saveRunEvent($runId,'run_completed',Util::stableJson(['time'=>gmdate('c'),'usage'=>$evaluator->usage(),'state'=>'finalization_ready']));
            $this->repository->sealRunEvidence($runId);
            $this->repository->finalizeRun($runId);
            $this->repository->markMissionCompleted($missionId);
            return ['run_id'=>$runId,'analysis'=>$analysis];
        }catch(\Throwable $e){
            $safeError=Util::redactSecret($sessionApiKey,$e->getMessage());
            if($runId!==null){try{$this->repository->saveRunEvent($runId,'run_failed',Util::stableJson(['time'=>gmdate('c'),'error'=>$safeError]));}catch(\Throwable){}try{$this->repository->failRun($runId,$safeError);}catch(\Throwable){}}
            try{$this->repository->markMissionFailed($missionId,$safeError);}catch(\Throwable){}
            if($safeError!==$e->getMessage())throw new \RuntimeException($safeError,0,$e);
            throw $e;
        }
    }

    private function makeEvaluator(array $cfg, string $sessionApiKey): Evaluator
    {
        if(is_callable($this->evaluatorFactory)){
            $e=($this->evaluatorFactory)($cfg);
            if(!$e instanceof Evaluator)throw new \RuntimeException('Evaluator factory returned an invalid evaluator.');
            return $e;
        }
        if(function_exists('odlab_evaluator_override')){
            $e=\odlab_evaluator_override($cfg);
            if($e instanceof Evaluator)return $e;
        }
        $key=trim($sessionApiKey);
        if($key==='')throw new \RuntimeException('DeepSeek API key is required for this run. It is BYOK and is not stored by OD Lab.');
        return new DeepSeekClient($cfg['provider'],$key,$cfg['budget']);
    }

    public function listMissions():array{return$this->repository->listMissions();}

    public function getRun(int $id):?array
    {
        $r=$this->repository->getRun($id);if(!$r)return null;
        $integrity=['ok'=>true,'checks'=>[]];
        $check=function(string $name,bool $ok)use(&$integrity):void{$integrity['checks'][$name]=$ok;if(!$ok)$integrity['ok']=false;};

        $configJson=(string)($r['config_json']??'');
        $splitJson=(string)($r['split_manifest_json']??'');
        $analysisJson=(string)($r['analysis_json']??'{}');
        $usageJson=(string)($r['usage_json']??'{}');
        $check('config_hash',strlen((string)($r['config_hash']??''))===64&&hash_equals((string)$r['config_hash'],hash('sha256',$configJson)));
        $splitDecoded=json_decode($splitJson,true,512,JSON_THROW_ON_ERROR);
        $check('split_hash',strlen((string)($r['split_hash']??''))===64&&hash_equals((string)$r['split_hash'],Util::hash($splitDecoded)));
        if(($r['status']??'')==='completed'){
            $check('analysis_sha256',strlen((string)($r['analysis_sha256']??''))===64&&hash_equals((string)$r['analysis_sha256'],hash('sha256',$analysisJson)));
            $check('usage_sha256',strlen((string)($r['usage_sha256']??''))===64&&hash_equals((string)$r['usage_sha256'],hash('sha256',$usageJson)));
        }

        $r['analysis']=json_decode($analysisJson,true,512,JSON_THROW_ON_ERROR)?:[];
        $r['config']=json_decode($configJson,true,512,JSON_THROW_ON_ERROR)?:[];
        $r['usage']=json_decode($usageJson,true,512,JSON_THROW_ON_ERROR)?:[];
        $r['split_manifest']=$splitDecoded?:[];
        $events=[];foreach($this->repository->getRunEvents($id) as $event){
            $expected=hash('sha256',(string)$event['event_type'].'\n'.(string)$event['event_json']);
            $ok=strlen((string)($event['event_sha256']??''))===64&&hash_equals((string)$event['event_sha256'],$expected);
            $check('event_'.$event['id'],$ok);if(!$ok)throw new \RuntimeException('Research evidence integrity failure: event hash mismatch.');
            $event['payload']=json_decode((string)$event['event_json'],true,512,JSON_THROW_ON_ERROR);$events[]=$event;
        }$r['events']=$events;
        $conditions=[];foreach($this->repository->getConditionResults($id) as $row){
            $expected=hash('sha256',(string)$row['result_json']);
            $ok=strlen((string)($row['result_sha256']??''))===64&&hash_equals((string)$row['result_sha256'],$expected);
            $check('condition_'.$row['condition_name'].'_'.$row['seed'],$ok);if(!$ok)throw new \RuntimeException('Research evidence integrity failure: condition-result hash mismatch.');
            $row['result']=json_decode((string)$row['result_json'],true,512,JSON_THROW_ON_ERROR);$conditions[]=$row;
        }$r['condition_evidence']=$conditions;
        if(($r['status']??'')==='completed'){
            $root=Util::evidenceRoot(array_map(fn($x)=>(string)$x['result_sha256'],$conditions),array_map(fn($x)=>(string)$x['event_sha256'],$events));
            $check('evidence_root_sha256',strlen((string)($r['evidence_root_sha256']??''))===64&&hash_equals((string)$r['evidence_root_sha256'],$root));
        }
        if(!$integrity['ok'])throw new \RuntimeException('Research evidence integrity failure: run-level hash mismatch.');
        $r['integrity']=$integrity;
        return$r;
    }

}
