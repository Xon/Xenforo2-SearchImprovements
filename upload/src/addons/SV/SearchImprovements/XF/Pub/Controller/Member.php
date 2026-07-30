<?php

namespace SV\SearchImprovements\XF\Pub\Controller;

use XF\ControllerPlugin\SearchPlugin;
use XF\Mvc\ParameterBag;

/**
 * @extends \XF\Pub\Controller\Member
 */
class Member extends XFCP_Member
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function actionRecentContent(ParameterBag $params)
    {
        $onlyShowPostsInRecentContent = \XF::options()->svOnlyShowPostsInRecentContent ?? false;
        if (!$onlyShowPostsInRecentContent && \XF::$versionId >= 2030800)
        {
            $this->assertNotEmbeddedImageRequest();
            $user = $this->assertViewableUser($params->get('user_id'));
            $input = [
                //'search_type' => 'post',
                'c' => [
                    'users' => $user->username,
                ],
                'order' => 'date',
            ];

            $searchPlugin = $this->plugin(SearchPlugin::class);
            $query = $searchPlugin->prepareSearchQuery($input, $urlConstraints);
            $searchPlugin->assertValidSearchQuery($query);

            $searcher = $this->app->search();

            $results = $this->app()->search()->search($query, 15);
            $resultSet = $searcher->getResultSet($results);

            $results = $searcher->wrapResultsForRender($resultSet);
            $resultCount = $resultSet->countResults();

            $viewParams = [
                'user' => $user,
                'results' => $results,
                'resultCount' => $resultCount,
            ];
            return $this->view('XF:Member\RecentContent', 'member_recent_content', $viewParams);
        }
        else if ($onlyShowPostsInRecentContent && \XF::$versionId < 2030800)
        {
            $this->assertNotEmbeddedImageRequest();
            $user = $this->assertViewableUser($params->get('user_id'));

            $searcher = $this->app->search();
            $query = $searcher->getQuery();

            // enforce only posts/threads
            $postHandler = $searcher->handler('post');
            if ($postHandler !== null)
            {
                $query->forTypeHandlerBasic($postHandler);
            }

            $query->byUserId($user->user_id)
                  ->orderedBy('date');

            $resultSet = $searcher->getResultSet($searcher->search($query));
            $resultSet->limitResults(15);

            $results = $searcher->wrapResultsForRender($resultSet);
            $resultCount = $resultSet->countResults();

            $viewParams = [
                'user' => $user,
                'results' => $results,
                'resultCount' => $resultCount,
            ];
            return $this->view('XF:Member\RecentContent', 'member_recent_content', $viewParams);
        }


        return parent::actionRecentContent($params);
    }
}