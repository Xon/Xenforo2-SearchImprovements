<?php

namespace SV\SearchImprovements\XF\Entity;

use SV\SearchImprovements\XF\Repository\Search as SearchRepo;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use function array_diff;
use function array_key_exists;
use function array_merge;
use function arsort;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * @property-read array<string,\XF\Phrase> $sv_structured_query
 */
class Search extends XFCP_Search
{
    /**
     * Special case for items pulled to the very start of the search term results
     * Overrides the $svDateConstraint/$svUserConstraint sort order
     * The order of items in this list is used in sortSearchConstraints
     * @var string[]
     */
    protected $svSortFirst = [
    ];
    /**
     * The order of items in this list is used in sortSearchConstraints
     * @var string[]
     */
    protected $svDateConstraint = [
        'newer_than',
        'older_than',
    ];
    /**
     * The order of items in this list is used in sortSearchConstraints
     * @var string[]
     */
    protected $svUserConstraint = [
        'users',
        'profile_users',
        'recipients', // SV/ConversationImprovements
        'participants', // SV/ReportImprovements mostly
        'assigned',
        'assigner',
    ];
    /**
     * Special case for items pulled to the very end of the search term results
     * Overrides the $svSortFirst/$svDateConstraint/$svUserConstraint sort order
     * The order of items in this list is used in sortSearchConstraints
     * @var string[]
     */
    protected $svSortLast = [
    ];

    protected $svIgnoreConstraint = [
        'child_nodes',
        'nodes',
    ];

    /** @var \XF\InputFilterer */
    protected $inputFilterer;

    public function __construct(Manager $em, Structure $structure, array $values = [], array $relations = [])
    {
        parent::__construct($em, $structure, $values, $relations);
        $this->inputFilterer = \XF::app()->inputFilterer();
        $this->setupConstraintFields();
    }

    protected function setupConstraintFields(): void
    {

    }

