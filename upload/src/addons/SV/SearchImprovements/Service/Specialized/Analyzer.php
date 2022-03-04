<?php

namespace SV\SearchImprovements\Service\Specialized;

use XFES\Service\Optimizer;

/**
 * Extends \XFES\Service\Analyzer
 */
class Analyzer extends \XFES\Service\Analyzer
{
    /** @var array */
    protected $ngramDefault = [
        'type' => 'edgeNGram',
        'min_gram'    => 2,
        'max_gram'    => 25,
        'token_chars' => [
            'letter',
            'digit',
            'punctuation',
            'symbol',
        ]
    ];

    /** @var int */
    protected $minNgramSize = 1;
    /** @var int */
    protected $maxNgramSize = 32;
    /** @var string */
    protected $singleType;

    public function __construct(\XF\App $app, string $singleType, \XFES\Elasticsearch\Api $es)
    {
        $this->singleType = $singleType;
        parent::__construct($app, $es);

        if ($this->getEsApi()->majorVersion() >= 7)
        {
            $this->ngramDefault['type'] = 'edge_ngram';
        }
    }

    public function getNgramDefault() : array
    {
        return $this->ngramDefault;
    }

    public function getAnalyzerFromConfig(array $config): array
    {
        $result = parent::getAnalyzerFromConfig($config);

        $filter = [];
        $filter[] = 'lowercase';

        $doFolding = $config['ascii_folding'] ?? true;
        if ($doFolding)
        {
            $filter[] = 'asciifolding';
        }

        if (isset($result['analysis']['filter']['xf_stemmer']['language']))
        {
            $filter[] = 'xf_stemmer';
        }

        // always generate analyzer configuration, even if it isn't used
        $nearExactMatchAnalyzer = $result['analysis']['analyzer']['default'];
        foreach ($nearExactMatchAnalyzer['filter'] as $key => $value)
        {
            if ($value === 'xf_stop' || $value === 'xf_stemmer')
            {
                unset($nearExactMatchAnalyzer['filter'][$key]);
            }
        }
        $result['analysis']['analyzer']['sv_near_exact_analyzer'] = $nearExactMatchAnalyzer;

        $autoCompleteFilter = $filter;
        $autoCompleteFilter[] = 'sv_ngram_filter';
        $result['analysis']['analyzer']['sv_ngram_analyzer'] = [
            'type'      => 'custom',
            'tokenizer' => 'standard',
            'filter'    => $autoCompleteFilter,
        ];
        $result['analysis']['filter']['sv_ngram_filter'] = $this->getNgramFilter($config['sv_ngram'] ?? []);

        if ($this->getEsApi()->majorVersion() >= 7)
        {
            $result['index']['max_ngram_diff'] = $this->maxNgramSize;
        }

        return $result;
    }

    public function getDefaultConfig(): array
    {
        $defaultConfig = parent::getDefaultConfig();

        if (!\array_key_exists('sv_ngram', $defaultConfig))
        {
            $defaultConfig['sv_ngram'] = $this->getNgramDefault();
        }

        return $defaultConfig;
    }

    public function getConfigFromAnalyzer(array $analysis): array
    {
        $currentConfig = parent::getConfigFromAnalyzer($analysis);

        $ngramFilter = $analysis['filter']['sv_ngram_filter'] ?? null;
        if (\is_array($ngramFilter))
        {
            $currentConfig['sv_ngram'] = $this->getNgramFilter($ngramFilter);
        }

        return $currentConfig;
    }

    public function getNgramFilter(array $ngramVars) : array
    {
        $defaultNgram = $this->getNgramDefault();
        $ngramVars = \array_replace($defaultNgram, $ngramVars);

        $sizeSanitizer = function ($size, int $default) {
            $finalSize = (int)$size;

            if ($finalSize < $this->minNgramSize || $finalSize > $this->maxNgramSize)
            {
                return $default;
            }

            return $finalSize;
        };

        $ngramVars['min_gram'] = $sizeSanitizer((int)$ngramVars['min_gram'], (int)$defaultNgram['min_gram']);
        $ngramVars['max_gram'] = $sizeSanitizer((int)$ngramVars['max_gram'], (int)$defaultNgram['max_gram']);

        return $ngramVars;
    }

    public function updateAnalyzer(array $config)
    {
        $settings = $this->getAnalyzerFromConfig($config);

        if (!$this->es->indexExists())
        {
            /** @var Optimizer $optimizer */
            $optimizer = $this->service('SV\SearchImprovements:Specialized\Optimizer', $this->singleType, $this->es);
            $optimizer->optimize($settings, true);

            return;
        }

        parent::updateAnalyzer($config);
    }
}