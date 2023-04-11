<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use SV\SearchImprovements\XF\Search\Search as ExtendedSearcher;
use XF\Entity\User as UserEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use SV\SearchImprovements\XF\Repository\Search as SearchRepo;
use function assert;
use function count;
use function in_array;

/**
 * Extends \XF\Pub\Controller\Search
 */
class SearchPatchFirst extends XFCP_SearchPatchFirst
{
    protected function prepareSearchQuery(array $data, &$urlConstraints = [])
    {
        // rewrite searches which target the parent content into a child content search with a type constraint
        // this covers member searches, but also constructed searches
        $searcher = $this->app()->search();
        assert($searcher instanceof ExtendedSearcher);

        $searchType = $data['search_type'];
        if ($searchType !== '' && $searcher->isValidContentType($searchType))
        {
            $handler = $searcher->handler($searchType);
            assert($handler !== null);
            // XF does falsy check on getGroupByType result :(
            if (!$handler->getGroupByType())
            {
                $searchRepo = $this->repository('XF:Search');
                assert($searchRepo instanceof SearchRepo);
                $firstChildType = $searchRepo->getChildContentTypeForContainerType($searchType);
                if ($firstChildType !== null && $firstChildType !== $searchType)
                {
                    $data['c']['content'] = $urlConstraints['content'] = $searchType;
                    unset($data['c']['type']);
                    $data['search_type'] = $firstChildType;
                }
            }

            // XF bug: https://xenforo.com/community/threads/search-c-type-c-content-allows-skipping-a-search-handlers-gettypepermissionconstraints.213722/
            // only allow sub-types if they are part of the selected handler
            $allowedTypeFilters = $handler->getSearchableContentTypes();
            if (isset($data['c']['content']) && !in_array($data['c']['content'], $allowedTypeFilters, true))
            {
                unset($data['c']['content']);
            }
            if (isset($data['c']['type']) && !in_array($data['c']['type'], $allowedTypeFilters, true))
            {
                unset($data['c']['type']);
            }
        }
        else
        {
            unset($data['c']['content']);
            unset($data['c']['type']);
        }

        return parent::prepareSearchQuery($data, $urlConstraints);
    }
}