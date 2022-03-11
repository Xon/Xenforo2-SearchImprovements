<?php

namespace SV\SearchImprovements\Repository;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\SearchSourceExtractor;
use SV\SearchImprovements\Search\Specialized\Query as SpecializedQuery;
use SV\SearchImprovements\Search\Specialized\Source as SpecializedSource;
use SV\SearchImprovements\Search\Specialized\SpecializedData;
use SV\SearchImprovements\XFES\Elasticsearch\Api;
use XF\Mvc\Entity\Repository;
use XF\Search\Data\AbstractData;
use XF\Search\Search as XenForoSearch;
use function strlen, strtolower, class_exists, is_array, count, max;

class SpecializedSearchIndex extends Repository
{
    /** @var array<class-string,XenForoSearch>|null */
    protected $search = null;
    /** @var array<class-string,\XF\Search\Data\AbstractData>|null */
    protected $handlers = null;

    public function getIndexApi(string $contentType): Api
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
     * @return array<string,class-string>
     */
    public function getSearchHandlerDefinitions(): array
    {
        return $this->app()->getContentTypeField('specialized_search_handler_class');
    }

    public function getSearchSource(string $contentType): SpecializedSource
    {
        $es = $this->getIndexApi($contentType);

        $class = \XF::extendClass(SpecializedSource::class);
        /** @var SpecializedSource $source */
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
    public function search(string $contentType, bool $throw = true)
    {
        /** @var XenForoSearch|null $search */
        $search = $this->search[$contentType] ?? null;
        if ($search !== null)
        {
            return $search;
        }

        $handlerDefinitions = $this->getSearchHandlerDefinitions();
        $handlerClass = $handlerDefinitions[$contentType] ?? null;
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

        $shimSearchForSpecialization = Globals::$shimSearchForSpecialization ?? false;
        Globals::$shimSearchForSpecialization = false;
        try
        {
            /** @var XenForoSearch $search */
            $search = new $class($this->getSearchSource($contentType), [$contentType => $handlerClass]);
        }
        finally
        {
            Globals::$shimSearchForSpecialization = $shimSearchForSpecialization;
        }

        $this->search[$contentType] = $search;

        return $search;
    }

    /**
     * @param string $contentType
     * @param bool   $throw
     * @return AbstractData|SpecializedData|null
     * @throws \Exception
     */
    public function getHandler(string $contentType, bool $throw = true)
    {
        if (!(\XF::options()->xfesEnabled ?? false))
        {
            if ($throw)
            {
                throw new \LogicException('XFES is not enabled');
            }

            return null;
        }

        /** @var AbstractData|SpecializedData|null $handler */
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
        if (!($handler instanceof SpecializedData))
        {
            throw new \LogicException('Specialized handlers must implement ' . SpecializedData::class);
        }

        return $handler;
    }

    protected function getQuery(\XF\Search\Search $search, SpecializedData $handler): SpecializedQuery
    {
        $extendClass = \XF::extendClass(SpecializedQuery::class);
        return new $extendClass($search, $handler);
    }

    public function getQueryForSpecializedSearch(string $contentType): SpecializedQuery
    {
        $search = $this->search($contentType);
        /** @var SpecializedData|AbstractData $handler */
        $handler = $search->handler($contentType);
        if (!($handler instanceof SpecializedData))
        {
            throw new \LogicException('Specialized handlers must implement ' . SpecializedData::class);
        }

        return $this->getQuery($search, $handler);
    }

    public function executeSearch(SpecializedQuery $query, int $maxResults = 0, bool $applyVisitorPermissions = false): \XF\ResultSet
    {
        $types = $query->getTypes();
        if (!is_array($types) && count($types) !== 1)
        {
            throw new \LogicException('Specialized search indexes only support a single type');
        }
        $contentType = $query->getHandlerType() ?? '';
        if (strlen($contentType) === 0)
        {
            throw new \LogicException('Specialized search indexes must have a content type');
        }

        if ($maxResults <= 0)
        {
            $maxResults = max((int)\XF::options()->maximumSearchResults, 20);
        }

        $search = $this->search($contentType);
        $source = SearchSourceExtractor::getSource($search);
        if (!$source instanceof SpecializedSource)
        {
            throw new \LogicException('Specialized search index source should be an instance of ' . SpecializedSource::class);
        }

        $results = $source->specializedSearch($query, $maxResults);

        return $search->getResultSet($results)->limitResults($maxResults, $applyVisitorPermissions);
    }
}