<?php

namespace SV\SearchImprovements;

abstract class PermissionCache extends \XF\PermissionCache
{
    public static function getPerms(string $contentType, string $permissionGroup): array
    {
        $visitor = \XF::visitor();
        $permissionCombinationId = $visitor->permission_combination_id;
        \XF::permissionCache()->cacheAllContentPerms($permissionCombinationId, $contentType);
        $permissionCache = $visitor->PermissionSet->getPermissionCache();
        return $permissionCache->contentPerms[$permissionCombinationId][$permissionGroup] ?? [];
    }
}
