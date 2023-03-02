<?php

namespace SV\SearchImprovements\SV\ConversationImprovements\Search\Data;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Search\MetadataStructure;

/**
 * Extends \SV\ConversationImprovements\Search\Data\ConversationMessage
 */
class ConversationMessage extends XFCP_ConversationMessage
{
    protected static $svDiscussionEntity = \XF\Entity\ConversationMaster::class;
    use DiscussionTrait;

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function getMetadata(\XF\Entity\ConversationMessage $entity)
    {
        $metaData = parent::getMetadata($entity);

        $this->populateDiscussionMetaData($entity->Conversation, $metaData);

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        parent::setupMetadataStructure($structure);

        $this->setupDiscussionMetadataStructure($structure);
    }

    public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $repo = Globals::repo();
        if ($repo->applyRepliesConstraint($query, $request,
            function () use (&$urlConstraints) {
                unset($urlConstraints['replies']['upper']);
            }, function () use (&$urlConstraints) {
                unset($urlConstraints['replies']['lower']);
            }, [$this->getConversationQueryTableReference()]))
        {
            $request->set('c.min_reply_count', 0);
        }

        parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }
}