<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\NF\Tickets\Entity;

use SV\SearchImprovements\Globals;
use XF\Mvc\Entity\Structure;
use function array_column,array_filter,array_map;

/**
 * Extends \NF\Tickets\Entity\Ticket
 */
class Ticket extends XFCP_Ticket
{
    /**
     * @return array<int>
     */
    protected function getDiscussionUserIds(): array
    {
        $userIds = array_column($this->getRelationFinder('Participants')->fetchColumns('user_id'), 'user_id');
        $userIds = array_filter(array_map('\intval', $userIds));
        $userId = $this->user_id;
        if ($userId !== 0)
        {
            $userIds[] = $userId;
        }

        return $userIds;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        if (Globals::isPushingViewOtherChecksIntoSearch())
        {
            if (isset($structure->behaviors['XF:IndexableContainer']))
            {
                $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'user_id';
            }
            $structure->getters['discussion_user_ids'] = ['getter' => 'getDiscussionUserIds', 'cache' => true];
        }

        return $structure;
    }
}