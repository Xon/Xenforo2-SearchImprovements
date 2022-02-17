<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserOption
 *
 * @property string sv_default_search_order
 */
class UserOption extends XFCP_UserOption
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_default_search_order'] = ['type' => self::STR, 'default' => ''];

        return $structure;
    }
}