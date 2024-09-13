<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use SV\SearchImprovements\XF\Search\Search as ExtendedSearcher;
use SV\SearchImprovements\XF\Repository\Search as ExtendedSearchRepo;
use SV\StandardLib\Helper;
use XF\Repository\Search as SearchRepo;
use function assert;
use function in_array;

/**
 * @Extends \XF\Pub\Controller\Search
 */
class SearchPatchFirst extends XFCP_SearchPatchFirst
{
    protected function prepareSearchQuery(array $data, &$urlConstraints = [])
    {
        // XF bug: https://xenforo.com/community/threads/crafted-post-search-query-can-skip-post-gettypepermissionconstraints.213723/
        // rewrite searches which target the parent content into a child content search with a type constraint
        // this covers member searches, but also constructed searches
        /** @var ExtendedSearcher $searcher */
        $searcher = \XF::app()->search();

        $searchType = $data['search_type'];
        if ($searchType !== '' && $searcher->isValidContentType($searchType))
        {
            $handler = $searcher->handler($searchType);
            // XF does falsy check on getGroupByType result :(
            if (!$handler->getGroupByType())
            {
                /** @var ExtendedSearchRepo $searchRepo */
                $searchRepo = Helper::repository(SearchRepo::class);
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