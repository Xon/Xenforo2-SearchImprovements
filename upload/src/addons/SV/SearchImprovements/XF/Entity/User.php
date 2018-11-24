<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\User
 *
 * @property UserOption Option
 */
class User extends XFCP_User
{
    public function canChangeSearchOptions()
    {
        if (!$this->canSearch())
        {
            return false;
        }

        $searcher = $this->app()->search();
        if (!$searcher->isRelevanceSupported())
        {
            return false;
        }

        return $this->hasPermission('general', 'sv_searchOptions');
    }
}