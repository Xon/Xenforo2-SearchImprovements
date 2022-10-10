<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use SV\SearchImprovements\Search\DiscussionUserTrait;
use XF\Search\MetadataStructure;
use function array_column, array_filter, array_map, array_unique;

class Thread extends XFCP_Thread
{
    use DiscussionUserTrait;

    protected function getMetaData(\XF\Entity\Thread $entity)
    {
        $metaData = parent::getMetaData($entity);

        $this->populateDiscussionUserMetaData($entity, $metaData);

        return $metaData;
    }

    protected function setupDiscussionUserMetadata(\XF\Mvc\Entity\Entity $entity, array &$metaData)
    {
        /** @var \XF\Entity\Thread $entity */
        if (\XF::isAddOnActive('SV/ViewStickyThreads'))
        {
            if ($entity->sticky ?? false)
            {
                $metaData['sticky'] = true;
            }
        }
    }

    protected function setupDiscussionUserMetadataStructure(MetadataStructure $structure)
    {
        if (\XF::isAddOnActive('SV/ViewStickyThreads'))
        {
            $structure->addField('sticky', MetadataStructure::BOOL);
        }
    }
}