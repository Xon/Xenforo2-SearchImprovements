<?php

namespace SV\SearchImprovements\XFES\Service;

use SV\SearchImprovements\Repository\SpecializedSearchIndex as SpecializedSearchIndexRepo;
use SV\SearchImprovements\XFES\Repository\IndexFailed as ExtendedIndexFailedRepo;
use SV\StandardLib\Helper;
use XF\App;
use XFES\Elasticsearch\Api as EsApi;
use XFES\Repository\IndexFailed as IndexFailedRepo;
use function microtime;

/**
 * @Extends \XFES\Service\RetryFailed
 */
class RetryFailed extends XFCP_RetryFailed
{
    public function __construct(App $app, EsApi $es)
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

    protected function svAllSpecializedRetries(?float &$maxRunTime = null)
    {
        $specializedContentTypes = SpecializedSearchIndexRepo::get()->getSearchHandlerDefinitions();

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

    protected function svSpecializedRetry(string $type, ?float $maxRunTime = null)
    {
        /** @var ExtendedIndexFailedRepo $indexFailedRepo */
        $indexFailedRepo = Helper::repository(IndexFailedRepo::class);

        $es = $this->es;
        $this->es = SpecializedSearchIndexRepo::get()->getIndexApi($type);
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
}