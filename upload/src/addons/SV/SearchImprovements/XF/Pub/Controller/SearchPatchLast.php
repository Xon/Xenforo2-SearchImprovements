<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use SV\SearchImprovements\XF\Search\Search as ExtendedSearcher;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;
use function array_key_exists;
use function is_array;
use function is_callable;
use function ksort;

/**
 * @Extends \XF\Pub\Controller\Search
 */
class SearchPatchLast extends XFCP_SearchPatchLast
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        if (\XF::$versionId < 2030800)
        {
            // Various endpoints on the Search controller are missing canSearch checks
            // https://xenforo.com/community/threads/in-xf-pub-controller-search-actionresults-is-missing-checks-from-actionsearch.209594/
            $visitor = \XF::visitor();
            if (!$visitor->canSearch($error))
            {
                throw $this->exception($this->noPermission($error));
            }
        }

        parent::preDispatchController($action, $params);
    }

    /**
     * search has expired, or the cached search is owned by someone else
     * Returns a 404 on error (matches XF behavior) as this prevents search bots being unhappy
     */
    protected function svSearchFromQueryData(array $searchData): AbstractReply
    {
        // a search with no criteria or no keywords is likely from a search url with no query arguments
        // this can't be sanely re-run, so return a customized 'no results' page
        if (($searchData['keywords'] ?? '') === '' && ($searchData['c'] ?? []) === [])
        {
            $reply = $this->message(\XF::phrase('no_results_found'));
            $reply->setPageParam('isExpiredSearch', true);
            return $reply;
        }

        $query = $this->prepareSearchQuery($searchData, $constraints);
        if ($query->getErrors())
        {
            return $this->error($query->getErrors(), 404);
        }
        /** @var ExtendedSearcher $searcher */
        $searcher = \XF::app()->search();
        if ($searcher->isQueryEmpty($query, $error))
        {
            return $this->error($error, 404);
        }

        return $this->runSearch($query, $constraints);
    }

    protected function svNormalizeSearchData(array $search): array
    {
        foreach ($search as $k => $v)
        {
            if (is_array($v))
            {
                $v = $this->svNormalizeSearchData($v);
                if (count($v) === 0)
                {
                    unset($search[$k]);
                    continue;
                }

                $search[$k] = $v;
            }
            else if ($v === null || $v === '')
            {
                unset($search[$k]);
            }
        }

        ksort($search);

        return $search;
    }

    /**
     * Used as a hook for getting cached search results
     */
    protected function svGetCachedSearch(int $searchId): ?\XF\Entity\Search
    {
        return Helper::find(\XF\Entity\Search::class, $searchId);
    }

    public function actionResults(ParameterBag $params)
    {
        // Re-do searches from the query data, as this gives saner experiences
        // Workaround for XF bug which allows sharing a cached member search with a guest
        // https://xenforo.com/community/threads/in-xf-pub-controller-search-actionresults-is-missing-checks-from-actionsearch.209594/
        $visitor = \XF::visitor();

        // existence + ownership checks
        $visitorId = (int)$visitor->user_id;
        $searchId = (int)$params->get('search_id');
        $search = $this->svGetCachedSearch($searchId);
        if ($search === null || $search->user_id !== $visitorId)
        {
            $searchData = $this->convertShortSearchInputNames();
            return $this->svSearchFromQueryData($searchData);
        }
        else if ($visitorId === 0)
        {
            // Determine if the search query (broadly) matches the stored query
            $searchData = $this->convertShortSearchInputNames();
            $storedArgs = $this->convertSearchToQueryInput($search);
            // normalize 'empty search', otherwise the query can be forced to be re-run unexpectedly
            if (\XF::options()->svAllowEmptySearch ?? false)
            {
                if (($searchData['keywords'] ?? '') === '*')
                {
                    $searchData['keywords'] = '';
                }
                if (($storedArgs['keywords'] ?? '') === '*')
                {
                    $storedArgs['keywords'] = '';
                }
            }

            $copy1 = $this->svNormalizeSearchData($searchData);
            $copy2 = $this->svNormalizeSearchData($storedArgs);
            if ($copy1 != $copy2) // MUST be non-exact so string juggling conversion can happen during comparison
            {
                // try to avoid too many redirects due to failed matches
                $session = \XF::session();
                if ((int)$session->get('svExpiredSearchRedirect') >= \XF::$time - 1)
                {
                    if (\XF::$developmentMode)
                    {
                        \XF::logError('Rapid expired search record:'.\var_export($copy1, true). ','.\var_export($copy2, true));
                    }

                    $session->remove('svExpiredSearchRedirect');
                    $reply = $this->message(\XF::phrase('no_results_found'));
                    $reply->setPageParam('isExpiredSearch', true);
                    return $reply;
                }
                $session->set('svExpiredSearchRedirect', \XF::$time);
                $session->save();

                return $this->svSearchFromQueryData($searchData);
            }
        }

        return parent::actionResults($params);
    }

    /**
     * XF2.3+
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpUndefinedMethodInspection
     * */
    protected function convertShortSearchNames(array $input)
    {
        $output = parent::convertShortSearchNames($input);

        // XF2.3.0 bug: https://xenforo.com/community/threads/searchcontroller-convertshortsearchnames-does-not-function-as-expected.223451/
        if (\XF::$versionId >= 2030870 && \XF::$versionId < 2030800 && array_key_exists('o', $output))
        {
            if (!array_key_exists('order', $output))
            {
                $output['order'] = $output['o'];
            }
            unset($output['o']);
        }

        return $output;
    }

    /**
     * @return AbstractReply
     * @throws ReplyException
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function actionMember()
    {
        if (is_callable([$this, 'assertNotEmbeddedImageRequest'])) // XF2.1 support
        {
            $this->assertNotEmbeddedImageRequest();
        }

        $userId = (int)$this->filter('user_id', 'uint');
        $user = $userId !== 0 ? Helper::find(UserEntity::class, $userId) : null;
        if ($user === null)
        {
            throw $this->exception($this->notFound(\XF::phrase('requested_member_not_found')));
        }

        /** @var ExtendedSearcher $searcher */
        $searcher = \XF::app()->search();

        // map old XF member search to standard search arguments
        $input = $this->filter([
            'type' => 'str',
            'content' => 'str',
            'before' => 'uint',
            'thread_type' => 'str',
            // allow standard XF search arguments
            'c' => 'array',
        ]);

        $input['c']['users'] = $user->username;

        $contentFilter = '';
        $content = $input['content'];
        $type = $input['type'];
        if ($content !== '' && $searcher->isValidContentType($content))
        {
            $contentFilter = $content;
            if ($type !== '' && $searcher->isValidContentType($type))
            {
                $input['c']['content'] = $type;
            }
        }
        else if ($type !== '' && $searcher->isValidContentType($type))
        {
            $contentFilter = $type;
        }

        if ($input['thread_type'] !== '')
        {
            $input['c']['thread_type'] = $input['thread_type'];
        }

        if ($input['before'] !== 0)
        {
            $input['c']['older_than'] = $input['before'];
        }

        $searchData = [
            'search_type' => $contentFilter,
            'c' => $input['c'],
            'order' => 'date'
        ];

        return $this->redirect($this->buildLink('search/search', null, $searchData), '');
    }
}