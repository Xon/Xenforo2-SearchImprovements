<?php

namespace SV\SearchImprovements\SV\StandardLib;

use SV\SearchImprovements\Util\Arr;
use XF\Template\Templater as BaseTemplater;
use function array_unshift;
use function explode;

/**
 * Extends \SV\StandardLib\TemplaterHelper
 */
class TemplaterHelper extends XFCP_TemplaterHelper
{
    /** @noinspection SpellCheckingInspection */
    public function addDefaultHandlers()
    {
        parent::addDefaultHandlers();

        $this->addFilter('dottoarray', 'filterDotToArray');
        $this->addFunction('getdotted', 'fnGetDotted');
        $this->addFunction('issetdotted', 'fnIssetDotted');
    }

    /**
     * @param BaseTemplater $templater
     * @param string        $value
     * @param bool          $escape
     * @return string
     * @noinspection PhpUnusedParameterInspection
     */
    public function filterDotToArray(BaseTemplater $templater, string $value, bool $escape): string
    {
        $parts = explode('.', $value);
        $value = array_shift($parts);
        foreach ($parts as &$part)
        {
            $part = '[' . $part . ']';
        }

        return $value . implode($parts);
    }

    /**
     * @param BaseTemplater $templater
     * @param bool          $escape
     * @param array|null    $input
     * @param string        $path
     * @param mixed         $default
     * @return mixed
     * @noinspection PhpUnusedParameterInspection
     */
    public function fnGetDotted(BaseTemplater $templater, bool &$escape, ?array $input, string $path, $default)
    {
        if ($input === null)
        {
            return $default;
        }

        return Arr::getByPath($input, $path, $default);
    }

    /**
     * @param BaseTemplater $templater
     * @param bool          $escape
     * @param array|null    $input
     * @param string        $path
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function fnIssetDotted(BaseTemplater $templater, bool &$escape, ?array $input, string $path): bool
    {
        if ($input === null)
        {
            return false;
        }

        return Arr::existsByPath($input, $path);
    }
}