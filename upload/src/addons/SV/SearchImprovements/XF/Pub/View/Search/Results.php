<?php

namespace SV\SearchImprovements\XF\Pub\View\Search;

use XF\Mvc\Renderer\Json as JsonRender;
use function is_callable;

/**
 * @Extends \XF\Pub\View\Search\Results
 */
class Results extends XFCP_Results
{
    public function renderJson()
    {
        /** @var JsonRender $jsonRender */
        $jsonRender = $this->renderer;

        /** @noinspection PhpUndefinedMethodInspection */
        $output = is_callable([parent::class, 'renderJson'])
            ? parent::renderJson()
            : ['status' => 'ok', 'html' => null];
        If (($output['html'] ?? null) === null)
        {
            $htmlOutput = $jsonRender->getTemplate($this->getTemplateName(), $this->getParams())->render();
            $output['html'] = $jsonRender->getHtmlOutputStructure($htmlOutput);
        }

        $content = $output['html']['content'] ?? null;
        if ($content !== null)
        {
            $templater = $jsonRender->getTemplater();
            $escape = false;
            $pageDescription = $templater->fnPageDescription($templater, $escape);
            if ($pageDescription !== '')
            {
                $description = $this->renderTemplate('public:svSearchImprov_search_results_description', [
                    'description' => $pageDescription,
                ]);

                $output['html'] = $jsonRender->getHtmlOutputStructure($description . $content);
            }
        }

        return $output;
    }
}