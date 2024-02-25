<?php

namespace SV\SearchImprovements;

use SV\SearchImprovements\Repository\Search as SearchRepo;

abstract class Globals
{
    /** @var bool */
    public static $shimSearchForSpecialization = true;
    /** @var ?array */
    public static $capturedSearchDebugInfo = null;

    /**
     * @deprecated
     */
    public static function repo(): SearchRepo
    {
        return SearchRepo::get();
    }

    private function __construct() { }
}
