<?php

namespace SV\SearchImprovements\Repository;

use SV\SearchImprovements\XFES\Elasticsearch\Api;
use XF\Mvc\Entity\Repository;
use XF\Search\Search as XenForoSearch;
use XFES\Search\Source\Elasticsearch as ElasticsearchSource;
use function strlen, strtolower, class_exists;

class SpecializedSearchIndex extends Repository
{
    /** @var string[]|null */
    protected $handlerDefinitions = null;
    /** @var XenForoSearch[]|null */
    protected $search = null;
    /** @var \XF\Search\Data\AbstractData[]|null */
    protected $handlers = null;

    protected function getIndexApi(string $contentType): Api
    {
        if (strlen($contentType) === 0)
        {
            throw new \LogicException('Expected content type');
        }

        $config = \XF::app()->options()->xfesConfig ?? [];

        if (strlen($config['index'] ?? '') === 0)
        {
            $xfConfig = \XF::app()->config();
            if (isset($xfConfig['db']['dbname']))
            {
                $config['index'] = strtolower($xfConfig['db']['dbname']);
            }
        }
        if (strlen($config['index'] ?? '') === 0)
        {
            throw new \LogicException('ElasticSearch index name can not be derived');
        }

        $config['index'] = $config['index'] . '_' . $contentType;

        /** @var Api $api */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $api = \XFES\Listener::getElasticsearchApi($config);

        return $api;
    }


    /**
     * A key-value listings of specialized search handlers
     *
     * @return string[]
     */
    protected function getSearchHandlerDefinitions(): array
    {
        return [
            // 'svExample' => \XF\Search\Data\AbstractData::class,
        ];
    }

    protected function getSearchSource(string $contentType): ElasticsearchSource
    {
        $es = $this->getIndexApi($contentType);

        $class = \XF::extendClass(ElasticsearchSource::class);
        /** @var ElasticsearchSource $source */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $source = new $class($es);

        return $source;
    }

    /**
     * @param string $contentType
     * @param bool   $throw
     * @return XenForoSearch|null
     * @throws \Exception
     */
    public function search(string $contentType, bool $throw)
    {
        /** @var XenForoSearch|null $search */
        $search = $this->search[$contentType] ?? null;
        if ($search !== null)
        {
            return $search;
        }

        if ($this->handlerDefinitions === null)
        {
            $this->handlerDefinitions = $this->getSearchHandlerDefinitions();
        }
        $handlerClass = $this->handlerDefinitions[$contentType] ?? null;
        if ($handlerClass === null)
        {
            if ($throw)
            {
                throw new \InvalidArgumentException("Unknown search handler type '$contentType'");
            }

            return null;
        }
        if (!class_exists($handlerClass))
        {
            if ($throw)
            {
                throw new \LogicException("Expected class  '$handlerClass' to exist for search handler type '$contentType'");
            }

            return null;
        }

        $class = \XF::extendClass(XenForoSearch::class);
        /** @var XenForoSearch $search */
        $search = new $class($this->getSearchSource($contentType), [$contentType => $handlerClass]);
        $this->search[$contentType] = $search;

        return $search;
    }

    /**
     * @param string $contentType
     * @param bool   $throw
     * @return \XF\Search\Data\AbstractData|null
     * @throws \Exception
     */
    public function getHandler(string $contentType, bool $throw = true)
    {
        if (\XF::options()->xfesEnabled ?? false)
        {
            if ($throw)
            {
                throw new \LogicException('XFES is not enabled');
            }

            return null;
        }

        if ($this->handlerDefinitions === null)
        {
            $this->handlerDefinitions = $this->getSearchHandlerDefinitions();
        }

        $handler = $this->handlers[$contentType] ?? null;
        if ($handler !== null)
        {
            return $handler;
        }

        $searcher = $this->search($contentType, $throw);
        if ($searcher === null)
        {
            return null;
        }

        $handler = $searcher->handler($contentType);
        $this->handlers[$contentType] = $handler;

        return $handler;
    }
}