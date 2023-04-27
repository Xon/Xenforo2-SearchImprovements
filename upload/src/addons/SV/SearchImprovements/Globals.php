<?php

namespace SV\SearchImprovements;

use SV\SearchImprovements\Repository\Search;

/**
 * This class is used to encapsulate global state between layers without using $GLOBAL[] or relying on the consumer
 * being loaded correctly by the dynamic class autoloader
 * Class Globals
 *
 * @package SV\SearchImprovements
 */
class Globals
{
    /** @var bool */
    public static $shimSearchForSpecialization = true;
    /** @var ?array */
    public static $capturedSearchDebugInfo = null;

    public static function repo(): Search
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return \XF::repository('SV\SearchImprovements:Search');
    }

    private function __construct() { }
}
