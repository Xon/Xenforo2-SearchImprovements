<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use SV\SearchImprovements\XF\Search\Search as ExtendedSearcher;
use XF\Entity\User as UserEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use function assert;

/**
 * Extends \XF\Pub\Controller\Search
 */
class SearchPatchLast extends XFCP_SearchPatchLast
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        // Various endpoints on the Search controller are missing canSearch checks
        // https://xenforo.com/community/threads/in-xf-pub-controller-search-actionresults-is-missing-checks-from-actionsearch.209594/
        $visitor = \XF::visitor();
        if (!$visitor->canSearch($error))
        {
            throw $this->errorException($this->noPermission($error));
        }

        parent::preDispatchController($action, $params);
    }

    protected function svSearchFromQueryData(array $searchData): AbstractReply
    {
        // a search with no criteria or no keywords is likely from a search url with no query arguments
        // this can't be sanely re-run, so return a customized 'no results' page
        if ($searchData['keywords'] === '' && $searchData['c'] === [])
        {
            $reply = $this->message(\XF::phrase('no_results_found'));
            $reply->setPageParam('isExpiredSearch', true);
            return $reply;
        }

        $query = $this->prepareSearchQuery($searchData, $constraints);
        if ($query->getErrors())
        {
            return $this->error($query->getErrors());
        }
        $searcher = $this->app->search();
        assert($searcher instanceof ExtendedSearcher);
        if ($searcher->isQueryEmpty($query, $error))
        {
            return $this->error($error);
        }

        return $this->runSearch($query, $constraints);
    }

    public function actionResults(ParameterBag $params)
    {
        // Re-do searches from the query data, as this gives saner experiences
        // Workaround for XF bug which allows sharing a cached member search with a guest
        // https://xenforo.com/community/threads/in-xf-pub-controller-search-actionresults-is-missing-checks-from-actionsearch.209594/
        $visitor = \XF::visitor();

        // existence + ownership checks
        $visitorId = (int)$visitor->user_id;
        $searchId = (int)$params->get('search_id');
        /** @var \XF\Entity\Search|null $search */
        $search = $this->em()->find('XF:Search', $searchId);
        if ($search === null || $search->user_id !== $visitorId)
        {
            // search has expired, or the cached search is owned by someone else
            $searchData = $this->convertShortSearchInputNames();
            return $this->svSearchFromQueryData($searchData);
        }
        else if ($visitorId === 0)
        {
            // Determine if the search query (broadly) matches the stored query
            $searchData = $this->convertShortSearchInputNames();
            $storedArgs = $this->convertSearchToQueryInput($search);
            // Use non-exact compare as it is recursively insensitive to element order
            if ($searchData != $storedArgs)
            {
                return $this->svSearchFromQueryData($searchData);
            }
        }

        return parent::actionResults($params);
    }
}