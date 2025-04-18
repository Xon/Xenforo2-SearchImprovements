<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XFES\Search\Source;

use SV\SearchImprovements\Search\Features\SearchOrder;
use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use SV\SearchImprovements\Search\ExecuteSearchWrapper;
use SV\SearchImprovements\XF\Search\Query\KeywordQuery;
use XF\Search\Query\Query;
use XF\Search\Query\MetadataConstraint;
use function array_fill_keys, array_key_exists, str_replace, count, floatval, is_array, array_merge;
use function class_exists;
use function is_callable;

/**
 * @extends  \XFES\Search\Source\Elasticsearch
 */
class Elasticsearch extends XFCP_Elasticsearch
{
    use MetadataSearchEnhancements;
    use ExecuteSearchWrapper;

    /**
     * @param string   $keywords
     * @param string[] $error
     * @param string[] $warning
     * @return string
     */
    public function parseKeywords($keywords, &$error = null, &$warning = null)
    {
        if (\XF::options()->searchImpov_simpleQuerySyntax ?? false)
        {
            return str_replace('/', '\/', $keywords);
        }

        return parent::parseKeywords($keywords, $error, $warning);
    }

    /**
     * XF2.0/XF2.1 support
     *
     * @param Query $query
     * @param int   $maxResults
     * @return array
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function getDslFromQuery(Query $query, $maxResults)
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        if ($query->getKeywords() === '*' && $query->getParsedKeywords() === '')
        {
            // getDslFromQuery/getQueryStringDsl disables relevancy if `getParsedKeywords` is empty
            // Which then causes the weightByContentType clause to not match
            /** @var \SV\SearchImprovements\XF\Search\Query\Query $query */
            $query->setParsedKeywords('*');
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $dsl = parent::getDslFromQuery($query, $maxResults);

        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if (isset($dsl['sort'][0]) && ($dsl['sort'][0] === '_score') ||
            isset($dsl['query']['function_score']) ||
            isset($dsl['query']['bool']['must']['function_score']))
        {
            $this->weightByContentType($query, $dsl);
        }

