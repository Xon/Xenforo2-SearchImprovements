<?php

namespace SV\SearchImprovements\XF\Admin\Controller;

use SV\SearchImprovements\Repository\Search as SearchRepo;
use SV\StandardLib\Helper;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;

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
            $configurer = Helper::service(\XFES\Service\Configurer ::class, null);

            if ($configurer->hasActiveConfig() && $configurer->isEnabled())
            {
                /** @var \SV\SearchImprovements\XFES\Elasticsearch\Api $es */
                $es = $configurer->getEsApi();
                try
                {
                    $esVersion = $es->version();

                    if ($esVersion && $es->test($esTestError))
                    {
                        $esClusterStatus = $es->getClusterInfo();

                        if ($es->indexExists())
                        {
                            $service = Helper::service(\XFES\Service\Stats::class, $es);
                            $esStats = $service->getStats();
                        }
                    }
                }
                catch (\XFES\Elasticsearch\Exception $e)
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