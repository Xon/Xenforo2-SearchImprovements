<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use XF\Search\MetadataStructure;

class Thread extends XFCP_Thread
{
    protected function getMetaData(\XF\Entity\Thread $entity)
    {
        $metaData = parent::getMetaData($entity);

        if (\XF::options()->svPushViewOtherCheckIntoXFES ?? false)
        {
            $metaData['discussion_user'] = $entity->Thread->user_id ?? 0;
            $isSticky = $entity->Thread->sticky ?? false;
            if (\XF::isAddOnActive('SV/ViewStickyThreads') && $isSticky)
            {
                $metaData['sticky'] = $isSticky;
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