        return $dsl;
    }

    /**
     * XF2.2+
     * @param \XF\Search\Query\KeywordQuery $query
     * @param int                           $maxResults
     * @return array
     */
    public function getKeywordSearchDsl(\XF\Search\Query\KeywordQuery $query, $maxResults)
    {
        // searches without an explicit search limit pickup \XF::options()->maximumSearchResults which is stored as a stringy value
        $maxResults = (int)$maxResults;

        if ($query->getKeywords() === '*' && $query->getParsedKeywords() === '')
        {
            // getDslFromQuery/getQueryStringDsl disables relevancy if `getParsedKeywords` is empty
            // Which then causes the weightByContentType clause to not match
            /** @var KeywordQuery $query */
            $query->setParsedKeywords('*');
        }

        $dsl = parent::getKeywordSearchDsl($query, $maxResults);

        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if (isset($dsl['sort'][0]) && ($dsl['sort'][0] === '_score') ||
            isset($dsl['query']['function_score']) ||
            isset($dsl['query']['bool']['must']['function_score']) ||
            isset($dsl['query']['bool']['must'][0]['function_score']))
        {
            $this->weightByContentType($query, $dsl);
        }

        return $dsl;
    }

    /** @var array<string>|null */
    protected $svValidSearchTypes = null;

    /**
     * @return array<string>
     */
    protected function svGetValidSearchTypes(): array
    {
        if ($this->svValidSearchTypes === null)
        {
            $types = [];
            $app = \XF::app();
            $search = $app->search();
            foreach ($app->getContentTypeField('search_handler_class') as $contentType => $handlerClass)
            {
                if ($search->isValidContentType($contentType) && class_exists($handlerClass))
                {
                    $types[] = $contentType;
                }
            }

            $this->svValidSearchTypes = $types;
        }

        return $this->svValidSearchTypes;
    }

    /**
     * @param Query $query
     * @return array<string|int,string|array<string,string>>
     */
    protected function getSearchSortDsl(Query $query)
    {
        $order = $query->getOrder();
        if ($order instanceof SearchOrder)
        {
            return $order->fields;
        }

        return parent::getSearchSortDsl($query);
    }

    protected function applyDslFilters(Query $query, array &$filters, array &$filtersNot)
    {
        parent::applyDslFilters($query, $filters, $filtersNot);

        if ($query->getHandlerType() !== null)
        {
            return;
        }

        $types = $query->getTypes() ?? [];
        if (!is_array($types) || count($types) === 1)
        {
            return;
        }

        // pre content type weighting
        $contentTypeWeighting = $this->getPerContentTypeWeighting();
        if (count($contentTypeWeighting) === 0)
        {
            return;
        }

        if (count($types) === 0)
        {
            $types = $this->svGetValidSearchTypes();
        }
        $validTypes = array_fill_keys($types, true);
        $skipContentTypes = [];
        foreach ($contentTypeWeighting as $contentType => $weight)
        {
            if (array_key_exists($contentType, $validTypes) && !$weight)
            {
                $skipContentTypes[] = $contentType;
            }
        }

        if (count($skipContentTypes) === 0)
        {
            return;
        }

        /** @noinspection PhpDeprecationInspection */
        if ($this->es->isSingleTypeIndex())
        {
            // types are now stored in a field in the index directly
            $this->applyMetadataConstraint(new MetadataConstraint('type', $skipContentTypes, 'none'), $filters, $filtersNot);
        }
        else
        {
            foreach ($skipContentTypes as $type)
            {
                $filtersNot[] = [
                    'type' => ['value' => $type]
                ];
            }
        }
    }

    /**
     * This is an extended function by 3rd party add-ons, do not change the function signature!
     *
     * @param bool   $isSingleTypeIndex
     * @param string $contentType
     * @param float  $weight
     * @return array
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function weightByContentTypePart($isSingleTypeIndex, $contentType, &$weight)
    {
        return $isSingleTypeIndex ? ['term' => ['type' => $contentType]] : ['type' => ['value' => $contentType]];
    }

    /**
     * This is an extended function by 3rd party add-ons, do not change the function signature!
     *
     * @param bool   $isSingleTypeIndex
     * @param string $contentType
     * @param float  $weight
     * @return array
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function expandContentTypeWeighting($isSingleTypeIndex, $contentType, &$weight)
    {
        /** @noinspection PhpIdempotentOperationInspection */
        $weight = floatval($weight) + 0;
        if ($weight == 1 || !$weight)
        {
            return [];
        }
        $term = $this->weightByContentTypePart($isSingleTypeIndex, $contentType, $weight);

        return [
            [
                'filter' => $term,
                'weight' => (float)$weight,
            ]
        ];
    }

    /**
     * This is an extended function by 3rd party add-ons, do not change the function signature!
     *
     * @return array
     */
    protected function getPerContentTypeWeighting()
    {
        return \XF::options()->content_type_weighting ?? [];
    }

    /**
     * @param Query $query
     * @param array $dsl
     * @return void
     */
    public function weightByContentType(Query $query, array &$dsl)
    {
        $types = $query->getTypes() ?? [];
        if (!is_array($types))
        {
            return;
        }

        $forceContentWeighting = is_callable([$query, 'isForceContentWeighting']) && $query->isForceContentWeighting();
        if (!$forceContentWeighting)
        {
            // skip specific type handler searches
            if ($query->getHandlerType() !== null)
            {
                return;
            }

            if (count($types) === 1)
            {
                return;
            }
        }

        // pre content type weighting
        $contentTypeWeighting = $this->getPerContentTypeWeighting();
        if (count($contentTypeWeighting) === 0)
        {
            return;
        }

        if (count($types) === 0)
        {
            $types = $this->svGetValidSearchTypes();
        }
        $validTypes = array_fill_keys($types, true);
        $functions = [];
        /** @noinspection PhpDeprecationInspection */
        $isSingleTypeIndex = $this->es->isSingleTypeIndex();
        foreach ($contentTypeWeighting as $contentType => $weight)
        {
            if (array_key_exists($contentType, $validTypes))
            {
                $functions = array_merge($functions, $this->expandContentTypeWeighting($isSingleTypeIndex, $contentType, $weight));
            }
        }

        if (count($functions) === 0)
        {
            return;
        }

        $dsl['query'] = [
            'function_score' => [
                'query'     => $dsl['query'],
                'functions' => $functions
            ]
        ];
    }
}
