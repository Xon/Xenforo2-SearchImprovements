<?php

namespace SV\SearchImprovements\Behavior;

use XF\Behavior\Indexable;

class SpecializedIndexable extends Indexable
{
    protected function getDefaultConfig(): array
    {
        return [
            'content_type' => null,
            'checkForUpdates' => null,
        ];
    }

    public function contentType(): string
    {
        return $this->config['content_type'] ?? parent::contentType();
    }
}