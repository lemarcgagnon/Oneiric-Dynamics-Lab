<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
use ODLab\{PdoRepository,Repository};

final class SpyStatement extends PDOStatement {
    public array $executions=[];
    public function __construct(public string $sql) {}
    public function execute(?array $params=null): bool { $this->executions[]=$params??[]; return true; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {
        if(str_contains($this->sql,'FROM users')) return ['id'=>1,'username'=>'admin','password_hash'=>'x'];
        if(str_contains($this->sql,'FROM missions WHERE')) return ['id'=>1,'config_json'=>'{}','config_hash'=>str_repeat('a',64)];
        if(str_contains($this->sql,'FROM runs r JOIN missions')) return ['id'=>1,'mission_id'=>1,'analysis_json'=>'{}','usage_json'=>'{}','split_manifest_json'=>'{}','config_json'=>'{}'];
        return false;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return []; }
    public function fetchColumn(int $column=0): mixed { return 1; }
}
final class SpyPDO extends PDO {
    /** @var list<string> */ public array $sql=[];
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->sql[]=$query; return new SpyStatement($query); }
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false { $this->sql[]=$query; return new SpyStatement($query); }
    public function lastInsertId(?string $name=null): string|false { return '42'; }
}

$pass=0;$fail=[];function pcheck(bool $ok,string $name):void{global$pass,$fail;if($ok){echo "PASS $name\n";$pass++;}else{$fail[]=$name;echo "FAIL $name\n";}}
try{
    $pdo=new SpyPDO();$r=new PdoRepository($pdo);
    pcheck($r instanceof Repository,'PdoRepository implements canonical Repository boundary');
    $r->findUserByUsername('admin');
    pcheck($r->createMission(1,'t','o','smoke','{}',str_repeat('a',64))===42,'mission insert reaches PDO boundary');
    $r->getMission(1);$r->markMissionRunning(1);$r->markMissionCompleted(1);$r->markMissionFailed(1,'x');
    pcheck($r->createRun(1,str_repeat('a',64),str_repeat('b',64),'{}','deepseek-flash',str_repeat('c',64))===42,'run insert reaches PDO boundary');
    $r->saveConditionResult(1,'c',101,'{}');$r->saveRunEvent(1,'event','{}');$r->saveRunAnalysis(1,'{}','{}');$r->sealRunEvidence(1);$r->finalizeRun(1);$r->failRun(1,'x');$r->listMissions();$r->getRun(1);$r->getRunEvents(1);$r->getConditionResults(1);
    $joined=implode("\n",$pdo->sql);
    foreach(['users','missions','runs','condition_results','run_events'] as $table)pcheck((bool)preg_match('/\b'.preg_quote($table,'/').'\b/',$joined),"PdoRepository touches schema table $table");
    pcheck(!str_contains($joined,'app_settings'),'runtime repository does not use app_settings for credentials or scientific state');
    $schema=(string)file_get_contents(__DIR__.'/../schema.sql');
    foreach(['users','app_settings','missions','runs','condition_results','run_events'] as $table)pcheck((bool)preg_match('/CREATE TABLE IF NOT EXISTS\\s+'.preg_quote($table,'/').'\\b/i',$schema),"schema defines $table");
    foreach(['config_hash','split_hash','split_manifest_json','software_version','provider_model','code_manifest_hash','evidence_root_sha256','analysis_json','analysis_sha256','usage_json','usage_sha256','result_sha256','result_json','event_sha256','event_json'] as $column)pcheck((bool)preg_match('/\\b'.preg_quote($column,'/').'\\b/',$schema),"research persistence column present: $column");
    pcheck(count($pdo->sql)>=15,'repository methods exercised through PDO spy');
    pcheck(!str_contains($schema,'deepseek_api_key') && !str_contains($joined,'deepseek_api_key'),'BYOK API key has no persistence column or repository SQL path');
}catch(Throwable $e){$fail[]='exception: '.$e->getMessage();echo 'EXCEPTION '.$e->getMessage()."\n";}
echo "\nPERSISTENCE PASS $pass\n";if($fail){echo "PERSISTENCE FAIL ".count($fail)."\n- ".implode("\n- ",$fail)."\n";exit(1);}echo "PERSISTENCE FAIL 0\n";
