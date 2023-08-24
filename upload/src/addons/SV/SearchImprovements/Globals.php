<?php

namespace SV\SearchImprovements;

use SV\SearchImprovements\Repository\Search;

abstract class Globals
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
