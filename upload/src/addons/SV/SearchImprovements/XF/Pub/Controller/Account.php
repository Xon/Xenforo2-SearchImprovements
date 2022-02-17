<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SearchImprovements\XF\Pub\Controller;

use XF\Entity\User;

/**
 * Extends \XF\Pub\Controller\Account
 */
class Account extends XFCP_Account
{
    /**
     * @param User $visitor
     * @return \XF\Mvc\FormAction
     */
    protected function preferencesSaveProcess(User $visitor)
    {
        $form = parent::preferencesSaveProcess($visitor);

        /** @var \SV\SearchImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        if ($visitor->hasOption('svSearchOptions') && $visitor->canChangeSearchOptions())
        {
            $input = $this->filter(
                [
                    'option' => [
                        'sv_default_search_order' => 'str',
                    ],
                ]
            );

            $userOptions = $visitor->getRelationOrDefault('Option');
            $form->setupEntityInput($userOptions, $input['option']);
        }

        return $form;
    }
}