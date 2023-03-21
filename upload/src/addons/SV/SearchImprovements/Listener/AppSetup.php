<?php

namespace SV\SearchImprovements\Listener;

/**
 * @deprecated
 */
abstract class AppSetup
{
    private function __construct() { }

    public static function appSetup(\XF\App $app): void
    {
    }
}