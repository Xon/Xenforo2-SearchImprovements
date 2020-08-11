<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

/**
 * Extends \XF\Pub\Controller\Search
 */
class Search extends XFCP_Search
{
    /** @var string|null */
    protected $shimOrder = null;

    public function actionIndex(ParameterBag $params)
    {
        $reply = parent::actionIndex($params);

        if ($reply instanceof View)
        {
            $input = $reply->getParam('input');
            if (empty($input['order']))
            {
                $input = $input ?: [];
                /** @var \SV\SearchImprovements\XF\Entity\User $visitor */
                $visitor = \XF::visitor();
                if ($visitor->canChangeSearchOptions() && $visitor->Option->sv_default_search_order)
                {
                    $input['order'] = $visitor->Option->sv_default_search_order;
                }
                else if (!empty(\XF::options()->svDefaultSearchOrder))
                {
                    $input['order'] = \XF::options()->svDefaultSearchOrder;
                }
            }
            $reply->setParam('input', $input);
        }

        return $reply;
    }

    public function actionSearch()
    {
        /** @var \SV\SearchImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if ($visitor->canChangeSearchOptions() && $visitor->Option->sv_default_search_order)
        {
            $this->shimOrder = $visitor->Option->sv_default_search_order;
        }
        else if (!empty(\XF::options()->svDefaultSearchOrder))
        {
            $this->shimOrder = \XF::options()->svDefaultSearchOrder;
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

    protected function prepareSearchQuery(array $data, &$urlConstraints = [])
    {
        if ($this->shimOrder && !$data['order'])
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

}