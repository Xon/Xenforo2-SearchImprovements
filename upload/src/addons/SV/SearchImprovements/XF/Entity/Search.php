<?php

namespace SV\SearchImprovements\XF\Entity;

use SV\RedisCache\Repository\Redis as RedisRepo;
use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Entity\Search as SearchEntity;
use SV\SearchImprovements\XF\Repository\Search as SearchRepo;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use XF\Util\Arr;
use function array_diff;
use function array_key_exists;
use function array_merge;
use function array_merge_recursive;
use function arsort;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function memory_get_peak_usage;
use function microtime;
use function round;

/**
 * @property array|null $sv_debug_info
 * @property-read array<string,\XF\Phrase> $sv_structured_query
 */
class Search extends XFCP_Search
{
    /**
     * Special case for items pulled to the very start of the search term results
     * Overrides the $svDateConstraint/$svUserConstraint sort order
     * The order of items in this list is used in sortSearchQueryForDisplay
     * @var string[]
     */
    protected $svSortFirst = [
    ];
    /**
     * The order of items in this list is used in sortSearchQueryForDisplay
     * @var string[]
     */
    protected $svDateConstraint = [
        'newer_than',
        'older_than',
    ];
    /**
     * The order of items in this list is used in sortSearchQueryForDisplay
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
     * The order of items in this list is used in sortSearchQueryForDisplay
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
        $searchRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Search::class);
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
            if (!is_array($value))
            {
                assert(is_string($value));
                $usernames = Arr::stringToArray($value, '/,\s*/');
            }
            else
            {
                $usernames = $value;
            }

            $templater = \XF::app()->templater();
            /** @var \XF\Repository\User $userRepo */
            $userRepo = \SV\StandardLib\Helper::repository(\XF\Repository\User::class);
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
            $value = implode(', ', $formattedUsernames);

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
            $thread = \SV\StandardLib\Helper::find(\XF\Entity\Thread::class, (int)$value);
            if (($thread instanceof \XF\Entity\Thread) && $thread->canView())
            {
                return \XF::phrase('svSearchConstraint.thread_with_title', [
                    'url'   => \XF::app()->router('public')->buildLink('threads', $thread),
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
            $nodeRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Node::class);
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
                    if (\XF::$versionId < 2020000)
                    {
                        $nodeTypeInfo = $node->getNodeTypeInfo();
                        $url = $nodeTypeInfo ? \XF::app()->router('public')->buildLink($nodeTypeInfo['public_route'], $this) : '';
                        $title = $node->title;
                    }
                    else
                    {
                        $url = $node->getContentUrl();
                        $title = $node->getContentTitle();
                    }

                    $query[$key . '_' . $id] = \XF::phrase('svSearchConstraint.nodes', [
                        'url' => $url,
                        'node' => $title,
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

    protected function sortSearchQueryForDisplay(array $unsortedQuery): array
    {
        if (count($unsortedQuery) < 2)
        {
            return $unsortedQuery;
        }

        // urlConstraints appears weirdly sorted, so apply some basic sorting to try to make this look good
        $query = [];
        $lastItems = [];
        foreach ($this->svSortLast as $key)
        {
            if (array_key_exists($key, $unsortedQuery))
            {
                $lastItems[$key] = $unsortedQuery[$key];
                unset($unsortedQuery[$key]);
            }
        }
        foreach ($this->getConstraintSortLists() as $list)
        {
            foreach ($list as $key)
            {
                if (array_key_exists($key, $unsortedQuery))
                {
                    $query[$key] = $unsortedQuery[$key];
                    unset($unsortedQuery[$key]);
                }
            }
        }
        foreach ($unsortedQuery as $key => $value)
        {
            $query[$key] = $value;
        }
        foreach ($lastItems as $key => $value)
        {
            $query[$key] = $value;
        }

        return $query;
    }

    protected function getSvStructuredQuery(): array
    {
        $query = [];
        $searchConstraints = $this->search_constraints;

        $typeFilter = $searchConstraints['content'] ?? $searchConstraints['type'] ?? null;
        $containerOnly = Helper::isAddOnActive('SV/ElasticSearchEssentials') && ($searchConstraints['container_only'] ?? false);
        $addContentTypeTerm = $this->search_type !== '' && !$this->search_grouping;
        if ($containerOnly && $typeFilter !== null || $addContentTypeTerm && $containerOnly)
        {
            unset($searchConstraints['container_only']);
        }
        unset($searchConstraints['content'], $searchConstraints['type']);
        $this->extractStructuredSearchConstraint($query, $searchConstraints, '');
        foreach ($query as &$queryPhrase)
        {
            if ($queryPhrase instanceof \XF\Phrase)
            {
                $queryPhrase = $queryPhrase->render('raw');
            }
        }
        unset($queryPhrase);
        $query = $this->sortSearchQueryForDisplay($query);

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
                    $query['search-content search-content--' . $this->search_type] = \XF::phrase('svSearchClauses.content_type', [
                        'contentTypes' => implode(', ', $types),
                    ])->render('raw');
                }
            }
        }
        // add sort-by clause
        $phrase = $this->getSearchOrderPhrase($this->search_order);
        if ($phrase !== null)
        {
            $query['search-order search-order--'.$this->search_order] = \XF::phrase('svSearchClauses.order_by', [
                'order' => $phrase
            ])->render('raw');
        }
        if ($this->search_grouping)
        {
            $contentType = $this->getContainerContentType() ?? $this->search_type;
            $value = \XF::app()->getContentTypePhrase($contentType, true);
            $query['search-clause search-clause--group_by'] = \XF::phrase('svSearchClauses.group_by', [
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
            $query['svSearchOrder.'.$this->search_order] = 'svSearchOrder.'.$this->search_order;
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


    protected function getSearchDebugRequestStateSnapshot(): array
    {
        $debug = [
            'time' => round(microtime(true) - \XF::app()->container('time.granular'), 4),
            'queries' => $this->db()->getQueryCount(),
            'memory' => round(memory_get_peak_usage() / 1024 / 1024, 2)
        ];

        if (Helper::isAddOnActive('SV/RedisCache'))
        {
            $mainConfig = \XF::app()->config()['cache'];
            $contexts = [];
            $contexts[''] = $mainConfig;
            if (isset($mainConfig['context']))
            {
                $contexts = $contexts + $mainConfig['context'];
            }
            foreach ($contexts as $contextLabel => $config)
            {
                $cache = RedisRepo::get()->getRedisConnector($contextLabel, false);
                if ($cache !== null)
                {
                    $stats = $cache->getRedisStats();
                    if (!isset($debug['cache']['get'], $debug['cache']['set']))
                    {
                        $debug['cache']['get'] = 0;
                        $debug['cache']['set'] = 0;
                    }
                    $debug['cache']['get'] += (int)($stats['gets'] ?? 0);
                    $debug['cache']['set'] += (int)($stats['sets'] ?? 0);
                }
            }
        }

        return $debug;
    }

    protected function getSearchDebugSummary(SearchEntity $search): array
    {
        $arr = [
            'summary' => [],
        ];

        foreach ($search->search_results as $match)
        {
            $contentType = $match[0] ?? null;
            if (!is_string($contentType))
            {
                throw new \LogicException('Unknown return contents from Search::search_results');
            }
            if (!isset($arr['summary'][$contentType]['php']))
            {
                $arr['summary'][$contentType]['php'] = 0;
            }
            $arr['summary'][$contentType]['php'] += 1;
        }

        return $arr;
    }

    protected function logSearchDebugInfo(): void
    {
        // Log debug information if required.
        // This requires setup inside runSearch as by the time setupFromQuery is called the search DSL and other bits can't be collected
        $capturedSearchDebugInfo = Globals::$capturedSearchDebugInfo ?? [];
        // convert empty array to null
        if ($capturedSearchDebugInfo !== [])
        {
            $capturedSearchDebugInfo = array_merge_recursive(
                $capturedSearchDebugInfo,
                $this->getSearchDebugRequestStateSnapshot(),
                $this->getSearchDebugSummary($this)
            );

            $this->sv_debug_info = $capturedSearchDebugInfo;
        }
    }

    protected function _preSave()
    {
        parent::_preSave();

        if ($this->isInsert() && $this->sv_debug_info === null)
        {
            // setupFromQuery is called before the search is executed, while the save is alled after the search happens
            $this->logSearchDebugInfo();
        }
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_debug_info'] = ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null];
        $structure->columns['search_results']['required'] = false;
        $structure->columns['search_results']['default'] = [];

        $structure->getters['sv_structured_query'] = ['getter' => 'getSvStructuredQuery', 'cache' => true];
        $structure->getters['sv_unstructured_query'] = ['getter' => 'getSvUnstructuredQuery', 'cache' => true];
        $structure->options['svSearchImprovements'] = true;

        return $structure;
    }
}