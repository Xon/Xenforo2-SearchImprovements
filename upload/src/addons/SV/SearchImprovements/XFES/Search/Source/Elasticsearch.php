<?php

namespace SV\SearchImprovements\XFES\Search\Source;

use SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint;
use XF\Search\Query;

/**
 * Class Elasticsearch
 *
 * @package SV\WordCountSearch\XFES\Search\Source
 */
class Elasticsearch extends XFCP_Elasticsearch
{
    /**
     * @param Query\MetadataConstraint $metadata
     * @param array                    $filters
     * @param array                    $filtersNot
     */
    protected function applyMetadataConstraint(Query\MetadataConstraint $metadata, array &$filters, array &$filtersNot)
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
        if (\XF::options()->searchImpov_simpleQuerySyntax)
        {
            return str_replace('/', '\/', $keywords);
        }

        if (!empty(\XF::options()->svAllowEmptySearch))
        {
            if ($keywords === '')
            {
                $keywords = '*';
            }
        }

        return parent::parseKeywords($keywords, $error, $warning);
    }

    protected function getDslFromQuery(Query\Query $query, $maxResults)
    {
        $dsl = parent::getDslFromQuery($query, $maxResults);

        // skip specific type handler searches
        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if (!$query->getHandlerType() &&
            (
                isset($dsl['sort'][0]['_score']) ||
                isset($dsl['query']['function_score']) ||
                isset($dsl['query']['bool']['must']['function_score'])
            )
        )
        {
            $this->weightByContentType($query, $dsl);
        }

        return $dsl;
    }

    function weightByContentType(Query\Query $query, array &$dsl)
    {
        // pre content type weighting
        $contentTypeWeighting = \XF::options()->content_type_weighting;
        if (!$contentTypeWeighting || !is_array($contentTypeWeighting))
        {
            return;
        }

        $types = $query->getTypes();
        if (\is_array($types) && count($types) === 1 )
        {
            return;
        }

        $skipContentTypes = [];
        $functions = [];
        $isSingleTypeIndex = $this->es->isSingleTypeIndex();
        foreach ($contentTypeWeighting as $contentType => $weight)
        {
            if ($weight == 1)
            {
                continue;
            }
            if (!$weight)
            {
                $skipContentTypes[] = $contentType;
                continue;
            }
            $functions[] = [
                "filter" => $isSingleTypeIndex ? ['term' => ['type' => $contentType]] : ['type' => ['value' => $contentType]],
                "weight" => $weight
            ];
        }

        if ($skipContentTypes)
        {
            if (!isset($dsl['query']['bool']))
            {
                $dsl['query']['bool']['must'] = $dsl['query'];
            }

            if (empty($dsl['query']['bool']['must_not']))
            {
                $dsl['query']['bool']['must_not'] = [];
            }

            $filters = &$dsl['query']['bool']['must'];
            $filtersNot = &$dsl['query']['bool']['must_not'];

            if ($this->es->isSingleTypeIndex())
            {
                // types are now stored in a field in the index directly
                $this->applyMetadataConstraint(new Query\MetadataConstraint('type', $skipContentTypes, 'none'), $filters, $filtersNot);
            }
            else
            {
                // type matching is a special case -- we need a long winded approach for multiple types
                if (count($skipContentTypes) > 1)
                {
                    $subBools = [];
                    foreach ($skipContentTypes AS $type)
                    {
                        $subBools[] = [
                            'type' => [
                                'value' => $type
                            ]
                        ];
                    }
                    $filtersNot[] = [
                        'bool' => ['should' => $subBools]
                    ];
                }
                else
                {
                    $filtersNot[] = [
                        'type' => ['value' => reset($skipContentTypes)]
                    ];
                }
            }
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
