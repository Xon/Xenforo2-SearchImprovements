<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Behavior\IndexableContainer;

class ThreadCollab extends XFCP_ThreadCollab
{
    public function rebuildCollaborativeThreadCounter()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::rebuildCollaborativeThreadCounter();

        if ($this->hasOption('svReindexThreadForCollaborators') && $this->getOption('svReindexThreadForCollaborators') && $this->hasBehavior('XF:IndexableContainer'))
        {
            /** @var IndexableContainer $indexableContainer */
            $indexableContainer = $this->getBehavior('XF:IndexableContainer');
            $indexableContainer->triggerReindex();
        }
    }
}