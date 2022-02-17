<?php

namespace SV\SearchImprovements\XF\Admin\Controller;

use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;

/**
 * Extends \XF\Admin\Controller\Index
 */
class Index extends XFCP_Index
{
    /**
     * @return AbstractReply
     */
    public function actionIndex()
    {
        $reply = parent::actionIndex();

        if ($reply instanceof ViewReply && \XF::isAddOnActive('XFES'))
        {
            $esTestError = $esStats = $esVersion = $esClusterStatus = null;
            /** @var \XFES\Service\Configurer $configurer */
            $configurer = $this->service('XFES:Configurer', null);

            if ($configurer->hasActiveConfig() && $configurer->isEnabled())
            {
                /** @var \SV\SearchImprovements\XFES\Elasticsearch\Api $es */
                $es = $configurer->getEsApi();
                try
                {
                    $esVersion = $es->version();

                    if ($esVersion && $es->test($esTestError))
                    {
                        if ($es->indexExists())
                        {
                            /** @var \XFES\Service\Stats $service */
                            $service = $this->service('XFES:Stats', $es);
                            $esStats = $service->getStats();
                            $esClusterStatus = $es->getClusterInfo();
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