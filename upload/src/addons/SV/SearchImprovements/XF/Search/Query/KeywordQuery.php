<?php

namespace SV\SearchImprovements\XF\Search\Query;

use XF\Search\Query\MetadataConstraint;

/**
 * XF2.2+ only instead use specialized instances
 */
class KeywordQuery extends XFCP_KeywordQuery
{
    /** @var bool */
    protected $forceContentWeighting = false;

    public function isForceContentWeighting(): bool
    {
        return $this->forceContentWeighting;
    }

    public function setForceContentWeighting(bool $forceContentWeighting)
    {
        $this->forceContentWeighting = $forceContentWeighting;
    }

    public function setParsedKeywords($keywords)
    {
        $this->parsedKeywords = $keywords;
    }

    /**
     * @param MetadataConstraint[] $metadataConstraints
     */
    public function setMetadataConstraints($metadataConstraints)
    {
        $this->metadataConstraints = $metadataConstraints;
    }
}
