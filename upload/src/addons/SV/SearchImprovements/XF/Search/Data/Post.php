<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Search\Data;

use SV\SearchImprovements\PermissionCache;
use SV\SearchImprovements\XF\Search\Query\Constraints\AndConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\ExistsConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use function count;

class Post extends XFCP_Post
{
    protected function getMetaData(\XF\Entity\Post $entity)
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

    public function getTypePermissionConstraints(\XF\Search\Query\Query $query, $isOnlyType)
    {
        $constraints = parent::getTypePermissionConstraints($query, $isOnlyType) ?? [];
        if (!(\XF::options()->svPushViewOtherCheckIntoXFES ?? false))
        {
            return $constraints;
        }

        // These are only meaningful with ElasticSearch+XenForo Enhanced Search
        if (!\XF::isAddOnActive('XFES'))
        {
            return $constraints;
        }

        // Node permissions are flat data, but the visibility status encodes hierarchical view data
        $nodePerms = PermissionCache::getNodePerms();

        $nonViewableNodeIds = $viewableStickiesNodeIds = [];
        $viewStickies = \XF::isAddOnActive('SV/ViewStickyThreads');
        foreach($nodePerms as $nodeId => $perm)
        {
            if (count($perm) > 1)
            {
                // view check is done in parent::getTypePermissionConstraints
                if (!empty($perm['view']) && empty($perm['viewOthers']))
                {
                    $nonViewableNodeIds[] = $nodeId;
                    if ($viewStickies && !empty($perm['viewStickies']))
                    {
                        $viewableStickiesNodeIds[] = $nodeId;
                    }
                }
            }
        }

        if (count($nonViewableNodeIds) !== 0 || count($viewableStickiesNodeIds) !== 0)
        {
            // Note; ElasticSearchEssentials forces all getTypePermissionConstraints to have $isOnlyType=true as it knows how to compose multiple types together
            $constraints[] = new OrConstraint(
                $isOnlyType ? null : new NotConstraint(new ExistsConstraint('node')),
                new MetadataConstraint('discussion_user', \XF::visitor()->user_id),
                count($viewableStickiesNodeIds) === 0
                    ? null
                    : new AndConstraint(
                        new ExistsConstraint('sticky'),
                        new MetadataConstraint('node', $viewableStickiesNodeIds)
                ),
                count($nonViewableNodeIds) === 0
                    ?  null
                    : new NotConstraint(new MetadataConstraint('node', $nonViewableNodeIds))
            );
        }

        return $constraints;
    }
}