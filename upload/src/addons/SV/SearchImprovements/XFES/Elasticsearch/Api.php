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
}