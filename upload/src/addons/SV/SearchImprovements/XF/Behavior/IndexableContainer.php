<?php

namespace SV\SearchImprovements\XF\Behavior;

/**
 * Extends \XF\Behavior\IndexableContainer
 */
class IndexableContainer extends XFCP_IndexableContainer
{
    public function triggerReindex()
    {
        \XF::runOnce(
            'searchIndex-children-' . $this->contentType() . $this->entity->getEntityId(),
            function()
            {
                parent::triggerReindex();
            }
        );
    }
}