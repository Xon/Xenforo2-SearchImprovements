<?php

namespace SV\SearchImprovements\Job;

use XF\Job\AbstractJob;
use XF\Job\JobResult;

class SearchPruneJob extends AbstractJob
{
    public static function enqueue(int $cutOff, int $batchSize = 10000): void
    {
        \XF::app()->jobManager()->enqueueUnique('svSearchPrune', self::class, [
            'cutOff' => $cutOff,
            'batchSize' => $batchSize,
        ], false);
    }

    protected $defaultData = [
        'cutOff' => null,
        'batchSize' => 10000,
    ];

    /**
     * @param float|int $maxRunTime
     */
    public function run($maxRunTime): JobResult
    {
        $cutOff = (int)($this->data['cutOff'] ?? 0);
        $batchSize = (int)($this->data['batchSize'] ?? 0);
        if ($cutOff <= 0 || $batchSize <= 0)
        {
            return $this->complete();
        }

        $rows = \XF::db()->delete('xf_search', 'search_date < ?', [$cutOff], '', '', $batchSize);
        if ($rows > 0)
        {
            return $this->resume();
        }

        return $this->complete();
    }

    public function getStatusMessage(): string
    {
        return '';
    }

    public function canCancel(): bool
    {
        return false;
    }

    public function canTriggerByChoice(): bool
    {
        return false;
    }
}