<?php
namespace SV\SearchImprovements\Search\Specialized;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use SV\SearchImprovements\Search\Specialized\Query as SpecializedQuery;
use XF\Search\IndexRecord;
use XF\Search\Query;
use XFES\Search\Source\Elasticsearch;
use function count;

class Source extends Elasticsearch
{
    use MetadataSearchEnhancements;

    protected function getDocument(IndexRecord $record): array
    {
        $document = $record->metadata;

        if ($record->hidden)
        {
            $document['hidden'] = true;
        }

        return $document;
    }

    public function specializedSearch(SpecializedQuery $query, $maxResults): array
    {
        return $this->executeSearch(
            $query,
            $this->getSpecializedSearchDsl($query, $maxResults),
            $maxResults
        );
    }

    public function getSpecializedSearchDsl(SpecializedQuery $query, $maxResults): array
    {
        $dsl = $this->getSpecializedCommonSearchDsl($query, $maxResults);

        $filters = [];
        $filtersNot = [];

        $queryDsl = $this->getSpecializedSearchQueryDsl($query, $filters, $filtersNot);
        $this->applyDslFilters($query, $filters, $filtersNot);

        $queryDsl = $this->getSearchQueryFunctionScoreDsl($query, $queryDsl);
        $queryDsl = $this->getSearchQueryBoolDsl($queryDsl, $filters, $filtersNot);

        $dsl['query'] = $queryDsl ?: ['match_all' => (object)[]];

        return $dsl;
    }

    protected function getSpecializedCommonSearchDsl(SpecializedQuery $query, $maxResults): array
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

    protected function getSearchSortDsl(Query\Query $query): array
    {
        if ($query->getOrder() === '_score')
        {
            return [];
        }

        return parent::getSearchSortDsl($query);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function getSpecializedSearchQueryDsl(SpecializedQuery $query, array &$filters, array &$filtersNot): array
    {
        // todo: make configurable
        $fieldBoost = '^1.5';
        $ngramBoost = '';
        $exactBoost = '^2';
        $multiMatchType = 'most_fields'; //'best_fields'
        //if ($this->es->majorVersion() > 7)
        //{
        //    $multiMatchType = 'bool_prefix';
        //}

        // generate actual search field list
        $simpleFieldList = $query->textFields();
        $withNgram = $query->isWithNgram();
        $withExact = $query->isWithExact();
        $fields = [];
        foreach ($simpleFieldList as $field)
        {
            $fields[] = $field . $fieldBoost;
            if ($withNgram)
            {
                $fields[] = $field . '.ngram' . $ngramBoost;
            }
            if ($withExact)
            {
                $fields[] = $field . '.exact' . $exactBoost;
            }
        }

        // multi-match creates multiple match statements and bolts them together depending on the 'type' field
        // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#
        $queryDsl = [
            'type'     => $multiMatchType,
            'query'    => $query->text(),
            'fields'   => $fields,
            'operator' => 'or',
            //'operator' => count($fields) === 1 ? 'and' : 'or',
        ];

        return [
            'multi_match' => $queryDsl,
        ];
    }

    public function truncate($type = null)
    {
        if ($type === null)
        {
            throw new \LogicException('Specialized index requires an explicit type to truncate');
        }
        /** @var \SV\SearchImprovements\Service\Specialized\Optimizer $optimizer */
        $optimizer = \XF::app()->service('SV\SearchImprovements:Specialized\Optimizer', $type, $this->es);
        $optimizer->optimize([], true);
    }
}