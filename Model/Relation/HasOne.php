<?php

namespace SwiftPHP\Model\Relation;

use SwiftPHP\Model\Model;

class HasOne extends Relation
{
    public function getResults(): ?Model
    {
        $foreignValue = $this->parent->getAttribute($this->localKey);
        if ($foreignValue === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->foreignKey, $foreignValue)
            ->first();
    }
}
