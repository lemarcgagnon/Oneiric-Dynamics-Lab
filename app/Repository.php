<?php
declare(strict_types=1);
namespace ODLab;

interface Repository
{
    public function findUserByUsername(string $username): ?array;
    public function createMission(int $userId,string $title,string $objective,string $suite,string $configJson,string $configHash): int;
    public function getMission(int $missionId): ?array;
    public function markMissionRunning(int $missionId): void;
    public function markMissionCompleted(int $missionId): void;
    public function markMissionFailed(int $missionId,string $error): void;
    public function createRun(int $missionId,string $configHash,string $splitHash,string $splitManifestJson,string $providerModel,string $codeManifestHash): int;
    public function saveConditionResult(int $runId,string $condition,int $seed,string $resultJson): void;
    public function saveRunEvent(int $runId,string $eventType,string $eventJson): void;
    /** Persist canonical analysis/usage while the run is still in running/finalizing state. */
    public function saveRunAnalysis(int $runId,string $analysisJson,string $usageJson): void;
    /** Seal all condition/event evidence accumulated before final completion. */
    public function sealRunEvidence(int $runId): string;
    /** Mark a fully analyzed and sealed run completed. Must be the final run-state mutation. */
    public function finalizeRun(int $runId): void;
    public function failRun(int $runId,string $error): void;
    public function listMissions(int $limit=100): array;
    public function getRun(int $runId): ?array;
    public function getRunEvents(int $runId): array;
    public function getConditionResults(int $runId): array;
}
