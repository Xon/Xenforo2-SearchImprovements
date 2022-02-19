<?php
namespace SV\SearchImprovements\Search\Specialized;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use SV\SearchImprovements\Search\Specialized\Query as SpecializedQuery;
use XFES\Elasticsearch\Exception as EsException;
use XFES\Search\Source\Elasticsearch;
use function array_slice, count;

class Source extends Elasticsearch
{
    use MetadataSearchEnhancements;

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

        return array_slice($hits, 0, $maxResults);
    }

    public function getSpecializedSearchDsl(SpecializedQuery $query, $maxResults): array
    {
        $dsl = $this->getCommonSearchDsl($query, $maxResults);
        $dsl['fields'] = [];

        $filters = [];
        $filtersNot = [];

        $queryDsl = $this->getSpecializedSearchQueryDsl($query, $filters, $filtersNot);
        $this->applyDslFilters($query, $filters, $filtersNot);

        $queryDsl = $this->getSearchQueryFunctionScoreDsl($query, $queryDsl);
        $queryDsl = $this->getSearchQueryBoolDsl($queryDsl, $filters, $filtersNot);

        $dsl['query'] = $queryDsl ?: ['match_all' => (object)[]];

        return $dsl;
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
            'type'             => $multiMatchType,
            'query'            => $query->text(),
            'fields'           => $fields,
            'default_operator' => count($fields) === 1 ? 'and' : 'or',
        ];

        return [
            'multi_match' => $queryDsl,
        ];
    }
}