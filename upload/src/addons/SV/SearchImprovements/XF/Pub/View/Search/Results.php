<?php

namespace SV\SearchImprovements\XF\Pub\View\Search;

use XF\Mvc\Renderer\Json as JsonRender;
use function assert;
use function is_callable;

/**
 * Extends \XF\Pub\View\Search\Results
 */
class Results extends XFCP_Results
{
    public function renderJson()
    {
        $jsonRender = $this->renderer;
        assert($jsonRender instanceof JsonRender);

        if (is_callable([parent::class, 'renderJson']))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $json = parent::renderJson();
        }
        else
        {

            $htmlOutput = $jsonRender->getTemplate($this->getTemplateName(), $this->getParams())->render();
            $json = [
                'status' => 'ok',
                'html' => $jsonRender->getHtmlOutputStructure($htmlOutput),
            ];
        }

        if (isset($json['html']['content']))
        {
            $templater = $jsonRender->getTemplater();
            $escape = false;
            $pageDescription = $templater->fnPageDescription($templater, $escape);
            if ($pageDescription !== '')
            {
                $description = $this->renderTemplate('public:svSearchImprov_search_results_description', [
                    'description' => $pageDescription,
                ]);

                $json['html'] = $jsonRender->getHtmlOutputStructure($description . $json['html']['content']);
            }
        }

        return $json;
    }
}