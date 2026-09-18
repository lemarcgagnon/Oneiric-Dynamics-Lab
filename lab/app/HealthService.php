<?php
declare(strict_types=1);
namespace ODLab;

/** Deployment diagnostics. Deliberately separate from scientific runtime. */
final class HealthService
{
    public function __construct(private \PDO $pdo) {}

    public function check(bool $installFileExists): array
    {
        $checks=[];$add=static function(string $id,bool $ok,string $detail='')use(&$checks):void{$checks[]=['id'=>$id,'ok'=>$ok,'detail'=>$detail];};
        $add('php',version_compare(PHP_VERSION,'8.1.0','>='),PHP_VERSION);
        $add('pdo_mysql',in_array('mysql',\PDO::getAvailableDrivers(),true),implode(',',\PDO::getAvailableDrivers()));
        $add('curl',function_exists('curl_init'));
        $add('json',function_exists('json_encode'));
        try{
            $server=(string)$this->pdo->query('SELECT VERSION()')->fetchColumn();$add('mysql_connection',true,$server);
            $required=['users','app_settings','missions','runs','condition_results','run_events'];
            $q=$this->pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');$tables=$q->fetchAll(\PDO::FETCH_COLUMN);
            foreach($required as $t)$add('table:'.$t,in_array($t,$tables,true));
            $requiredRunColumns=['config_hash','split_hash','split_manifest_json','code_manifest_hash','evidence_root_sha256','analysis_sha256','usage_sha256'];
            $q=$this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='runs'");$runColumns=$q->fetchAll(\PDO::FETCH_COLUMN);
            foreach($requiredRunColumns as $c)$add('run_column:'.$c,in_array($c,$runColumns,true));
            $q=$this->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('deepseek_api_key','deepseek_api_key_enc','api_key','api_key_enc')");
            $add('byok_columns',(int)$q->fetchColumn()===0);
            $before=$this->pdo->query('SELECT updated_at FROM app_settings WHERE id=1')->fetchColumn();
            $this->pdo->beginTransaction();
            try{
                $this->pdo->exec('UPDATE app_settings SET updated_at=NOW() WHERE id=1');
                $during=$this->pdo->query('SELECT updated_at FROM app_settings WHERE id=1')->fetchColumn();
                $this->pdo->rollBack();
                $after=$this->pdo->query('SELECT updated_at FROM app_settings WHERE id=1')->fetchColumn();
                $add('transaction',(string)$before===(string)$after,'write observed='.(($during!==$before)?'yes':'same-second').' rollback='.(($before===$after)?'yes':'no'));
            }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        }catch(\Throwable $e){$add('mysql_diagnostic',false,$e->getMessage());}
        $add('install_removed',!$installFileExists,'Delete install.php after installation.');
        return ['ok'=>count(array_filter($checks,fn($c)=>!$c['ok']))===0,'checks'=>$checks];
    }
}
