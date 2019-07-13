<?php
namespace Webmgine;

class DatabaseObject{

	protected $columns = [];
	protected $createTable = '';
	protected $createTablePk = '';
    protected $pdo;
	protected $prefix = '';
	protected $prefixTarget = '#__';
	protected $lastResult;
	protected $selects = [];
	protected $show = [];
	protected $from = '';
	protected $insertInto = '';
	protected $update = '';
	protected $wheres = [];
	protected $sets = [];
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
			$query = $this->buildUpdateQuery();
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
		return $queryString;
	}
	
	protected function buildUpdateQuery():string{
		// UPDATE
		$queryString = 'UPDATE '.$this->update.' SET ';
		$first = true;
		foreach($this->sets AS $set){
			$queryString .= ($first?'':', ').$set;
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

	public function deleteFrom(string $table, array $wheres, array $data = []):bool{
		$query = 'DELETE FROM '.$table.' WHERE';
		foreach($wheres AS $where){
			$query .= ' '.$where;
		}
		$query = str_replace($this->prefixTarget, $this->prefix, $query);
		$currentQuery = $this->pdo->prepare($query);
		if(!$currentQuery){
			return false;
		}
		$currentQuery->execute($data);
		return true;
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
	
	public function newQuery(){
		$this->selects = [];
		$this->from = '';
		$this->wheres = [];
		$this->update = '';
        $this->insertInto = '';
		$this->sets = [];
		$this->joins = [];
		$this->createTable = '';
		$this->createTablePk = '';
		$this->columns = [];
		$this->show = [];
	}

	public function runSqlFile(string $filepath, bool $replacePrefix = true){
		$query = file_get_contents($filepath);
		$this->runSqlText($query, $replacePrefix);
	}

	public function runSqlText(string $query, bool $replacePrefix = true){
		$query = str_replace($this->prefixTarget, $this->prefix, $query);
		$currentQuery = $this->pdo->prepare($query);
		$currentQuery->execute();
		$this->lastResult = $currentQuery->fetchAll(\PDO::FETCH_OBJ);
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
	
	public function set($item){
		if(is_string($item)){
			$this->sets[] = $item;
			return true;
		}
		if(is_array($item)){
			if(count($item) < 1){
				return false;
			}
			foreach($item as $i){
				$this->sets[] = $i;
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
}