<?php

namespace SV\SearchImprovements\XF\Entity;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\Features\ISearchableReplyCount;
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

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $repo = Globals::repo();
        if ($repo->isUsingElasticSearch())
        {
            $repo->addContainerIndexableField($structure, 'reply_count');
        }

        return $structure;
    }
}