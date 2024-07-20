<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Pub\Controller;

use SV\SearchImprovements\XF\Repository\Search as SearchRepo;
use XF\Entity\Search as SearchEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Message as MessageReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Search\Query\Query;
use function assert;
use function strlen;

/**
 * @Extends \XF\Pub\Controller\Search
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
            $reply->setParam('isUsingElasticSearch', \SV\SearchImprovements\Repository\Search::get()->isUsingElasticSearch());

            // get the container type for this type
            $contentType = (string)$reply->getParam('type');
            if ($contentType !== '')
            {
                $searchRepo = $this->repository('XF:Search');
                assert($searchRepo instanceof SearchRepo);
                $reply->setParam('contentType', $contentType);
                $reply->setParam('containerType', $searchRepo->getContainerTypeForContentType($contentType));
            }
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
     * @return \XF\Search\Query\KeywordQuery|Query
     * @noinspection PhpUndefinedClassInspection
     * @noinspection PhpReturnDocTypeMismatchInspection
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

    /** @var bool */
    protected $svCaptureLinkData = false;
    /** @var SearchEntity|null */
    protected $svCapturedLinkData = null;

    public function assertValidPage($page, $perPage, $total, $linkType, $linkData = null)
    {
        if ($this->svCaptureLinkData && $linkType === 'search' && $linkData instanceof SearchEntity)
        {
            $this->svCapturedLinkData = $linkData;
        }

        parent::assertValidPage($page, $perPage, $total, $linkType, $linkData);
    }

    public function actionResults(ParameterBag $params)
    {
        $this->svCaptureLinkData = true;
        try
        {
            $reply = parent::actionResults($params);
        }
        finally
        {
            // assertValidPage is called after validation, so it is safe to reference it as being owned by the user
            $validSearch = $this->svCapturedLinkData;
            $this->svCapturedLinkData = null;
            $this->svCaptureLinkData = false;
        }


        if ($reply instanceof MessageReply)
        {
            $phrase = $reply->getMessage();
            if ($phrase instanceof \XF\Phrase && $phrase->getName() === 'no_results_found')
            {
                $emptySearch = $validSearch;
                if ($emptySearch === null)
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
                }

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
                    'isExpiredSearch' => $reply->getPageParams()['isExpiredSearch'] ?? false,
                ];

                $reply = $this->view('XF:Search\Results', 'search_results', $viewParams);
            }
        }
        if ((\XF::config('svForceSearchQueryNonEmptyOnDisplay') ?? true)  && $reply instanceof ViewReply && ($search = $reply->getParam('search')))
        {
            assert($search instanceof SearchEntity);
            if ($search->search_query === '')
            {
                $search->setReadOnly(false);
                $search->search_query = '*';
                $search->setReadOnly(true);
            }
        }

        return $reply;
    }
}