<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use XF\Entity\Search as SearchEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Message as MessageReply;
use XF\Mvc\Reply\View as ViewReply;
use function array_filter;
use function assert;
use function strlen;

/**
 * Extends \XF\Pub\Controller\Search
 */
class SearchPatch extends XFCP_SearchPatch
{
    public function actionResults(ParameterBag $params)
    {
        // Workaround for XF bug which allows sharing a cached member search with a guest
        // https://xenforo.com/community/threads/in-xf-pub-controller-search-actionresults-is-missing-checks-from-actionsearch.209594/
        $visitor = \XF::visitor();

        // existence + ownership checks
        $visitorId = (int)$visitor->user_id;
        /** @var \XF\Entity\Search|null $search */
        $search = $this->em()->find('XF:Search', (int)$params->get('search_id'));
        if ($search === null || $search->user_id !== $visitorId) {
            if ($visitorId === 0) {
                // prevent sharing of search links from members to guests
                return $this->message(\XF::phrase('no_results_found'));
            }

            $searchData = $this->convertShortSearchInputNames();
            $query = $this->prepareSearchQuery($searchData, $constraints);
            if ($query->getErrors()) {
                return $this->error($query->getErrors());
            }
            $searcher = $this->app->search();
            if ($searcher->isQueryEmpty($query, $error)) {
                return $this->error($error);
            }

            // always re-run search for logged-in users
            return $this->runSearch($query, $constraints);
        }
        elseif ($visitorId === 0)
        {
            // Determine if the search query (broadly) matches the stored query
            $searchData = $this->convertShortSearchInputNames();
            // most of the time arguments are in the URL, but member searches and a few other redirects do not include it like a normal search
            $emptySearchData = array_filter($searchData, function ($e) {
                // avoid falsy, which may include terms we don't want to skip
                return $e !== null && $e !== 0 && $e !== '' && $e !== [];
            });
            if (count($emptySearchData) !== 0) {
                $storedArgs = $this->convertSearchToQueryInput($search);
                // Use non-exact compare as it is recursively insensitive to element order
                if ($searchData != $storedArgs) {
                    return $this->message(\XF::phrase('no_results_found'));
                }
            }
        }

        return parent::actionResults($params);
    }
}