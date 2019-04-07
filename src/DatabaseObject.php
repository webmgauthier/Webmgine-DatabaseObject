<?php
namespace Webmgine;

class DatabaseObject{

    protected $pdo;
	protected $prefix = '';
	protected $prefixTarget = '#__';
	protected $lastResult;
	protected $selects = [];
	protected $from = '';
	protected $insertInto = '';
	protected $update = '';
	protected $wheres = [];
    protected $sets = [];
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

    public function buildQuery($data = []):string{
		$query = '';
		if(count($this->selects) > 0){
			$query = $this->buildSelectQuery();
		}
		if($this->update !== ''){
			$query = $this->buildUpdateQuery();
		}
		if($this->insertInto !== ''){
			$query = $this->buildInsertQuery($data);
		}
		return $query;
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
	
	protected function buildSelectQuery():string{
		// SELECT
		$queryString = 'SELECT ';
		$first = true;
		foreach($this->selects AS $select){
			$queryString .= ($first?'':', ').$select;
			$first = false;
		}
		// FROM
		$queryString .= ' FROM '.$this->from;
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