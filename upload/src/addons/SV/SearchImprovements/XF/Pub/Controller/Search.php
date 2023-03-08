<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

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
class Search extends XFCP_Search
{
    /** @var string|null */
    protected $shimOrder = null;

    public function preDispatch($action, ParameterBag $params)
    {
        /** @var \SV\SearchImprovements\XF\Search\SearchPatch $search */
        $search = \XF::app()->search();
        $search->specializedIndexProxying = false;
        parent::preDispatch($action, $params);
    }

    /**
     * @param ParameterBag $params
     * @return AbstractReply
     */
    public function actionIndex(ParameterBag $params)
    {
        $reply = parent::actionIndex($params);

        if ($reply instanceof ViewReply)
        {
            $input = $reply->getParam('input');
            if (empty($input['order']))
            {
                /** @var \SV\SearchImprovements\XF\Entity\User $visitor */
                $visitor = \XF::visitor();
                $userSearchOrder = $visitor->getDefaultSearchOrder();
                if ($userSearchOrder !== '')
                {
                    $input = $input ?: [];
                    $input['order'] = $userSearchOrder;
                }
            }
            $reply->setParam('input', $input);
        }

        return $reply;
    }

    /**
     * @return AbstractReply
     */
    public function actionSearch()
    {
        /** @var \SV\SearchImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        $userSearchOrder = $visitor->getDefaultSearchOrder();
        if ($userSearchOrder !== '')
        {
            $this->shimOrder = $userSearchOrder;
        }
        try
        {
            return parent::actionSearch();
        }
        finally
        {
            $this->shimOrder = null;
        }
    }

    /**
     * @param array $data
     * @param array $urlConstraints
     * @return \XF\Search\Query\KeywordQuery
     */
    protected function prepareSearchQuery(array $data, &$urlConstraints = [])
    {
        if ($this->shimOrder !== null && strlen($data['order'] ?? '') === 0)
        {
            $data['order'] = $this->shimOrder;
        }

        $query = parent::prepareSearchQuery($data, $urlConstraints);

        /** @var \SV\SearchImprovements\XF\Search\Search $searcher */
        $searcher = $this->app->search();

        if ($searcher->isSvAllowEmptySearch())
        {
            $searcher->setSvAllowEmptySearch(false);
            // rewrite the keyword to a *, so the user can be linked back to the query
            // this also initializes the parsedKeywords option
            // must re-fetch c.title_only since it gets ignored if there are no keywords...
            if ($searcher->isQueryEmpty($query, $error))
            {
                $searcher->setSvAllowEmptySearch(true);

                $searchRequest = new \XF\Http\Request($this->app->inputFilterer(), $data, [], []);
                $input = $searchRequest->filter([
                    'c.title_only' => 'uint',
                ]);
                $query->withKeywords('*', $input['c.title_only']);
            }
        }

        return $query;
    }

    public function actionResults(ParameterBag $params)
    {
        $reply = parent::actionResults($params);

        if ($reply instanceof MessageReply)
        {
            $phrase = $reply->getMessage();
            if ($phrase instanceof \XF\Phrase && $phrase->getName() === 'no_results_found')
            {
                $emptySearch = $this->em()->create('XF:Search');
                assert($emptySearch instanceof SearchEntity);

                // extract from the URL public known information for the search result page
                $searchId = (int)$params->get('search_id');
                $emptySearch->setTrusted('search_id', $searchId);
                $searchData = $this->convertShortSearchInputNames();
                $query = $this->prepareSearchQuery($searchData, $constraints);
                // Construct a known good empty-search
                $emptySearch->setupFromQuery($query, $constraints);
                $emptySearch->search_results = [];
                $emptySearch->setReadOnly(true);

                $resultOptions = [
                    'search' => $emptySearch,
                    'term'   => $emptySearch->search_query,
                ];

                $searcher = $this->app()->search();
                $resultSet = $searcher->getResultSet($emptySearch->search_results)->limitToViewableResults();
                $resultsWrapped = $searcher->wrapResultsForRender($resultSet, $resultOptions);

                $viewParams = [
                    'search'  => $emptySearch,
                    'results' => $resultsWrapped,

                    'page'    => 1,
                    'perPage' => $this->options()->searchResultsPerPage,

                    'modTypes'      => [],
                    'activeModType' => '',

                    'getOlderResultsDate' => null,
                ];

                $reply = $this->view('XF:Search\Results', 'search_results', $viewParams);
            }
        }

        return $reply;
    }
}