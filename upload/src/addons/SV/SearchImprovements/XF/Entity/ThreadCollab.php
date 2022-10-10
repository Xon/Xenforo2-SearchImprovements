<?php

namespace SV\SearchImprovements\XF\Entity;

class ThreadCollab extends XFCP_ThreadCollab
{
    public function rebuildCollaborativeThreadCounter()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::rebuildCollaborativeThreadCounter();

        if ($this->getOption('svReindexThreadForCollaborators'))
        {
            /** @var \XF\Behavior\IndexableContainer $indexableContainer */
            $indexableContainer = $this->getBehavior('XF:IndexableContainer');
            if ($indexableContainer !== null)
            {
                $indexableContainer->triggerReindex();
            }
        }
    }
}