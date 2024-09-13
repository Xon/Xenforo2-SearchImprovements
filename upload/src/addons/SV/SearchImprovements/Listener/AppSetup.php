<?php

namespace SV\SearchImprovements\Listener;

use XF\App;

/**
 * @deprecated
 */
abstract class AppSetup
{
    private function __construct() { }

    public static function appSetup(App $app): void
    {
    }
}