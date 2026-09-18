<?php
declare(strict_types=1);
namespace ODLab;

final class Util
{
    public static function stableJson(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
    }
    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if ($value === []) return [];
        $assoc = array_keys($value) !== range(0,count($value)-1);
        if ($assoc) {
            ksort($value);
            foreach ($value as $k=>$v) $value[$k]=self::normalize($v);
            return $value;
        }
        return array_map([self::class,'normalize'],$value);
    }
    public static function hash(mixed $value): string { return hash('sha256', self::stableJson($value)); }
    public static function assertSecretAbsent(string $secret,string $payload,string $context='payload'): void
    {
        if($secret!=='' && str_contains($payload,$secret)) throw new \RuntimeException('Credential leak blocked before persistence: '.$context);
    }
    public static function redactSecret(string $secret,string $text): string
    {
        return $secret===''?$text:str_replace($secret,'[REDACTED]',$text);
    }
    public static function evidenceRoot(array $resultHashes,array $eventHashes): string
    {
        foreach(array_merge($resultHashes,$eventHashes) as $h)if(!is_string($h)||!preg_match('/^[0-9a-f]{64}$/',$h))throw new \InvalidArgumentException('Evidence root requires SHA-256 hex hashes.');
        return self::hash(['condition_results'=>array_values($resultHashes),'run_events'=>array_values($eventHashes)]);
    }
    public static function clamp(float $v,float $lo,float $hi): float { return max($lo,min($hi,$v)); }
    public static function codeManifestHash(): string
    {
        $manifest = ODLAB_ROOT.'/MANIFEST.sha256';
        if (is_file($manifest)) return hash_file('sha256',$manifest);
        $files=[];
        foreach (['app','public','schema.sql'] as $entry) {
            $path=ODLAB_ROOT.'/'.$entry;
            if (is_file($path)) {$files[$entry]=hash_file('sha256',$path);continue;}
            if (!is_dir($path)) continue;
            $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS));
            foreach($it as $file)if($file->isFile()){$rel=substr($file->getPathname(),strlen(ODLAB_ROOT)+1);$files[$rel]=hash_file('sha256',$file->getPathname());}
        }
        ksort($files); return self::hash($files);
    }
}
