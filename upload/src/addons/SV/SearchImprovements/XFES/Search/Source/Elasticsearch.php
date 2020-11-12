<?php

namespace SV\SearchImprovements\XFES\Search\Source;

use SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\MetadataConstraint;

/**
 * Class Elasticsearch
 *
 * @package SV\WordCountSearch\XFES\Search\Source
 */
class Elasticsearch extends XFCP_Elasticsearch
{
    /**
     * @param MetadataConstraint $metadata
     * @param array                    $filters
     * @param array                    $filtersNot
     */
    protected function applyMetadataConstraint(MetadataConstraint $metadata, array &$filters, array &$filtersNot)
    {
        if ($metadata instanceof RangeMetadataConstraint)
        {
            $values = $metadata->getValues();

            switch ($metadata->getMatchType())
            {
                case RangeMetadataConstraint::MATCH_LESSER:
                    $filters[] = [
                        'range' => [
                            $metadata->getKey() => [
                                "lte" => $values[0],
                            ]
                        ]
                    ];

                    return;
                case RangeMetadataConstraint::MATCH_GREATER:
                    $filters[] = [
                        'range' => [
                            $metadata->getKey() => [
                                "gte" => $values[0],
                            ]
                        ]
                    ];

                    return;
                case RangeMetadataConstraint::MATCH_BETWEEN:
                    $filters[] = [
                        'range' => [
                            $metadata->getKey() => [
                                "lte" => $values[0],
                                "gte" => $values[1],
                            ]
                        ]
                    ];

                    return;
            }
        }
        parent::applyMetadataConstraint($metadata, $filters, $filtersNot);
    }

    public function parseKeywords($keywords, &$error = null, &$warning = null)
    {
        $options = \XF::options();
        if (!empty($options->searchImpov_simpleQuerySyntax))
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
     * @noinspection DuplicatedCode
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

        // skip specific type handler searches
        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if ((!$query->getHandlerType() || $query->isForceContentWeighting()) &&
            (
                (isset($dsl['sort'][0]) && ($dsl['sort'][0] === '_score')) ||
                isset($dsl['query']['function_score']) ||
                isset($dsl['query']['bool']['must']['function_score'])
            )
        )
        {
            $this->weightByContentType($query, $dsl);
        }

        return $dsl;
    }

    /**
     * @param \XF\Search\Query\KeywordQuery $query
     * @param int                           $maxResults
     * @return array
     * @noinspection DuplicatedCode
     */
    public function getKeywordSearchDsl(\XF\Search\Query\KeywordQuery $query, $maxResults)
    {
        if ($query->getKeywords() === '*' && $query->getParsedKeywords() === '')
        {
            // getDslFromQuery/getQueryStringDsl disables relevancy if `getParsedKeywords` is empty
            // Which then causes the weightByContentType clause to not match
            /** @var \SV\SearchImprovements\XF\Search\Query\KeywordQuery $query */
            $query->setParsedKeywords('*');
        }

        $dsl = parent::getKeywordSearchDsl($query, $maxResults);

        // skip specific type handler searches
        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if ((!$query->getHandlerType() || $query->isForceContentWeighting()) &&
            (
                (isset($dsl['sort'][0]) && ($dsl['sort'][0] === '_score')) ||
                isset($dsl['query']['function_score']) ||
                isset($dsl['query']['bool']['must']['function_score'])
            )
        )
        {
            $this->weightByContentType($query, $dsl);
        }

        return $dsl;
    }

    protected function applyDslFilters(Query $query, array &$filters, array &$filtersNot)
    {
        parent::applyDslFilters($query, $filters, $filtersNot);

        if ($query->getHandlerType())
        {
            return;
        }

        // pre content type weighting
        $contentTypeWeighting = \XF::options()->content_type_weighting;
        if (!$contentTypeWeighting || !is_array($contentTypeWeighting))
        {
            return;
        }

        $types = $query->getTypes();
        if (\is_array($types) && count($types) === 1)
        {
            return;
        }

        $skipContentTypes = [];
        foreach ($contentTypeWeighting as $contentType => $weight)
        {
            if (!$weight)
            {
                $skipContentTypes[] = $contentType;
            }
        }

        if ($skipContentTypes)
        {
            if ($this->es->isSingleTypeIndex())
            {
                // types are now stored in a field in the index directly
                $this->applyMetadataConstraint(new MetadataConstraint('type', $skipContentTypes, 'none'), $filters, $filtersNot);
            }
            else
            {
                foreach ($skipContentTypes AS $type)
                {
                    $filtersNot[] = [
                        'type' => ['value' => $type]
                    ];
                }
            }
        }
    }

    /**
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
     * @param bool   $isSingleTypeIndex
     * @param string $contentType
     * @param float  $weight
     * @return array
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function expandContentTypeWeighting($isSingleTypeIndex, $contentType, &$weight)
    {
        if ($weight == 1 || !$weight )
        {
            return [];
        }
        $term = $this->weightByContentTypePart($isSingleTypeIndex, $contentType, $weight);
        return [
            [
                "filter" => $term,
                "weight" => $weight,
            ]
        ];
    }

    protected function getPerContentTypeWeighting()
    {
        return \XF::options()->content_type_weighting;
    }

    public function weightByContentType(Query $query, array &$dsl)
    {
        // pre content type weighting
        $contentTypeWeighting = $this->getPerContentTypeWeighting();
        if (!$contentTypeWeighting || !is_array($contentTypeWeighting))
        {
            return;
        }

        $types = $query->getTypes();
        if (\is_array($types) && count($types) === 1)
        {
            return;
        }

        $functions = [];
        $isSingleTypeIndex = $this->es->isSingleTypeIndex();
        foreach ($contentTypeWeighting as $contentType => $weight)
        {
            $functions = array_merge($functions, $this->expandContentTypeWeighting($isSingleTypeIndex, $contentType, $weight));
        }

        if (!$functions)
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
