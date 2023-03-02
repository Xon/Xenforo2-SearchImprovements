<?php

namespace SV\SearchImprovements;

use XF\Mvc\Entity\Entity;
use function array_key_exists;

abstract class EntityGetterCache extends Entity
{
    /**
     * @param Entity   $entity
     * @param string   $cacheKey
     * @param callable $rebuild
     * @return mixed
     */
    public static function getCachedValue(Entity $entity, string $cacheKey, callable $rebuild)
    {
        if (!array_key_exists($cacheKey, $entity->_getterCache))
        {
            $entity->_getterCache[$cacheKey] = $rebuild();
        }

        return $entity->_getterCache[$cacheKey];
    }
}