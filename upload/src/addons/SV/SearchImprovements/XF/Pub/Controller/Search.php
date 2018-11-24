<?php

namespace SV\SearchImprovements\XF\Pub\Controller;



/**
 * Extends \XF\Pub\Controller\Search
 */
class Search extends XFCP_Search
{
    protected $shimOrder = null;

    public function actionSearch()
    {
        /** @var \SV\SearchImprovements\XF\Entity\UserOption $options */
        $options = \XF::visitor()->Option;
        $this->shimOrder = empty($options->sv_default_search_order) ? null : $options->sv_default_search_order;
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

        return parent::prepareSearchQuery($data, $urlConstraints);
    }

}