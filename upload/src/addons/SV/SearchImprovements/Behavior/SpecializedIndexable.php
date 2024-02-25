<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\Behavior;

use XF\Behavior\Indexable;
use function is_string, is_callable;

class SpecializedIndexable extends Indexable
{
    protected function getDefaultConfig(): array
    {
        return [
            'content_type' => null,
            'checkForUpdates' => null,
        ];
    }

    protected function verifyConfig()
    {
        if (!$this->contentType())
        {
            throw new \LogicException('Structure must provide a contentType value');
        }

        if ($this->config['checkForUpdates'] === null && !is_callable([$this->entity, 'requiresSpecializedSearchIndexUpdate']))
        {
            throw new \LogicException('If checkForUpdates is null/not specified, the entity must define requiresSpecializedSearchIndexUpdate');
        }
    }

    protected function requiresIndexUpdate()
    {
        if ($this->entity->isInsert())
        {
            return true;
        }

        $checkForUpdates = $this->config['checkForUpdates'];

        if ($checkForUpdates === null)
        {
            // method is verified above
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            return $this->entity->requiresSpecializedSearchIndexUpdate();
        }
        else if (is_array($checkForUpdates) || is_string($checkForUpdates))
        {
            return $this->entity->isChanged($checkForUpdates);
        }
        else
        {
            return $checkForUpdates;
        }
    }

    public function contentType(): string
    {
        $contentType = $this->config['content_type'] ?? parent::contentType();
        if (!is_string($contentType) && is_callable($contentType))
        {
            $contentType = $contentType();
        }

        return $contentType;
    }
}