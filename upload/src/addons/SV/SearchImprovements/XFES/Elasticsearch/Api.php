<?php

namespace SV\SearchImprovements\XFES\Elasticsearch;

/**
 * Extends \XFES\Elasticsearch\Api
 */
class Api extends XFCP_Api
{
    public function getClusterInfo()
    {
        return $this->request('get', "/_cluster/health", null)->getBody();
    }

    public function search(array $dsl)
    {
        if (\XF::options()->esLogDSL)
        {
            \XF::logError(\json_encode($dsl));
        }
        return parent::search($dsl);
    }

    protected function getErrorMessage(array $body)
    {
        $reason = parent::getErrorMessage($body);
        // bad error...
        if ($reason === 'all shards failed' &&
            isset($body['error']['failed_shards'][0]['reason']['type']) &&
            isset($body['error']['failed_shards'][0]['reason']['caused_by']['type']) &&
            isset($body['error']['failed_shards'][0]['reason']['caused_by']['reason']))
        {
            return strval($body['error']['failed_shards'][0]['reason']['type']) . ": " .
                   strval($body['error']['failed_shards'][0]['reason']['caused_by']['type']) . " " .
                   strval($body['error']['failed_shards'][0]['reason']['caused_by']['reason']);
        }

        return $reason;
    }
}