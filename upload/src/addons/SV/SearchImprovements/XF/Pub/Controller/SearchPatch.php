<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use XF\Entity\Search as SearchEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Message as MessageReply;
use XF\Mvc\Reply\View as ViewReply;
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
                return $this->notFound();
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
            $storedArgs = $this->convertSearchToQueryInput($search);
            // Use non-exact compare as it is recursively insensitive to element order
            if ($searchData != $storedArgs) {
                return $this->notFound();
            }
        }

        return parent::actionResults($params);
    }
}