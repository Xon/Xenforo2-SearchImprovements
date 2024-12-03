<?php

namespace SV\SearchImprovements\XF\Repository;

use function array_diff;
use function count;
use function in_array;
use function is_array;
use function reset;

/**
 * @Extends \XF\Repository\Search
 */
class Search extends XFCP_Search
{
    public function getContainerTypeForContentType(string $contentType): ?string
    {
        if ($contentType === '')
        {
            return null;
        }

        try
        {
            $handler = \XF::app()->search()->handler($contentType);
        }
        catch (\Throwable $e)
        {
            return null;
        }

        return $handler->getGroupByType();
    }

    public function getChildContentTypeForContainerType(string $containerContentType): ?string
    {
        // XF lacks a link from the parent type to child types :`(
        $search = \XF::app()->search();
        foreach (\XF::app()->getContentTypeField('search_handler_class') as $contentType => $handlerClass)
        {
            if ($containerContentType === $contentType)
            {
                continue;
            }

            $handler = $search->handler($contentType);
            if ($handler !== null)
            {
                $childTypes = $handler->getSearchableContentTypes();
                if (is_array($childTypes) && count($childTypes) > 1 && in_array($containerContentType, $childTypes, true))
                {
                    $childTypes = array_diff($childTypes, [$containerContentType]);
                    if (count($childTypes) === 0)
                    {
                        return null;
                    }

                    return reset($childTypes);
                }
            }
        }

        return null;
    }
}