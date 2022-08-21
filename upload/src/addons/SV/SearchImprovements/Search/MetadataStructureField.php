<?php

namespace SV\SearchImprovements\Search;

class MetadataStructureField
{
    /** @var MetadataStructureEx */
    public $structure;
    /** @var string */
    public $field;

    public function __construct(MetadataStructureEx $structure, string $field)
    {
        $this->structure = $structure;
        $this->field = $field;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    protected function patchField(string $field, $value): self
    {
        $config = $this->structure->getField($this->field);
        $config[$field] = $value;
        $this->structure->updateField($this->field, $config);

        return $this;
    }

    public function skipWhiteSpaceFromExact(bool $value = true): self
    {
        return $this->patchField('stripe-whitespace-from-exact', $value);
    }

    public function skipRewrite(bool $value = true): self
    {
        return $this->patchField('skip-rewrite', $value);
    }
}