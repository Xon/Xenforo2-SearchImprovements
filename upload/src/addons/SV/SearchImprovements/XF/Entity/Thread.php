<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\Thread
 */
class Thread extends XFCP_Thread
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        if (isset($structure->behaviors['XF:IndexableContainer']))
        {
            if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
            {
                $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'user_id';
                if (\XF::isAddOnActive('SV/ViewStickyThreads'))
                {
                    $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'sticky';
                }
            }
        }
    
        return $structure;
    }
}