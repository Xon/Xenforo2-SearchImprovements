<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Behavior\IndexableContainer;

class ThreadCollab extends XFCP_ThreadCollab
{
    public function rebuildCollaborativeThreadCounter()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::rebuildCollaborativeThreadCounter();

        if ($this->hasOption('svReindexThreadForCollaborators') && $this->getOption('svReindexThreadForCollaborators'))
        {
            /** @var IndexableContainer $indexableContainer */
            $indexableContainer = $this->getBehavior('XF:IndexableContainer');
            if ($indexableContainer !== null)
            {
                $indexableContainer->triggerReindex();
            }
        }
    }
}