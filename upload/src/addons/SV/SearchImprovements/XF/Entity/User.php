<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;
use function strlen;

/**
 * @Extends \XF\Entity\User
 *
 * @property UserOption Option
 */
class User extends XFCP_User
{
    public function getDefaultSearchOrder(): string
    {
        $userSearchOrder = $this->Option->sv_default_search_order ?? '';
        if ($this->canChangeSearchOptions() && strlen($userSearchOrder) !== 0)
        {
            return $userSearchOrder;
        }

        $globalSearchOrder = \XF::options()->svDefaultSearchOrder ?? '';

        if (strlen($globalSearchOrder) !== 0)
        {
            return $globalSearchOrder;
        }

        return '';
    }

    public function canChangeSearchOptions(): bool
    {
        if (!$this->canSearch())
        {
            return false;
        }

        $searcher = \XF::app()->search();
        if (!$searcher->isRelevanceSupported())
        {
            return false;
        }

        return $this->hasPermission('general', 'sv_searchOptions');
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->options['svSearchOptions'] = true;

        return $structure;
    }
}