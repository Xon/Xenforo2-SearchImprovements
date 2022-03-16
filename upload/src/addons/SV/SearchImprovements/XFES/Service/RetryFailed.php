<?php

namespace SV\SearchImprovements\XFES\Service;

use SV\SearchImprovements\Repository\SpecializedSearchIndex;
use function microtime;

/**
 * Extends \XFES\Service\RetryFailed
 */
class RetryFailed extends XFCP_RetryFailed
{
    public function __construct(\XF\App $app, \XFES\Elasticsearch\Api $es)
    {
        parent::__construct($app, $es);
    }

    /**
     * @param float|null $maxRunTime
     * @return void
     */
    public function retry($maxRunTime = null)
    {
        $this->svAllSpecializedRetries($maxRunTime);
        if ($maxRunTime < 0)
        {
            return;
        }

        parent::retry($maxRunTime);
    }

    protected function svAllSpecializedRetries(float &$maxRunTime = null)
    {
        $specializedContentTypes = $this->getSpecializedSearchIndexRepo()->getSearchHandlerDefinitions();

        foreach($specializedContentTypes as $type => $handler)
        {
            $start = microtime(true);

            $this->svSpecializedRetry($type, $maxRunTime);

            if ($maxRunTime)
            {
                $elapsed = microtime(true) - $start;
                if ($elapsed > $maxRunTime)
                {
                    return;
                }

                $maxRunTime -= $elapsed;
            }
        }
    }

    protected function svSpecializedRetry(string $type, float $maxRunTime = null)
    {
        /** @var \SV\SearchImprovements\XFES\Repository\IndexFailed $indexFailedRepo */
        $indexFailedRepo = $this->repository('XFES:IndexFailed');

        $es = $this->es;
        $this->es = $this->getSpecializedSearchIndexRepo()->getIndexApi($type);
        $indexFailedRepo->svSpecializedContentTypes = $type;
        try
        {
            parent::retry($maxRunTime);
        }
        finally
        {
            $indexFailedRepo->svSpecializedContentTypes = null;
            $this->es = $es;
        }
    }

    protected function getSpecializedSearchIndexRepo(): SpecializedSearchIndex
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('SV\SearchImprovements:SpecializedSearchIndex');
    }
}