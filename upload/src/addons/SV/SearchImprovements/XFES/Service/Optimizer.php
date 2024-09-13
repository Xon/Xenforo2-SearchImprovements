<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XFES\Service;

/**
 * @Extends \XFES\Service\Optimizer
 */
class Optimizer extends XFCP_Optimizer
{
    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    public function getExpectedMappingConfig()
    {
        /** @var \SV\SearchImprovements\XF\Search\SearchPatch $search */
        $search = \XF::app()->search();

        $oldVal = $search->specializedIndexProxying ?? false;
        $search->specializedIndexProxying = false;
        try
        {
            return parent::getExpectedMappingConfig();
        }
        finally
        {
            $search->specializedIndexProxying = $oldVal;
        }
    }
}