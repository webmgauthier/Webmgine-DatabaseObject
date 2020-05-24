<?php
namespace Webmgine;

use PDO;
use stdClass;
use App\System\Exception;
use Webmgine\QueryObjects\Condition;

class DatabaseObject {

	const OPTION_PORT = 'port';
	const OPTION_PORT_DEFAULT = 3306;
	const OPTION_ENCODING = 'encoding';
	const OPTION_ENCODING_DEFAULT = 'UTF8';
	const OPTION_TABLE_PREFIX = 'tablePrefix';
	const OPTION_TABLE_PREFIX_PLACEHOLDER = 'tablePrefixPlaceholder';
	const QUERY_TYPE_SELECT = 'SELECT';
	const QUERY_TYPE_SHOW = 'SHOW';
	const QUERY_TYPE_INSERT = 'INSERT';
	const QUERY_TYPE_UPDATE = 'UPDATE';
	const QUERY_TYPE_DELETE = 'DELETE';
	const CONDITION_AND = 'AND';
	const CONDITION_OR = 'OR';

	protected string $databaseName = '';
	protected string $tablePrefix = '';
	protected string $tablePrefixPlaceholder = '#__';
	protected ?string $queryType = null;
	protected array $selectItems = [];
	protected array $insertItems = [];
	protected array $from = [
		'table' => '',
		'as' => ''
	];
	protected ?int $limit = null;
	protected string $update = '';
	protected array $conditions = [];
	protected array $values = [];
	protected $lastResult;

