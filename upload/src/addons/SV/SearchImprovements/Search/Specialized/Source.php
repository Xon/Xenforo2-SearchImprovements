<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Search\Specialized;

use Closure;
use SV\SearchImprovements\Search\Features\SearchOrder;
use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use SV\SearchImprovements\Search\ExecuteSearchWrapper;
use SV\SearchImprovements\Search\Specialized\Query as SpecializedQuery;
use SV\SearchImprovements\Service\Specialized\Optimizer as SpecializedOptimizer;
use XF\Search\IndexRecord;
use XF\Search\Query\Query;
use XFES\Elasticsearch\Api;
use XFES\Search\Source\Elasticsearch;
use XFES\Search\Query\FunctionOrder;
use function min, strlen, count;

class Source extends Elasticsearch
{
    use MetadataSearchEnhancements;
    use ExecuteSearchWrapper;

    protected function getDocument(IndexRecord $record): array
    {
        $document = $record->metadata;

        if ($record->hidden)
        {
            $document['hidden'] = true;
        }

        return $document;
    }

    public function specializedSearch(SpecializedQuery $query, int $maxResults): array
    {
        return $this->executeSearch(
            $query,
            $this->getSpecializedSearchDsl($query, $maxResults),
            $maxResults
        );
    }

    public function getSpecializedSearchDsl(SpecializedQuery $query, int $maxResults): array
    {
        $dsl = $this->getSpecializedCommonSearchDsl($query, $maxResults);

        $filters = [];
        $filtersNot = [];

        $queryDsl = $this->getSpecializedSearchQueryDsl($query, $maxResults, $filters, $filtersNot);
        $this->applyDslFilters($query, $filters, $filtersNot);

        $queryDsl = $this->getSearchQueryFunctionScoreDsl($query, $queryDsl);
        $queryDsl = $this->getSearchQueryBoolDsl($queryDsl, $filters, $filtersNot);

        $dsl['query'] = $queryDsl ?: ['match_all' => (object)[]];

        return $dsl;
    }

    /**
     * @param Query $query
     * @param array $queryDsl
     * @return array
     */
    protected function getSearchQueryFunctionScoreDsl(Query $query,array $queryDsl): array
    {
        $order = $query->getOrder();

        if ($order instanceof SearchOrder)
        {
            $functions = [];
            foreach ($order->getFunctions() as $function)
            {
                /** @var array|Closure(Query,Api):array $function */
                if (is_array($function))
                {
                    $functions[] = $function;
                }
                else if ($function instanceof \Closure)
                {
                    $functions[] = $function($query, $this->es);
                }
            }

            if (count($functions) === 0)
            {
                return $queryDsl;
            }

            return [
                'function_score' => [
                    'query' => $queryDsl,
                    'functions' => $functions
                ]
            ];
        }

        if (\XF::$versionId < 2020000)
        {
            return $queryDsl;
        }

        return parent::getSearchQueryFunctionScoreDsl($query, $queryDsl);
    }

    /**
     * XF2.1 support - TODO remove inlined function
     *
     * @param array $queryDsl
     * @param array $filters
     * @param array $filtersNot
     * @return array
     */
    protected function getSearchQueryBoolDsl(
        array $queryDsl,
        array $filters,
        array $filtersNot
    ): array
    {
        if (\XF::$versionId >= 2020000)
        {
            return parent::getSearchQueryBoolDsl($queryDsl, $filters, $filtersNot);
        }

        if (!$filters && !$filtersNot)
        {
            return $queryDsl;
        }

        $bool = [];

        if ($filters)
        {
            $bool['filter'] = $filters;
        }

        if ($filtersNot)
        {
            $bool['must_not'] = $filtersNot;
        }

        if ($queryDsl)
        {
            $bool['must'] = [$queryDsl];
        }

        return ['bool' => $bool];
    }

    protected function getSpecializedCommonSearchDsl(SpecializedQuery $query, int $maxResults): array
    {
        $dsl = [];

        if ($this->es->majorVersion() >= 5)
        {
            $dsl['docvalue_fields'] = [];
            $dsl['_source'] = false;
        }
        else
        {
            $dsl['fields'] = [];
        }

        $dsl['size'] = $this->getSearchSizeDsl($query, $maxResults);
        $dsl['sort'] = $this->getSearchSortDsl($query);
        if (count($dsl['sort']) === 0)
        {
            unset($dsl['sort']);
        }

        return $dsl;
    }

