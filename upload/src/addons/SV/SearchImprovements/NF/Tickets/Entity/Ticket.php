<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\NF\Tickets\Entity;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\Features\ISearchableDiscussionUser;
use SV\SearchImprovements\Search\Features\ISearchableReplyCount;
use XF\Mvc\Entity\Structure;
use function array_column;

/**
 * Extends \NF\Tickets\Entity\Ticket
 */
class Ticket extends XFCP_Ticket implements ISearchableDiscussionUser, ISearchableReplyCount
{
    /**
     * @return array<int>
     */
    public function getDiscussionUserIds(): array
    {
        $userIds = array_column($this->getRelationFinder('Participants')->fetchColumns('user_id'), 'user_id');
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

        if (Globals::isPushingViewOtherChecksIntoSearch())
        {
            Globals::addContainerIndexableField($structure, 'user_id');
        }
        if (Globals::isUsingElasticSearch())
        {
            Globals::addContainerIndexableField($structure, 'reply_count');
        }

        return $structure;
    }
}