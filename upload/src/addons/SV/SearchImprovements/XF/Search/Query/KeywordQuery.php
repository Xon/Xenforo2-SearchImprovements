<?php

namespace SV\SearchImprovements\XF\Search\Query;

use SV\SearchImprovements\Search\Features\SearchOrder;
use XF\Search\Query\MetadataConstraint;

/**
 * @extends \XF\Search\Query\KeywordQuery
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

    public function setParsedKeywords(?string $keywords = null)
    {
        $this->parsedKeywords = $keywords;
    }

    /**
     * @param MetadataConstraint[] $metadataConstraints
     */
    public function setMetadataConstraints(array $metadataConstraints): void
    {
        $this->metadataConstraints = $metadataConstraints;
    }

    public function hasQueryConstraints()
    {
        if ($this->order instanceof SearchOrder && $this->order->xfesOnly())
        {
            return false;
        }

        return parent::hasQueryConstraints();
    }
}
