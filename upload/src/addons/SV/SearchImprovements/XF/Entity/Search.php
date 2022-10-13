<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use function arsort;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;

/**
 * @property-read array<string,\XF\Phrase> $sv_structured_query
 */
class Search extends XFCP_Search
{
    protected $svDateConstraint = [
        'newer_than',
        'older_than',
    ];
    protected $svUserConstraint = [
        'users',
        'profile_users',
        'recipients', // SV/ConversationImprovements
    ];
    protected $svIgnoreConstraint = [
    ];

    public function __construct(Manager $em, Structure $structure, array $values = [], array $relations = [])
    {
        parent::__construct($em, $structure, $values, $relations);
        $this->setupConstraintFields();
    }

    protected function setupConstraintFields(): void
    {

    }

    protected function getContainerContentType(): ?string
    {
        if (!$this->search_type)
        {
            return null;
        }

        try
        {
            $handler = \XF::app()->search()->handler($this->search_type);
        }
        catch (\Throwable $e)
        {
            return null;
        }

        return $handler->getGroupByType();
    }

    protected function formatConstraintValue(string $key, $value)
    {
        $lang = \XF::language();
        if (in_array($key, $this->svDateConstraint, true))
        {
            // yyyy-mm-dd
            $date = @strtotime($value);
            if ($date)
            {
                $value = $lang->date($date);
            }
        }

        if (in_array($key, $this->svUserConstraint, true))
        {
            $usernames = (array)$value;

            $templater = \XF::app()->templater();
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $users = $userRepo->getUsersByNames($usernames, $notFound);

            $formattedUsernames = [];
            foreach ($users as $user)
            {
                $formattedUsernames[] = new \XF\PreEscaped($templater->func('username_link', [
                    $user, false
                ]));
            }
            foreach ($notFound as $username)
            {
                $formattedUsernames[] = new \XF\PreEscaped($templater->func('username_link', [
                    null, false, [
                        'username' => $username
                    ],
                ]));
            }
            $value = implode(',', $formattedUsernames);
        }

        return $value;
    }

    /**
     * @param string           $key
     * @param array|string|int $value
     * @return \XF\Phrase|null
     */
    protected function getSpecializedSearchConstraintPhrase(string $key, $value): ?\XF\Phrase
    {
        return null;
    }

    /**
     * @param string           $key
     * @param array|string|int $value
     * @return \XF\Phrase|null
     */
    protected function getSearchConstraintPhrase(string $key, $value): ?\XF\Phrase
    {
        if (in_array($key, $this->svIgnoreConstraint, true))
        {
            return null;
        }

        $phrase = $this->getSpecializedSearchConstraintPhrase($key, $value);
        if ($phrase !== null)
        {
            return $phrase;
        }

        $phraseKey = 'svSearchConstraint.' . $key;
        $phraseText = \XF::language()->getPhraseText($phraseKey);
        if (!is_string($phraseText))
        {
            return null;
        }

        $value = $this->formatConstraintValue($key, $value);

        return \XF::phrase($phraseKey, [
            'value' => $value,
        ]);
    }

    protected function getSearchOrderPhrase(string $searchOrder): ?\XF\Phrase
    {
        $phraseKey = 'svSearchOrder.' . $searchOrder;
        $phrase = \XF::language()->getPhraseText($phraseKey);
        if (!is_string($phrase))
        {
            return null;
        }

        return \XF::phrase($phraseKey);
    }

    protected function extractStructuredSearchConstraint(array &$query, array $constraints, string $prefix)
    {
        foreach ($constraints as $key => $value)
        {
            $key = $prefix . $key;
            if (is_array($value))
            {
                // decompose this into multiple constraints
                $this->extractStructuredSearchConstraint($query, $value, $key . '_');
                continue;
            }

            $phrase = $this->getSearchConstraintPhrase($key, $value);
            if ($phrase !== null)
            {
                $query[$key] = $phrase->render('raw');
            }
        }
    }


    protected function extractUnstructuredSearchConstraint(array &$query, array $constraints, string $prefix)
    {
        foreach ($constraints as $key => $value)
        {
            $key = $prefix . $key;
            if (is_array($value))
            {
                // decompose this into multiple constraints
                $this->extractUnstructuredSearchConstraint($query, $value, $key . '_');
                continue;
            }

            if (in_array($key, $this->svIgnoreConstraint, true))
            {
                continue;
            }
            $phrase = $this->getSearchConstraintPhrase($key, $value);
            if ($phrase === null)
            {
                $query['svSearchConstraint.'.$key] = $value;
            }
        }
    }

    protected function getSvStructuredQuery(): array
    {
        $query = [];
        $this->extractStructuredSearchConstraint($query, $this->search_constraints , '');
        //
        arsort($query);
        // add sort-by clause
        $phrase = $this->getSearchOrderPhrase($this->search_order);
        if ($phrase !== null)
        {
            $query['svSearchOrder.'.$this->search_order] = \XF::phrase('svSearchClauses.order_by', [
                'order' => $phrase
            ])->render('raw');
        }
        if ($this->search_grouping)
        {
            $contentType = $this->getContainerContentType() ?? $this->search_type;
            $value = \XF::app()->getContentTypePhrase($contentType, true);
            $query['svSearchClauses.group_by'] = \XF::phrase('svSearchClauses.order_by', [
                'value' => $value
            ])->render('raw');
        }

        return $query;
    }

    protected function getSvUnstructuredQuery(): array
    {
        $query = [];
        $this->extractUnstructuredSearchConstraint($query, $this->search_constraints, '');
        arsort($query);
        // add sort-by clause
        $phrase = $this->getSearchOrderPhrase($this->search_order);
        if ($phrase === null)
        {
            $query['svSearchOrder.'.$this->search_order] = \XF::phrase('svSearchOrder.order_by_clause', [
                'order' => $this->search_order
            ])->render('raw');
        }

        return $query;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['sv_structured_query'] = ['getter' => 'getSvStructuredQuery', 'cache' => true];
        $structure->getters['sv_unstructured_query'] = ['getter' => 'getSvUnstructuredQuery', 'cache' => true];
        $structure->options['svSearchImprovements'] = true;

        return $structure;
    }
}