    protected function getContainerContentType(): ?string
    {
        $searchRepo = $this->repository('XF:Search');
        assert($searchRepo instanceof SearchRepo);
        return $searchRepo->getContainerTypeForContentType($this->search_type);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function formatConstraintValue(string $key, $value)
    {
        if ($value instanceof \XF\Phrase)
        {
            return $value->render('raw');
        }

        if (in_array($key, $this->svDateConstraint, true))
        {
            // yyyy-mm-dd
            $date = $this->inputFilterer->filter($value, 'datetime');
            if ($date)
            {
                return \XF::language()->date($date);
            }
        }

        if (in_array($key, $this->svUserConstraint, true))
        {
            $usernames = (array)$this->inputFilterer->filter((array)$value, 'array-string');

            $templater = \XF::app()->templater();
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $users = $userRepo->getUsersByNames($usernames, $notFound);

            $formattedUsernames = [];
            foreach ($users as $user)
            {
                assert($user instanceof \XF\Entity\User);
                $username = $user->username;
                if (!in_array($user->user_state, ['valid', 'email_confirm', 'email_confirm_edit', 'email_bounce'], true))
                {
                    $user = null;
                }

                $formattedUsernames[] = new \XF\PreEscaped($templater->func('username_link', [
                    $user, false, [
                        'username' => $username
                    ]
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
            /** @noinspection PhpUnnecessaryLocalVariableInspection */
            $value = implode(',', $formattedUsernames);

            return $value;
        }

        return (string)$this->inputFilterer->filter($value, 'string');
    }

    /**
     * @param string           $key
     * @param array|string|int $value
     * @return \XF\Phrase|null
     */
    protected function getSpecializedSearchConstraintPhrase(string $key, $value): ?\XF\Phrase
    {
        if ($key === 'thread' && is_numeric($value))
        {
            $thread = $this->app()->find('XF:Thread', (int)$value);
            if (($thread instanceof \XF\Entity\Thread) && $thread->canView())
            {
                return \XF::phrase('svSearchConstraint.thread_with_title', [
                    'url'   => $this->app()->router('public')->buildLink('threads', $thread),
                    'title' => $thread->title,
                ]);
            }

            return \XF::phrase('svSearchConstraint.thread_no_title');
        }

        if ($key === 'replies_upper' && $value === '0')
        {
            return \XF::phrase('svSearchConstraint.replies_none');
        }

        return null;
    }

    /**
     * @param array            $query
     * @param string           $key
     * @param array|string|int $value
     * @return bool
     */
    protected function expandStructuredSearchConstraint(array &$query, string $key, $value): bool
    {
        if (is_array($value) && $key === 'nodes')
        {
             /** @var \XF\Repository\Node $nodeRepo */
            $nodeRepo = $this->repository('XF:Node');
            $nodes = $nodeRepo->getFullNodeListCached('search')->filterViewable();
            foreach ($value as $id)
            {
                $id = (int)$id;
                if ($id === 0)
                {
                    continue;
                }

                /** @var \XF\Entity\Node|null $node */
                $node = $nodes[$id] ?? null;
                if ($node !== null)
                {
                    $query[$key . '_' . $id] = \XF::phrase('svSearchConstraint.nodes', [
                        'url' => $node->getContentUrl(),
                        'node' => $node->getContentTitle(),
                    ]);
                }
            }

            return true;
        }

        return false;
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
            if ($this->expandStructuredSearchConstraint($query, $key, $value))
            {
                continue;
            }

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
            if (in_array($key, $this->svIgnoreConstraint, true))
            {
                continue;
            }

            if (is_array($value))
            {
                // decompose this into multiple constraints
                $this->extractUnstructuredSearchConstraint($query, $value, $key . '_');
                continue;
            }

            $phrase = $this->getSearchConstraintPhrase($key, $value);
            if ($phrase === null)
            {
                $query[$key] = $value;
            }
        }
    }

    protected function getConstraintSortLists(): array
    {
        return [
            $this->svSortFirst,
            $this->svDateConstraint,
            $this->svUserConstraint,
        ];
    }


    protected function sortSearchConstraints(array $searchConstraint): array
    {
        // urlConstraints appears weirdly sorted, so apply some basic sorting to try to make this look good
        $constraints = [];
        $lastItems = [];
        foreach ($this->svSortLast as $key)
        {
            if (array_key_exists($key, $searchConstraint))
            {
                $lastItems[$key] = $searchConstraint[$key];
                unset($searchConstraint[$key]);
            }
        }
        foreach ($this->getConstraintSortLists() as $list)
        {
            foreach ($list as $key)
            {
                if (array_key_exists($key, $searchConstraint))
                {
                    $constraints[$key] = $searchConstraint[$key];
                    unset($searchConstraint[$key]);
                }
            }
        }
        foreach ($searchConstraint as $key => $value)
        {
            $constraints[$key] = $value;
        }
        foreach ($lastItems as $key => $value)
        {
            $constraints[$key] = $value;
        }

        return $constraints;
    }

    protected function getSvStructuredQuery(): array
    {
        $query = [];
        $searchConstraint = $this->sortSearchConstraints($this->search_constraints);

        $typeFilter = $searchConstraint['content'] ?? $searchConstraint['type'] ?? null;
        $containerOnly = \XF::isAddOnActive('SV/ElasticSearchEssentials') && ($searchConstraint['container_only'] ?? false);
        $addContentTypeTerm = $this->search_type !== '' && !$this->search_grouping;
        if ($containerOnly && $typeFilter !== null || $addContentTypeTerm && $containerOnly)
        {
            unset($searchConstraint['container_only']);
        }
        unset($searchConstraint['content'], $searchConstraint['type']);
        $this->extractStructuredSearchConstraint($query, $searchConstraint, '');
        foreach ($query as &$queryPhrase)
        {
            if ($queryPhrase instanceof \XF\Phrase)
            {
                $queryPhrase = $queryPhrase->render('raw');
            }
        }
        unset($queryPhrase);

        // add content-type
        if ($addContentTypeTerm)
        {
            try
            {
                $handler = \XF::app()->search()->handler($this->search_type);
            }
            catch (\Throwable $e)
            {
                $handler = null;
            }

            if ($handler !== null)
            {
                $types = [];
                $groupType = $handler->getGroupByType();
                $rawTypes = $handler->getSearchableContentTypes();
                if ($typeFilter !== null)
                {
                    $rawTypes = (array)$typeFilter;
                }
                else if ($groupType !== null && in_array($groupType, $rawTypes, true))
                {
                    if ($containerOnly)
                    {
                        $rawTypes = [$groupType];
                    }
                    else
                    {
                        // impose a consistent order which is the groupable-type and then other types
                        $rawTypes = array_merge([$groupType], array_diff($rawTypes, [$groupType]));
                    }
                }

                foreach ($rawTypes as $type)
                {
                    $types[$type] = \XF::app()->getContentTypePhrase($type, true);
                }

                if (count($types) !== 0)
                {
                    $query['svSearchOrder.' . $this->search_type] = \XF::phrase('svSearchClauses.content_type', [
                        'contentTypes' => implode(', ', $types),
                    ])->render('raw');
                }
            }
        }
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
            $query['svSearchClauses.group_by'] = \XF::phrase('svSearchClauses.group_by', [
                'value' => $value
            ])->render('raw');
        }

        return $query;
    }

    protected function getSvUnstructuredQuery(): array
    {
        $rawQuery = [];
        $searchConstraint = $this->search_constraints;
        unset($searchConstraint['content'], $searchConstraint['type']);
        $this->extractUnstructuredSearchConstraint($rawQuery, $searchConstraint, '');
        arsort($rawQuery);

        $structuredQuery = $this->sv_structured_query;
        $query = [];
        foreach ($rawQuery as $key => $value)
        {
            if (!array_key_exists($key, $structuredQuery))
            {
                $query['svSearchConstraint.'.$key] = $value;
            }
        }

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

    public function getContentHandler(): ?\XF\Search\Data\AbstractData
    {
        if ($this->search_type === '')
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

        return $handler;
    }

    public function setupFromQuery(\XF\Search\Query\KeywordQuery $query, array $constraints = [])
    {
        parent::setupFromQuery($query, $constraints);

        // smooth over differences between member search & normal search
        // XF does falsy check on getGroupByType result :(
        $handler = $this->getContentHandler();
        if ($handler !== null && !$handler->getGroupByType())
        {
            $searchRepo = $this->repository('XF:Search');
            assert($searchRepo instanceof SearchRepo);

            $firstChildType = $searchRepo->getChildContentTypeForContainerType($this->search_type);
            if ($firstChildType !== null)
            {
                $constraints = $this->search_constraints;
                $constraints['content']  = $this->search_type;
                $this->search_constraints = $constraints;
                $this->search_type = $firstChildType;
            }
        }
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['search_results']['required'] = false;
        $structure->columns['search_results']['default'] = [];

        $structure->getters['sv_structured_query'] = ['getter' => 'getSvStructuredQuery', 'cache' => true];
        $structure->getters['sv_unstructured_query'] = ['getter' => 'getSvUnstructuredQuery', 'cache' => true];
        $structure->options['svSearchImprovements'] = true;

        return $structure;
    }
}