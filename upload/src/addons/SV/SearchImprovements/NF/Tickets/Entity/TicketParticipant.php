<?php

namespace SV\SearchImprovements\NF\Tickets\Entity;

use XF\Behavior\IndexableContainer;

/**
 * @Extends \NF\Tickets\Entity\TicketParticipant
 */
class TicketParticipant extends XFCP_TicketParticipant
{
    protected function _postSave()
    {
        parent::_postSave();
        $this->indexTicket();
    }

    protected function _postDelete()
    {
        parent::_postDelete();
        $this->indexTicket();
    }

    protected function indexTicket(): void
    {
        \XF::runOnce('nfIndexTicket'.$this->ticket_id, function() {
            $ticket = $this->Ticket;
            if ($ticket !== null)
            {
                /** @var IndexableContainer $indexableContainer */
                $indexableContainer = $ticket->getBehavior('XF:IndexableContainer');
                if ($indexableContainer !== null)
                {
                    $indexableContainer->triggerReindex();
                }
            }
        });
    }
}