<?php

namespace SV\SearchImprovements\XFES\Elasticsearch;

/**
 * Extends \XFES\Elasticsearch\Api
 */
class Api extends XFCP_Api
{
    /** @var array|null s*/
    protected $dslForError;

    /** @noinspection PhpMissingReturnTypeInspection */
    public function getClusterInfo()
    {
        return $this->request('get', "/_cluster/health", null)->getBody();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function search(array $dsl)
    {
        $esLogDSL = \XF::options()->esLogDSL ?? false;
        if ($esLogDSL)
        {
            $this->dslForError = null;
            \XF::logError(\json_encode($dsl));
        }
        else
        {
            $this->dslForError = $dsl;
        }
        try
        {
            return parent::search($dsl);
        }
        finally
        {
            $this->dslForError = null;
        }
    }

    /**
     * @param array $body
     * @return string|null
     * @noinspection PhpMissingReturnTypeInspection
     */
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

        // log DSL on error
        $esLogDSLOnError = \XF::options()->esLogDSLOnError ?? true;
        if ($esLogDSLOnError && $this->dslForError)
        {
            $reason = ($reason ? $reason . "\n " : '') . "DSL:" . \json_encode($this->dslForError);
        }

        return $reason;
    }
}