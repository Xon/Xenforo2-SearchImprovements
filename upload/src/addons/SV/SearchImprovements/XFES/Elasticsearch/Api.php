<?php

namespace SV\SearchImprovements\XFES\Elasticsearch;

use function json_encode;

/**
 * Extends \XFES\Elasticsearch\Api
 */
class Api extends XFCP_Api
{
    /** @var array|null */
    protected $dslForError;
    /** @var array<array> */
    protected $svQueries = [];
    protected $svLogQueries = false;

    public function setLogQueries(bool $logQueries): void
    {
        $this->svLogQueries = $logQueries;
        if (!$logQueries)
        {
            $this->svQueries = [];
        }
    }

    public function svGetQueries(): array
    {
        return $this->svQueries;
    }

    public function getClusterInfo(): ?array
    {
        return $this->request('get', '/_cluster/health')->getBody();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function search(array $dsl)
    {
        if ($this->svLogQueries)
        {
            $this->svQueries[] = $dsl;
        }

        if (\XF::options()->esLogDSL ?? false)
        {
            $this->dslForError = null;
            \XF::logError(json_encode($dsl));
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

        // log DSL on error
        if ((\XF::options()->esLogDSLOnError ?? true) && $this->dslForError !== null)
        {
            $reason = ($reason ? $reason . "\n " : '') . 'DSL:' . json_encode($this->dslForError);
        }

        return $reason;
    }
}