<?php

namespace SV\SearchImprovements\XFES\Repository;

/**
 * Extends \XFES\Repository\IndexFailed
 */
class IndexFailed extends XFCP_IndexFailed
{
    /** @var string|string[]|null */
    public $svSpecializedContentTypes = null;

    /** @noinspection PhpMissingReturnTypeInspection */
    public function findRetryableRecords()
    {
        $finder = parent::findRetryableRecords();
        if ($this->svSpecializedContentTypes !== null)
        {
            $finder->where('content_type', $this->svSpecializedContentTypes);
        }

        return $finder;
    }
}