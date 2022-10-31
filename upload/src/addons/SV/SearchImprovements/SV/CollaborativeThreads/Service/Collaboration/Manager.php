<?php

namespace SV\SearchImprovements\SV\CollaborativeThreads\Service\Collaboration;

use function count;

class Manager extends XFCP_Manager
{
    public function setCollaborators(array $groupedCollaborators, bool $checkPermissions = true, bool $triggerErrors = true)
    {
        parent::setCollaborators($groupedCollaborators, $checkPermissions, $triggerErrors);

        if ($this->thread->hasOption('svReindexThreadForCollaborators') &&
            count($this->errors) === 0 && (count($this->addCollaborators) !== 0 && count($this->removeCollaborators) !== 0))
        {
            $this->thread->setOption('svReindexThreadForCollaborators', true);
        }
    }
}