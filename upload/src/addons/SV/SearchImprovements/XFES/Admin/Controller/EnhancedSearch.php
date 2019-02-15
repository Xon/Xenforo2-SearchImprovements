<?php

namespace SV\SearchImprovements\XFES\Admin\Controller;



/**
 * Extends \XFES\Admin\Controller\EnhancedSearch
 */
class EnhancedSearch extends XFCP_EnhancedSearch
{
    protected function getDynamicOptionValuesFromInput()
    {
        $input = parent::getDynamicOptionValuesFromInput();

        $input['svDefaultSearchOrder'] = $this->filter('svDefaultSearchOrder', 'str');

        return $input;
    }
}