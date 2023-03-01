<?php

namespace SV\SearchImprovements\XF\Entity;

use SV\SearchImprovements\Search\Features\ISearchableReplyCount;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\ConversationMaster
 */
class ConversationMaster extends XFCP_ConversationMaster implements ISearchableReplyCount
{
    public function getReplyCountForSearch(): int
    {
        return $this->reply_count;
    }
}