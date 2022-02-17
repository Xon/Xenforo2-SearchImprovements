<?php

namespace SV\SearchImprovements\XF\Search\Query;

use XF\Search\Query\MetadataConstraint;

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

    public function setParsedKeywords(string $keywords = null)
    {
        $this->parsedKeywords = $keywords;
    }

    /**
     * @param MetadataConstraint[] $metadataConstraints
     */
    public function setMetadataConstraints(array $metadataConstraints)
    {
        $this->metadataConstraints = $metadataConstraints;
    }
}
