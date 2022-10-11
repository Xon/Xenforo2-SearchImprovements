<?php


namespace SV\SearchImprovements;

/**
 * This class is used to encapsulate global state between layers without using $GLOBAL[] or relying on the consumer
 * being loaded correctly by the dynamic class autoloader
 * Class Globals
 *
 * @package SV\SearchImprovements
 */
class Globals
{
    public static $shimSearchForSpecialization = true;

    public static function isPushingViewOtherChecksIntoSearch(): bool
    {
        return (\XF::options()->svPushViewOtherCheckIntoXFES ?? false) && \XF::isAddOnActive('XFES');
    }

    private function __construct() { }
}
