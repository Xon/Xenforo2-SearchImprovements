<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Entity;

use SV\SearchImprovements\Globals;
use XF\Mvc\Entity\Structure;
use function array_column,array_filter,array_map,array_unique;

/**
 * Extends \XF\Entity\Thread
 * @property-read int[] $discussion_user_ids
 */
class Thread extends XFCP_Thread
{
    /**
     * @return array<int>
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    protected function getDiscussionUserIds(): array
    {
        /** @var \SV\CollaborativeThreads\XF\Entity\Thread $this */
        if (($this->sv_collaborator_count ?? 0) > 0)
        {
            $userIds = array_column($this->getRelationFinder('CollaborativeUsers')->fetchColumns('user_id'), 'user_id');
            $userIds = array_filter(array_map('\intval', $userIds));
        }
        else
        {
            $userIds = [];
        }
        $userId = $this->user_id;
        if ($userId !== 0)
        {
            $userIds[] = $userId;
        }
        $userIds = array_unique($userIds);

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
                $structure->options['svReindexThreadForCollaborators'] = false;
                $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'user_id';
                if (\XF::isAddOnActive('SV/ViewStickyThreads'))
                {
                    $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'sticky';
                }
            }
            $structure->getters['discussion_user_ids'] = ['getter' => 'getDiscussionUserIds', 'cache' => true];
        }
    
        return $structure;
    }
}