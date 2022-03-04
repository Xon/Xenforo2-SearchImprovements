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

    private function __construct() { }
}
