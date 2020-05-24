<?php
namespace Webmgine\QueryObjects;
use Webmgine\DatabaseObject;

class Condition {

    const CONDITION_TYPE_COLUMN_VALUE = 'columnValue';
    const CONDITION_TYPE_STRING = 'string';
    const EQUAL = '=';
    const NOT_EQUAL = '!=';
	const GREATER = '>';
	const GREATER_EQUAL = '>=';
    const LOWER = '<';
	const LOWER_EQUAL = '<=';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';

    protected array $columnValueConditions = [];
    protected array $conditions = [];
    protected array $values = [];

    public function addStringCriteria(string $string, array $values = [], string $comparator = self::EQUAL, string $chain = DatabaseObject::CONDITION_AND): void {
        $this->conditions[] = [
            'type' => self::CONDITION_TYPE_STRING,
            'string' => $string,
            'values' => $values,
            'comparator' => $comparator,
            'chain' => $chain
        ];
        
    }

    public function addColumnValueCriteria(string $column, $value, string $comparator = self::EQUAL, string $chain = DatabaseObject::CONDITION_AND): void {
        $this->conditions[] = [
            'type' => self::CONDITION_TYPE_COLUMN_VALUE,
            'column' => (in_array(strtolower($column), ['group']) ? '`'. $column .'`' : $column),
            'value' => $value,
            'comparator' => $comparator,
            'chain' => $chain
        ];
    }

    public function getDefinition(string $paramsPrefix = ''): array {
        $conditions = $values = [];
        foreach ($this->conditions AS $condition) {
            switch ($condition['type']) {
                
                case self::CONDITION_TYPE_STRING:
                    $conditions[] = [
                        'string' => $condition['string'],
                        'chain' => $condition['chain']
                    ];
                    foreach ($condition['values'] AS $key => $val) {
                        $values[$key] =  $val;
                    }
                    break;

                case self::CONDITION_TYPE_COLUMN_VALUE:
                    $key = $paramsPrefix .'p'. count($conditions);
                    $isInCondition = in_array($condition['comparator'], [self::IN, self::NOT_IN]);
                    $conditions[] = [
                        'string' => $condition['column'] . $condition['comparator'] .':'. ($isInCondition ? '(' : '') . $key . ($isInCondition ? ')' : ''),
                        'chain' => $condition['chain']
                    ];
                    $values[$key] = $condition['value'];
                    break;

            }
        }
        return [
            'conditions' => $conditions,
            'values' => $values
        ];
    }
}