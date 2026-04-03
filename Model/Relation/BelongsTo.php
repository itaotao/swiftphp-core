<?php

namespace SwiftPHP\Model\Relation;

use SwiftPHP\Model\Model;

class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        $foreignValue = $this->parent->getAttribute($this->foreignKey);
        if ($foreignValue === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->localKey, $foreignValue)
            ->first();
    }
}
