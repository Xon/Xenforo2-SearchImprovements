<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

/**
 * Extends \XF\Pub\Controller\Search
 */
class Search extends XFCP_Search
{
    protected $shimOrder = null;

    public function actionIndex(ParameterBag $params)
    {
        $reply = parent::actionIndex($params);

        if ($reply instanceof View)
        {
            $input = $reply->getParam('input');
            if (empty($input['order']))
            {
                /** @var \SV\SearchImprovements\XF\Entity\User $visitor */
                $visitor = \XF::visitor();
                if ($visitor->canChangeSearchOptions() && $visitor->Option->sv_default_search_order)
                {
                    $reply->setParam('input', ['order' => $visitor->Option->sv_default_search_order]);
                }
                else if (!empty(\XF::options()->svDefaultSearchOrder))
                {
                    $reply->setParam('input', ['order' => \XF::options()->svDefaultSearchOrder]);
                }
            }
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

        if (!empty(\XF::options()->svAllowEmptySearch))
        {
            if (!strlen($query->getKeywords()) && !$query->getUserIds())
            {
                $query->withKeywords('*', $query->getTitleOnly());
            }
        }

        return $query;
    }

}