<?php
/**
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */

namespace SV\SearchImprovements\XF\Repository;

/** @noinspection PhpUndefinedClassInspection */
\SV\StandardLib\Helper::repo()->aliasClass(
    SearchPatch::class,
    \XF::$versionId < 2020000
        ? \SV\SearchImprovements\XF\Repository\XF21\SearchPatch::class
        : \SV\SearchImprovements\XF\Repository\XF22\SearchPatch::class
);
