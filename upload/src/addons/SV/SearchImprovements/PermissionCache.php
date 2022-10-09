<?php

namespace SV\SearchImprovements;

abstract class PermissionCache extends \XF\PermissionCache
{
    public static function getNodePerms(): array
    {
        $visitor = \XF::visitor();
        $visitor->cacheNodePermissions();
        $permissionCache = $visitor->PermissionSet->getPermissionCache();
        return $permissionCache->contentPerms[$visitor->permission_combination_id]['node'] ?? [];
    }
}
