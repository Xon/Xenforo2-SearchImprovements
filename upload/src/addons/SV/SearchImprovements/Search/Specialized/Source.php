<?php
namespace SV\SearchImprovements\Search\Specialized;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use SV\SearchImprovements\Search\Specialized\Query as SpecializedQuery;
use XF\Search\IndexRecord;
use XF\Search\Query;
use XFES\Elasticsearch\Exception as EsException;
use XFES\Search\Source\Elasticsearch;
use function version_compare, array_map, array_slice, count;

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
        $dsl = $this->getSpecializedSearchDsl($query, $maxResults);
        try
        {
            $response = $this->es->search($dsl);
        }
        catch (EsException $e)
        {
            $this->logElasticsearchException($e);
            $response = null;
        }

        $hits = $response['hits']['hits'] ?? null;
        if ($hits === null)
        {
            throw \XF::phrasedException('xfes_search_could_not_be_completed_try_again_later');
        }

        $matches = [];
        foreach ($hits as $hit)
        {
            $matches[$hit['id']] = (array)$hit['fields'];
        }

        return array_slice($matches, 0, $maxResults);
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

        $fields = $query->textFields();
        if ($this->es->majorVersion() >= 5)
        {
            // fields is no longer accessible. stored_fields only works if explicitly stored. _source
            // only works if it hasn't been removed. docvalue_fields works consistently.
            if (
                $this->es->majorVersion() == 6 &&
                version_compare($this->es->version(), '6.4.0', '>=')
            )
            {
                $dsl['docvalue_fields'] = array_map(function (string $field) {
                    return [
                        'field'  => $field,
                        'format' => 'use_field_mapping'
                    ];
                }, $fields);
            }
            else
            {
                $dsl['docvalue_fields'] = $fields;
            }

            $dsl['_source'] = false;
        }
        else
        {
            $dsl['fields'] = $fields;
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
}