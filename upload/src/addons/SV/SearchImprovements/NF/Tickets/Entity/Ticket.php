<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\NF\Tickets\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;

/**
 * Extends \NF\Tickets\Entity\Ticket
 */
class Ticket extends XFCP_Ticket
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
            }
        }

        return $structure;
    }
}