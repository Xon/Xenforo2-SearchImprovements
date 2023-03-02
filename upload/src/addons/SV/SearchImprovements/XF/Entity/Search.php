<?php

namespace SV\SearchImprovements\XF\Entity;

use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use function array_diff;
use function array_merge;
use function arsort;
use function assert;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;

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
        'child_nodes',
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
            if ($thread !== null)
            {
                assert($thread instanceof \XF\Entity\Thread);
                return \XF::phrase('svSearchConstraint.thread', [
                    'url' => $this->app()->router('public')->buildLink('threads', $thread),
                    'title' => $thread->title,
                ]);
            }
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
        if ($this->search_type === 'post' && $key === 'nodes' && is_array($value))
        {
            foreach ($value as $id)
            {
                $id = (int)$id;
                if ($id === 0)
                {
                    continue;
                }

                /** @var \XF\Repository\Node $nodeRepo */
                $nodeRepo = $this->repository('XF:Node');
                $nodes = $nodeRepo->getFullNodeListCached('search')->filterViewable();
                /** @var \XF\Entity\Node|null $node */
                $node = $nodes[$id] ?? null;
                if ($node !== null)
                {
                    $query[$key . '_' . $id] = \XF::phrase('svSearchConstraint.nodes', [
                        // todo link to node ($node->getContentUrl()), use template?
                        'node' => $node->title,
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
        $searchConstraint = $this->search_constraints;
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

        arsort($query);
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
        $query = [];
        $searchConstraint = $this->search_constraints;
        unset($searchConstraint['content'], $searchConstraint['type']);
        $this->extractUnstructuredSearchConstraint($query, $searchConstraint, '');
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

        $structure->columns['search_results']['required'] = false;
        $structure->columns['search_results']['default'] = [];

        $structure->getters['sv_structured_query'] = ['getter' => 'getSvStructuredQuery', 'cache' => true];
        $structure->getters['sv_unstructured_query'] = ['getter' => 'getSvUnstructuredQuery', 'cache' => true];
        $structure->options['svSearchImprovements'] = true;

        return $structure;
    }
}