<?php

namespace SV\SearchImprovements\XF\Admin\Controller;

use SV\SearchImprovements\Repository\Search as SearchRepo;
use SV\SearchImprovements\XFES\Elasticsearch\Api as ExtendedApi;
use SV\StandardLib\Helper;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XFES\Elasticsearch\Exception as ElasticSearchException;
use XFES\Service\Configurer as ConfigurerService;
use XFES\Service\Stats as StatsService;

/**
 * @Extends \XF\Admin\Controller\Index
 */
class Index extends XFCP_Index
{
    /**
     * @return AbstractReply
     */
    public function actionIndex()
    {
        $reply = parent::actionIndex();

        if ($reply instanceof ViewReply && SearchRepo::get()->isUsingElasticSearch())
        {
            $esTestError = $esStats = $esVersion = $esClusterStatus = null;
            $configurer = Helper::service(ConfigurerService ::class, null);

            if ($configurer->hasActiveConfig() && $configurer->isEnabled())
            {
                /** @var ExtendedApi $es */
                $es = $configurer->getEsApi();
                try
                {
                    $esVersion = $es->version();

                    if ($esVersion && $es->test($esTestError))
                    {
                        $esClusterStatus = $es->getClusterInfo();

                        if ($es->indexExists())
                        {
                            $service = Helper::service(StatsService::class, $es);
                            $esStats = $service->getStats();
                        }
                    }
                }
                catch (ElasticSearchException $e)
                {
                }
            }

            $reply->setParam('esVersion', $esVersion);
            $reply->setParam('esTestError', $esTestError);
            $reply->setParam('esStats', $esStats);
            $reply->setParam('esClusterStatus', $esClusterStatus);
        }

        return $reply;
    }
}