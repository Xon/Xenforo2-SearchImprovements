<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use XF\Search\MetadataStructure;
use function array_column, array_filter, array_map, array_unique;

class Thread extends XFCP_Thread
{
    protected function getMetaData(\XF\Entity\Thread $entity)
    {
        $metaData = parent::getMetaData($entity);

        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $thread = $entity;
            if (\XF::isAddOnActive('SV/ViewStickyThreads'))
            {
                if ($thread->sticky ?? false)
                {
                    $metaData['sticky'] = true;
                }
            }
            if (($thread->sv_collaborator_count ?? 0) > 0)
            {
                $userIds = array_column($thread->getRelationFinder('CollaborativeUsers')->fetchColumns('user_id'), 'user_id');
            }
            else
            {
                $userIds = [];
            }
            $userIds[] = $thread->user_id;
            $userIds = array_unique(array_filter(array_map('\intval', $userIds)));
            if (count($userIds) !== 0)
            {
                $metaData['discussion_user'] = $userIds;
            }
        }

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        parent::setupMetadataStructure($structure);
        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $structure->addField('discussion_user', MetadataStructure::INT);
            if (\XF::isAddOnActive('SV/ViewStickyThreads'))
            {
                $structure->addField('sticky', MetadataStructure::BOOL);
            }
        }
    }
}