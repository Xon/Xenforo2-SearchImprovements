<?php
/**
 * @noinspection RedundantSuppression
 */

namespace SV\SearchImprovements\XF\Search\Query;

use XF\Search\Query\MetadataConstraint;

/**
 * XF2.0/XF2.1 only, as XF2.2+ instead use specialized instances
 *
 * Note; parsedKeywords moved to KeywordQuery in XF2.2+
 * @extends \XF\Search\Query\Query
 */
class Query extends XFCP_Query
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
        /** @noinspection PhpDynamicFieldDeclarationInspection */
        $this->parsedKeywords = $keywords;
    }

    /**
     * @param MetadataConstraint[] $metadataConstraints
     */
    public function setMetadataConstraints(array $metadataConstraints): void
    {
        $this->metadataConstraints = $metadataConstraints;
    }
}
