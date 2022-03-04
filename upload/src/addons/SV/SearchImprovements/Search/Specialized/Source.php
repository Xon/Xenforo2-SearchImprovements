<?php
namespace SV\SearchImprovements\Search\Specialized;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use SV\SearchImprovements\Search\Specialized\Query as SpecializedQuery;
use XF\Search\IndexRecord;
use XF\Search\Query;
use XFES\Search\Source\Elasticsearch;
use function min, strlen, count;

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

    protected function getSearchSortDsl(Query\Query $query): array
    {
        if ($query->getOrder() === '_score')
        {
            return [];
        }

        return parent::getSearchSortDsl($query);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function getSpecializedSearchQueryDsl(SpecializedQuery $query, int $maxResults, array &$filters, array &$filtersNot): array
    {


        // generate actual search field list
        $simpleFieldList = $query->textFields();
        $fieldBoost = $query->fieldBoost();

        $withNgram = $query->isWithNgram();
        $ngramBoost = $query->ngramBoost();

        $withExact = $query->isWithExact();
        $exactBoost = $query->exactBoost();

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

        $multiMatchType = $query->matchQueryType();
        $fuzziness = $query->fuzzyMatching();
        // multi-match creates multiple match statements and bolts them together depending on the 'type' field
        // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#
        $queryDsl = [
            'type'     => $multiMatchType,
            'query'    => $query->text(),
            'fields'   => $fields,
            'operator' => 'or',
            //'operator' => count($fields) === 1 ? 'and' : 'or',
        ];
        if (strlen($fuzziness) !== 0)
        {
            $queryDsl['fuzziness'] = $fuzziness;
            $queryDsl['max_expansions'] = min($maxResults, 50);
        }

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