<?php
/**
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */

namespace SV\SearchImprovements\XF\Entity;

/** @noinspection PhpUndefinedClassInspection */
\SV\StandardLib\Helper::repo()->aliasClass(
    SearchPatch::class,
    \XF::$versionId < 2020000
        ? \SV\SearchImprovements\XF\Entity\XF21\SearchPatch::class
        : \SV\SearchImprovements\XF\Entity\XF22\SearchPatch::class
);