    protected function getSearchSizeDsl(Query $query, $maxResults): int
    {
        $fetchResults = $maxResults;

        if ($query->getGroupByType())
        {
            $fetchResults *= 4;
        }

        return min(10000, $fetchResults);
    }

    protected function getSearchSortDsl(Query $query): array
    {
        $order = $query->getOrder();
        if ($order === '_score')
        {
            return [];
        }
        else if ($order instanceof SearchOrder)
        {
            return $order->fields;
        }
        else if (
            \XF::$versionId < 2020000
            ? $order === 'relevance' && $query->getParsedKeywords()
            :  $order === 'relevance' || $order instanceof FunctionOrder
        )
        {

            return [
                '_score',
                ['date' => 'desc']
            ];
        }

        return [
            ['date' => 'desc']
        ];

        //return parent::getSearchSortDsl($query);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function getSpecializedSearchQueryDsl(SpecializedQuery $query, int $maxResults, array &$filters, array &$filtersNot): array
    {
        $withPrefixPreferred = $query->isWithPrefixPreferred();
        $prefixMatchBoost = $query->prefixMatchBoost();
        $withNgram = $query->isWithNgram();
        $ngramBoost = $query->ngramBoost();
        $withExact = $query->isWithExact();
        $exactBoost = $query->exactBoost();
        $multiMatchType = $query->matchQueryType();
        $fuzziness = $query->fuzzyMatching();

        $dsl = [];
        foreach($query->textMatches() as $textMatch)
        {
            [$text, $simpleFieldList, $fieldBoost] = $textMatch;
            // generate actual search field list
            $fields = [];
            $prefixFields = [];
            foreach ($simpleFieldList as $field)
            {
                $esField = $field . $fieldBoost;
                $fields[] = $esField;
                $prefixFields[] = $esField;
                if ($withNgram)
                {
                    $esField = $field . '.ngram' . $ngramBoost;
                    $fields[] = $esField;
                }
                if ($withExact)
                {
                    $esField = $field . '.exact' . $exactBoost;
                    $fields[] = $esField;
                    $prefixFields[] = $esField;
                }
            }

            // multi-match creates multiple match statements and bolts them together depending on the 'type' field
            // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#
            $queryDsl = [
                'type'     => $multiMatchType,
                'query'    => $text,
                'fields'   => $fields,
                'operator' => 'or',
                //'operator' => count($fields) === 1 ? 'and' : 'or',
            ];
            if (strlen($fuzziness) !== 0)
            {
                $queryDsl['fuzziness'] = $fuzziness;
                $queryDsl['max_expansions'] = min($maxResults, 50);
            }

            if ($withPrefixPreferred)
            {
                $prefixMatch = [
                    'type'     => 'phrase_prefix',
                    'query'    => $text,
                    'fields'   => $prefixFields,
                    'operator' => 'or',
                    //'operator' => count($fields) === 1 ? 'and' : 'or',
                ];
                if ($prefixMatchBoost !== null)
                {
                    $prefixMatch['boost'] = $prefixMatchBoost;
                }

                $dsl[] = [
                    'bool' => [
                        'should'               => [
                            ['multi_match' => $queryDsl],
                            ['multi_match' => $prefixMatch],
                        ],
                        'minimum_should_match' => 1,
                    ]
                ];
            }
            else
            {
                $dsl[] = ['multi_match' => $queryDsl];
            }
        }

        if (count($dsl) === 0)
        {
            return ['match_all' => (object)[]];
        }
        if (count($dsl) === 1)
        {
            return reset($dsl);
        }

        return [
            'bool' => [
                'should' => $dsl,
                'minimum_should_match' => 1,
            ]
        ];
    }

    public function truncate($type = null)
    {
        if ($type === null)
        {
            throw new \LogicException('Specialized index requires an explicit type to truncate');
        }
        $optimizer = SpecializedOptimizer::get($type, $this->es);
        $optimizer->optimize([], true);
    }
}