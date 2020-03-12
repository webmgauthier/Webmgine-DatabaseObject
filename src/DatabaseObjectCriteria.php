<?php
namespace Webmgine;
use Webmgine\DatabaseObject;

class DatabaseObjectCriteria {

    const EQUAL = '=';
    const NOT_EQUAL = '!=';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';

    protected array $conditions = [];
    protected array $values = [];

    public function add(string $condition, string $chain = DatabaseObject::CONDITION_AND): void {
        $this->conditions[] = [
            'chain' => $chain,
            'condition' => $condition
        ];
    }

    public function addColumnValue(string $column, string $key, $value, string $comparator = self::EQUAL, string $chain = DatabaseObject::CONDITION_AND): void {
        $isInCondition = in_array($comparator, [self::IN, self::NOT_IN]);
        $this->values[$key] = $value;
        $this->add($column . $comparator .':'. ($isInCondition ? '(' : '') . $key . ($isInCondition ? ')' : ''), $chain);
    }

    public function getConditions(): array {
        return $this->conditions;
    }

    public function getValue(string $key) {
        return (isset($this->values[$key]) ? $this->values[$key] : null);
    }

    public function getValues(): array {
        return $this->values;
    }
}