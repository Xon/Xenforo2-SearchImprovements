<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Entity;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\Features\ISearchableDiscussionUser;
use SV\SearchImprovements\Search\Features\ISearchableReplyCount;
use XF\Mvc\Entity\Structure;
use function array_column;

/**
 * Extends \XF\Entity\Thread
 */
class Thread extends XFCP_Thread implements ISearchableDiscussionUser, ISearchableReplyCount
{
    /**
     * @return array<int>
     */
    public function getDiscussionUserIds(): array
    {
        /** @var \SV\CollaborativeThreads\XF\Entity\Thread $this */
        if (($this->sv_collaborator_count ?? 0) > 0)
        {
            $userIds = array_column($this->getRelationFinder('CollaborativeUsers')->fetchColumns('user_id'), 'user_id');
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

        return $userIds;
    }

    public function getReplyCountForSearch(): int
    {
        return $this->reply_count;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        if (Globals::isPushingViewOtherChecksIntoSearch() && isset($structure->behaviors['XF:IndexableContainer']))
        {
            $structure->options['svReindexThreadForCollaborators'] = false;
            Globals::addContainerIndexableField($structure, 'user_id');
            if (\XF::isAddOnActive('SV/ViewStickyThreads'))
            {
                Globals::addContainerIndexableField($structure, 'sticky');
            }
        }
        if (Globals::isUsingElasticSearch())
        {
            Globals::addContainerIndexableField($structure, 'reply_count');
        }
    
        return $structure;
    }
}