	public function __construct(string $host, string $name, string $user, string $pass, array $options = []) {
		$this->databaseName = $name;
		$port = (isset($options[self::OPTION_PORT]) ? $options[self::OPTION_PORT] : self::OPTION_PORT_DEFAULT);
		$encoding = (isset($options[self::OPTION_ENCODING]) ? $options[self::OPTION_ENCODING] : self::OPTION_ENCODING_DEFAULT);
		$this->pdo = new PDO(
			"mysql:host=". $host .";port=". $port .";dbname=". $this->databaseName, $user, $pass,
			[PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '. $encoding]
		);
		if (isset($options[self::OPTION_TABLE_PREFIX])) $this->tablePrefix = $options[self::OPTION_TABLE_PREFIX];
		if (isset($options[self::OPTION_TABLE_PREFIX_PLACEHOLDER])) $this->tablePrefixPlaceholder = $options[self::OPTION_TABLE_PREFIX_PLACEHOLDER];
	}

	public function addCondition(Condition $condition, ?string $chain = null): DatabaseObject {
		if (is_null($chain) || !in_array($chain, [self::CONDITION_AND, self::CONDITION_OR])) {
			$chain = self::CONDITION_AND;
		}
		$this->conditions[] = [
			'condition' => $condition,
			'chain' => $chain
		];
		return $this;
	}
	
	public function delete(Condition $condition): DatabaseObject {
		$this->newQuery(self::QUERY_TYPE_DELETE);
		return $this->addCondition($condition);
	}

	public function dump(): string {
		return $this->buildQuery();
	}

	public function execute(): DatabaseObject {
		$query = $this->buildQuery();
		$currentQuery = $this->pdo->prepare($query);
		if (!$currentQuery) throw new Exception('Failed to prepare query');
		$currentQuery->execute($this->values);
		// Check for error
		$errorInfo = $currentQuery->errorInfo();
		if (isset($errorInfo[0]) && $errorInfo[0] !== '00000') {

			// TODO: Set error
			//var_dump($errorInfo);
			//$this->state['error'] = true;
			//$this->state['messages'][] = 'Database error: '.$e;
			
		}
		$this->lastResult = $currentQuery->fetchAll(PDO::FETCH_OBJ);
		if ($this->queryType === self::QUERY_TYPE_INSERT) $this->lastInsertedId = $this->pdo->lastInsertId();
		return $this;
	}

	public function from(string $table, string $as = ''): DatabaseObject {
		$table = str_replace($this->tablePrefixPlaceholder, $this->tablePrefix, $table);
		$this->from['table'] = $table;
		$this->from['as'] = $as;
		return $this;
	}
	
	public function getDatabaseName(): string {
		return $this->databaseName;
	}
	
	public function getTableIncrementValue(string $table, bool $replacePrefix = true): int {
		$query = 'SELECT auto_increment FROM information_schema.TABLES WHERE TABLE_NAME=:tableName AND TABLE_SCHEMA=:dbName';
		if ($replacePrefix) {
			$query = str_replace($this->tablePrefixPlaceholder, $this->tablePrefix, $query);
		}
		$currentQuery = $this->pdo->prepare($query);
		$currentQuery->execute([
			'tableName' => $table,
			'dbName' => $this->getDatabaseName()
		]);
		return $currentQuery->fetchAll(\PDO::FETCH_OBJ)[0]->auto_increment;
	}

	public function getTablePrefix(): string {
		return $this->tablePrefix;
	}

	public function getTablePrefixPlaceholder(): string {
		return $this->tablePrefixPlaceholder;
	}

	public function getResult(): ?stdClass {
		if (!isset($this->lastResult) || !is_array($this->lastResult) || count($this->lastResult) < 1) return null;
		return $this->lastResult[0];
	}
	
	public function getResults(): ?array {
		if (!isset($this->lastResult) || !is_array($this->lastResult) || count($this->lastResult) < 1) return null;
		return $this->lastResult;
	}

	public function insert(array $item): DatabaseObject {
		return $this->multipleInsert([$item]);
	}

	public function into(string $table): DatabaseObject {
		return $this->from($table);
	}

	public function limit(?int $limit): DatabaseObject {
		$this->limit = $limit;
		return $this;
	}

	public function multipleInsert(array $items): DatabaseObject {
		$this->newQuery(self::QUERY_TYPE_INSERT);
		$this->insertItems = $items;
		return $this;
	}

	public function runSqlFile(string $filepath, bool $replacePrefix = true): DatabaseObject {
		$query = file_get_contents($filepath);
		return $this->runSqlText($query, $replacePrefix);
	}

	public function runSqlText(string $query, bool $replacePrefix = true): DatabaseObject {
		if ($replacePrefix) $query = str_replace($this->tablePrefixPlaceholder, $this->tablePrefix, $query);
		$currentQuery = $this->pdo->prepare($query);
		$currentQuery->execute();
		$this->lastResult = $currentQuery->fetchAll(\PDO::FETCH_OBJ);
		return $this;
	}
	
	public function select(array $items): DatabaseObject {
		$this->newQuery(self::QUERY_TYPE_SELECT);
		$this->selectItems = $items;
		return $this;
	}

	public function set(string $column, $value): DatabaseObject {
		$this->values[$column] = $value;
		return $this;
	}

	public function show(array $items): DatabaseObject {
		$this->newQuery(self::QUERY_TYPE_SHOW);
		$this->selectItems = $items;
		return $this;
	}

	public function update(string $table): DatabaseObject {
		$this->newQuery(self::QUERY_TYPE_UPDATE);
		$this->update = $table;
		return $this;
	}











	protected function buildQuery(): string {
		switch ($this->queryType) {
			case self::QUERY_TYPE_SELECT: return self::buildSelectQuery();
			case self::QUERY_TYPE_SHOW: return self::buildSelectQuery(self::QUERY_TYPE_SHOW);
			case self::QUERY_TYPE_INSERT: return self::buildInsertQuery();
			case self::QUERY_TYPE_UPDATE: return self::buildUpdateQuery();
			case self::QUERY_TYPE_DELETE: return self::buildDeleteQuery();
		}
	}

	protected function buildSelectQuery(string $selector = self::QUERY_TYPE_SELECT): string {
		$first = true;
		$query = $selector .' ';
		foreach ($this->selectItems AS $as => $item) {
			$query .= ($first ? '' : ', ') . $item . (is_int($as) ? '' : ' AS '. $as);
			$first = false;
		}
		$query .= ' FROM '. $this->from['table'] . ($this->from['as'] !== '' ?  : '');
		if ($this->from['as'] !== '') $query .= '` AS `'. $this->from['as'] .'`';
		if (count($this->conditions) > 0) {
			$query .= ' WHERE '. $this->conditionsToString();
		}
		if (!is_null($this->limit)) {
			$query .= ' LIMIT '. $this->limit;
		}
		return $query .';';
	}

	protected function buildInsertQuery(): string {
		$query = 'INSERT INTO '. $this->from['table'] .' (';
		$queryValues = ' VALUES ';
		$itemCount = 0;
		$this->values = [];
		foreach ($this->insertItems AS $insertItem) {
			$columnCount = 0;
			$queryValues .= ($itemCount < 1 ? '' : ', ') .'(';
			foreach ($insertItem AS $column => $value) {
				if ($itemCount < 1) {
					$query .= ($columnCount < 1 ? '' : ', ') . '`' . $column . '`';
				}
				$valKey = 'p'. $itemCount .'c'. $columnCount;
				$queryValues .= ($columnCount < 1 ? '' : ', ') .':'. $valKey;
				$this->values[$valKey] = $value;
				$columnCount++;
			}
			$queryValues .= ')';
			$itemCount++;
		}
		return $query .')'. $queryValues .';';
	}

	protected function buildUpdateQuery(): string {
		$query = 'UPDATE '. $this->update .' SET ';
		$first = true;
		foreach ($this->values AS $column => $value) {
			$query .= ($first ? '' : ', ') . $column .'=:'. $column;
			$first = false;
		}
		if (count($this->conditions) > 0) {
			$query .= ' WHERE '. $this->conditionsToString();
		}
		return $query;
	}

	protected function buildDeleteQuery(): string {
		$query = 'DELETE FROM '. $this->from['table'];
		if (count($this->conditions) > 0) {
			$query .= ' WHERE '. $this->conditionsToString();
		}
		return $query;
	}

	protected function conditionsToString(): string {
		$condString = '';
		$x = 0;
		foreach ($this->conditions AS $item) {
			if ($condString !== '') {
				$condString .= ' '. $item['chain'] .' ';
			}
			$condString .= '(';
			$definition = $item['condition']->getDefinition('c'. $x);
			$first = true;
			foreach ($definition['conditions'] AS $condition) {
				$condString .= (!$first ? ' '. $condition['chain'] .' ' : '') . $condition['string'];
				$first = false;
			}
			$condString .= ')';
			foreach ($definition['values'] AS $key => $value) {
				$this->values[$key] = $value;
			}
			$x++;
		}
		return $condString;
	}

	protected function newQuery(string $queryType): void {
		$this->queryType = $queryType;
		$this->selectItems = [];
		$this->insertItems = [];
		$this->from = [
			'table' => '',
			'as' => ''
		];
		$this->update = '';
		$this->limit = null;
		$this->conditions = [];
		$this->values = [];
	}
	

	




	/*
	protected $columns = [];
	protected $createTable = '';
	protected $createTablePk = '';
	protected $lastInsertedId = 0;
    protected $pdo;
	protected $prefix = '';
	protected $prefixTarget = '#__';
	protected $lastResult;
	protected $limit = 0;
	protected $orderBy = '';
	protected $selects = [];
	protected $show = [];
	protected $from = '';
	protected $insertInto = '';
	protected $update = '';
	protected $wheres = [];
	protected $joins = [];
    protected $state = [
        'error' => '',
        'messages' => []
    ];

    public function __construct(array $data){
        foreach(['host', 'name', 'user', 'pass'] AS $dataId){
            if(isset($data[$dataId])){
                continue;
            }
            $this->state['error'] = true;
            $this->state['messages'][] = 'Database '.$dataId.' information is missing';
            return;
        }
        $data['port'] = (isset($data['port'])?$data['port']:3306);
        $data['encoding'] = (isset($data['encoding'])?$data['encoding']:'UTF8');
		try{
			$this->pdo = new \PDO(
				"mysql:host=".$data['host'].";port=".$data['port'].";dbname=".$data['name'],
				$data['user'],
				$data['pass'],
				[
					\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$data['encoding']
				]
			);
		}
		catch(\PDOException $e){
            $this->state['error'] = true;
            $this->state['messages'][] = 'Database error: '.$e;
            return;
		}
		if(isset($data['prefix'])){
			$this->setPrefix($data['prefix']);
		}
	}

	public function join(array $definitions){
		$this->joins = array_merge($this->joins, $definitions);
	}

	public function addColumn(string $name, array $params):bool{
		if(!isset($params['type'])){
			return false;
		}
		if(isset($params['primaryKey']) && $params['primaryKey']){
			$this->createTablePk = $params['primaryKey'];
		}
		$this->columns[$name] = $params;
		return true;
	}

	public function createTable(string $item):bool{
		$item = trim($item);
		if($item === ''){
			return false;
		}
		$this->createTable = $item;
		return false;
	}

    public function buildQuery($data = []):string{
		$query = '';
		if(count($this->selects) > 0){
			$query = $this->buildSelectQuery($this->selects);
		}
		else if(count($this->show) > 0){
			$query = $this->buildSelectQuery($this->show, 'SHOW');
		}
		else if($this->update !== ''){
			$query = $this->buildUpdateQuery($data);
		}
		else if($this->insertInto !== ''){
			$query = $this->buildInsertQuery($data);
		}
		else if($this->createTable !== ''){
			$query = $this->buildCreateTableQuery($data);
		}
		return $query;
	}

	protected function buildCreateTableQuery($data){
		$query = 'CREATE TABLE '.$this->createTable.' (';
		$first = true;
		foreach($this->columns AS $columnName => $columnParams){
			if(!$first){
				$query .= ', ';
			}
			$query .= $columnName.' '.$columnParams['type'];
			if(isset($columnParams['unsigned']) && $columnParams['unsigned']){
				$query .= ' UNSIGNED';
			}
			if(isset($columnParams['autoIncrement']) && $columnParams['autoIncrement']){
				$query .= ' AUTO_INCREMENT';
			}
			if(isset($columnParams['primaryKey']) && $columnParams['primaryKey']){
				$query .= ' PRIMARY KEY';
			}
			$first = false;
		}
		return $query.');';
	}

	protected function buildInsertQuery($data):string{
		// INSERT
		$queryString = 'INSERT INTO '.$this->insertInto.' (';
		// SETS
		$sets = '';
		$valKeys = '';
		foreach($data AS $set => $val){
			$sets .= ($sets==''?'':',').$set;
			$valKeys .= ($valKeys==''?'':',').':'.$set;
		}
		$queryString .= $sets.') VALUES ('.$valKeys.');';
		return $queryString;
	}
	
	protected function buildSelectQuery(array $items, string $start = 'SELECT'):string{
		// SELECT
		$queryString = $start.' ';
		$first = true;
		foreach($items AS $item){
			$queryString .= ($first?'':', ').$item;
			$first = false;
		}
		// FROM
		$queryString .= ' FROM '.$this->from;
		// JOINS
		if(count($this->joins) > 0){
			foreach($this->joins AS $tableName => $definition){
				$queryString .= ' '.$definition['side'].' JOIN '.$tableName;
				if(isset($definition['as'])){
					$queryString .= ' AS '.$definition['as'];
				}
				$queryString .= ' ON '.$definition['on'];
			}
		}
		// WHERE
		if(count($this->wheres) > 0){
			$queryString .= ' WHERE ';
			$first = true;
			foreach($this->wheres AS $where){
				$queryString .= ($first?'':' '.$where['assoc'].' ').$where['value'];
				$first = false;
			}
		}
		// ORDER BY
		if($this->orderBy !== ''){
			$queryString .= ' ORDER BY '.$this->orderBy;
		}
		// LIMIT
		if($this->limit > 0){
			$queryString .= ' LIMIT '.$this->limit;
		}
		return $queryString;
	}
	
	protected function buildUpdateQuery($data):string{
		// UPDATE
		$queryString = 'UPDATE '.$this->update.' SET ';
		$first = true;
		foreach($data AS $column => $value){
			$queryString .= ($first?'':', ').'`'.$column.'`=:'.$column;
			$first = false;
		}
		// WHERE 
		if(count($this->wheres) > 0){
			$queryString .= ' WHERE ';
			$first = true;
			foreach($this->wheres AS $where){
				$queryString .= ($first?'':' '.$where['assoc'].' ').$where['value'];
				$first = false;
			}
		}
		return $queryString;
	}

	
	
	public function execute(array $data = []):bool{
		$query = $this->buildQuery($data);
		$query = str_replace($this->prefixTarget, $this->prefix, $query);
		$currentQuery = $this->pdo->prepare($query);
		if(!$currentQuery){
			return false;
		}
		$currentQuery->execute($data);
		$this->lastResult = $currentQuery->fetchAll(\PDO::FETCH_OBJ);
		if(substr($query, 0, 6) === 'INSERT'){
			$this->lastInsertedId = $this->pdo->lastInsertId();
		}
		return true;
	}
	
	public function from($item):bool{
		if(is_string($item)){
			$this->from = $item;
			return true;
		}
		if(is_array($item)){
			if(count($item) !== 1){
				return false;
			}
			foreach($item AS $id => $value){
				$this->from = $value.(is_int($id)?'':' AS '.$id);
			}
			return true;
		}
		return false;
	}

	public function getLastInsertedId():int{
		return $this->lastInsertedId;
	}
	
	public function getResult(){
		if(!isset($this->lastResult) || !is_array($this->lastResult) || count($this->lastResult) < 1){
			return null;
		}
		return $this->lastResult[0];
	}
	
	public function getResults(){
		if(!isset($this->lastResult) || !is_array($this->lastResult) || count($this->lastResult) < 1){
			return null;
		}
		return $this->lastResult;
    }
    
    public function getState(){
        return $this->state;
	}

	public function insertInto($item):bool{
		if(is_string($item)){
			$this->insertInto = $item;
			return true;
		}
		if(is_array($item)){
			if(count($item) !== 1){
				return false;
			}
			foreach($item AS $id => $value){
				$this->insertInto = $value.(is_int($id)?'':' AS '.$id);
			}
			return true;
		}
		return false;
	}

	public function limit(int $limit){
		$this->limit = $limit;
	}
	
	public function newQuery(){
		$this->selects = [];
		$this->from = '';
		$this->wheres = [];
		$this->update = '';
        $this->insertInto = '';
		$this->joins = [];
		$this->createTable = '';
		$this->createTablePk = '';
		$this->columns = [];
		$this->show = [];
		$this->limit = 0;
		$this->orderBy = '';
	}

	public function orderBy(string $orderBy){
		$this->orderBy = $orderBy;
	}

	
	
	public function select($item):bool{
		if(is_string($item)){
			$this->selects[] = $item;
			return true;
		}
		if(is_array($item)){
			if(count($item) < 1){
				return false;
			}
			foreach($item AS $id => $value){
				$this->selects[] = $value.(is_int($id)?'':' as '.$id);
			}
			return true;
		}
		return false;
	}

	public function show($item):bool{
		if(is_string($item)){
			$this->show[] = $item;
			return true;
		}
		if(is_array($item)){
			if(count($item) < 1){
				return false;
			}
			foreach($item AS $id => $value){
				$this->show[] = $value.(is_int($id)?'':' as '.$id);
			}
			return true;
		}
		return false;
	}
	
	public function setPrefix(string $prefix){
		$this->prefix = $prefix;
	}
	
	public function setPrefixTarget(string $prefixTarget){
		$this->prefixTarget = $prefixTarget;
	}
	
	public function update($item):bool{
		if(!is_string($item)){
			return false;
		}
		$this->update = $item;
		return true;
	}
	
	public function where($item, string $assoc = 'AND'):bool{
		if(is_string($item)){
			$this->wheres[] = [
				'assoc' => $assoc,
				'value' => $item
			];
			return true;
		}
		if(is_array($item)){
			if(count($item) < 1){
				return false;
			}
			foreach($item AS $value){
				$this->wheres[] = [
					'assoc' => $assoc,
					'value' => $value
				];
			}
			return true;
		}
		return false;
	}
	*/

}