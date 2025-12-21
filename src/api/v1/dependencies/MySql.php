<?php
namespace Dependency;

define('DB_USERNAME', $_ENV["DB_USER"]);
define('DB_PASSWORD', $_ENV["DB_PWD"]);
define('DB_HOST', $_ENV["DB_HOST"]);
define('DB_NAME', $_ENV["DB_NAME"]);
define('DB_SALT', $_ENV["DB_SALT"]);
define('DB_SALT_ID', $_ENV["DB_SALT_ID"]);

$DBCounter = 0;
$globalDB;
 /* Handling database connection */

/*Examples
Object functions do not support encryption

$sql = new SQL();

//general format function(table, fields, condition, binding)

//INSERT returns new insert id
$id = $sql->insert('encrypt', '*name, *email ', array('ss', 'bob@gmail.com'));
$id = $sql->insertObject($table, $object)

//SELECT returns an array of results
$result = $sql->query('encrypt', 'id, *name, *email', 'email=*?', array('bob@gmail.com'));

//UPDATE : returns rowcount
$sql->update('encrypt', 'name=*?, email = *?', 'email=*?', array('cuthy', 's@g.com', 'bob@gmail.com'));
$sql->updateObject($table, $object, $idField){}


//DELETE : returns rowcount
$sql->delete('encrypt', 'email=*?', array('s@g.com'));

//ROWCOUNT
$sql->rowCount()

*/
use \PDO;

class Mysql {

    public $conn;
	  private $query;
		private $queryType;
		public $writeLog = TRUE;
	  private $selectType = "ALL";
		public $rowCount;
    public $dbName;
    public $isCaseInsensitive = false;

		public function __construct() {
   	}

    public function __sleep()
    {
       return array('dbName');
    }

    public function connect($db) {
      global $DBCounter;
			global $globalDB;

			// Set options
			$options = [
					PDO::ATTR_PERSISTENT => true,
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_EMULATE_PREPARES => false,
          PDO::ATTR_STRINGIFY_FETCHES => false
			];

      $DBCounter++;
      $this->dbName = DB_NAME;

			try {
					$this->conn = new PDO("mysql:host=".DB_HOST."; LoginTimeout=2; charset=utf8;dbname=".$db, DB_USERNAME, DB_PASSWORD, $options);
			}
			catch(\PDOException $e) {
				throw $e;
			}
			$globalDB = $this->conn;
      return $globalDB;
    }

	public function dateFromUnix($unix){
      return date('Y-m-d', $unix);
    }

    public function updateObject($table, $object, $idField = 'id'){
      $fieldString = '';
      $binding = array();
      $id = $object[$idField];
      unset($object[$idField]);
      unset($object['__index']); //a vue variable
      $comma = ' ';

      foreach ($object as $key => $value) {
        $fieldString .= $comma . $key . '=?';
        $comma = ', ';
        $binding[] = $value;
      }
      $condition = $idField . '=?';
      $binding[] = $id;

      return $this->update($table, $fieldString, $condition, $binding);
    }

		public function update($table, $fieldString, $condition = NULL, $binding = NULL){
			$this->reset();

			$this->queryType = 'UPDATE';
			$this->binding = $binding;
			$this->table = $table;
			$encrypt = $this->encrypt('?');
			$fieldString = str_replace('*?', $encrypt, $fieldString);

			if($condition){
				$condition = str_replace('*?', $encrypt, $condition);
				$condition = "WHERE ($condition)";
			}
		 	$this->query = "UPDATE $table SET $fieldString $condition";
 			return $this->execute();
		}

	 public function single($table, $fieldString, $condition = NULL, $binding = NULL){
		 $d = $this->select($table, $fieldString, $condition, $binding);
     if (isset($d[0])) {
       return $d[0];
     } else {
       return null;
     }
	 }

    public function exists($table, $condition = NULL, $binding = NULL, $isCaseInsensitive = FALSE){

      $d = $this->select($table, '*', $condition, $binding, $isCaseInsensitive);
      if (isset($d[0]))
      {
        return true;
      } else {
        return false;
      }

    }

    public function selectFirst($table, $fieldString, $condition = NULL, $binding = NULL, $isCaseInsensitive = FALSE) {
      $result = $this->select($table, $fieldString, $condition, $binding, $isCaseInsensitive);
      if (isset($result[0])) return $result[0];
      return null;
    }

	 	public function select($table, $fieldString, $condition = NULL, $binding = NULL, $isCaseInsensitive = FALSE){

			$this->reset();
			$this->queryType = 'SELECT';
			$this->isCaseInsensitive = $isCaseInsensitive;
			$this->binding = $binding;
      $this->table = $table;

			if($isCaseInsensitive){
				foreach($this->binding as &$bind){
					$bind = strtolower($bind);
				}
			}

      if ($fieldString !== '*') {

        //add encryption gubbins for the field string
        $fieldString = str_replace(' as ', '@', $fieldString);
  			$fieldString = str_replace(' ', '', $fieldString);
        $fieldString = str_replace('@', ' as ', $fieldString); //allows a field alias to be used.

  			$explode = explode(',', $fieldString);
  			$flag = 0;
  			foreach($explode as &$item){
  				if(substr($item,0,1)=='*'){
  					$item = substr($item,1);
  					$item = $this->encrypt($item, TRUE);
  				}
  			}
  			$fieldString = implode(', ', $explode);
      }

		 	if($condition){
				$encrypt = $this->encrypt('?');
				$condition = str_replace('*?', $encrypt, $condition);
				$condition = "WHERE $condition";
			}

		 	$this->query = "SELECT $fieldString from $table $condition";
 			return $this->execute();

		}

	  //untested so may not work
		function tableExists($table) {

			// Try a select statement against the table
			// Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
			try {
					$result = $this->conn->query("SELECT 1 FROM $table LIMIT 1");
			} catch (Exception $e) {
					// We got an exception == table not found
					return FALSE;
			}

			// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
			return $result !== FALSE;
	}

		//allows an arbitrary query. Doesn't allow encryption
		public function query($query, $binding = NULL, $isCaseInsensitive = FALSE){
			$this->reset();

			$this->queryType = 'SELECT';

			$this->isCaseInsensitive = $isCaseInsensitive;

			if($binding){
				$this->binding = $binding;

				foreach($this->binding as &$bind){

					$bind = strtolower($bind);

				}
			}else{

				$this->binding = array();

			}

		 	$this->query = $query;


 			return $this->execute();

		}

			//doesn't automatically add a WHERE statements. Allows for things like joings
			public function select_raw($table, $fieldString, $condition = NULL, $binding = NULL){

			$this->reset();

			$this->queryType = 'SELECT';


			$this->binding = $binding;
			$this->table = $table;


			//add encryption gubbins for the field string
			$fieldString = str_replace(' ', '', $fieldString);
			$explode = explode(',', $fieldString);
			$flag = 0;

			foreach($explode as &$item){

				if(substr($item,0,1)=='*'){

					$item = substr($item,1);
					$item = $this->encrypt($item, TRUE);

				}

			}

			$fieldString = implode(', ', $explode);



		 	if($condition){
				$encrypt = $this->encrypt('?');
				$condition = str_replace('*?', $encrypt, $condition);
			}


		 	$this->query = "SELECT $fieldString from $table $condition";



 			return $this->execute();

		}


		public function delete($table, $condition = NULL, $binding = NULL){

			$this->reset();

			if($condition==NULL){return NULL;} //just in case deletes all

			$this->queryType = 'DELETE';
			$this->binding = $binding;
			$this->table = $table;


		 	if($condition){
				$encrypt = $this->encrypt('?');
				$condition = str_replace('*?', $encrypt, $condition);
				$condition = "WHERE ($condition)";
			}


		 	$this->query = "DELETE from $table $condition";

 			return $this->execute();

		}

    public function insertObject($table, $object){
      $fieldString = '';
      $binding = array();
      $comma = '';
      foreach ($object as $key => $value) {
        $fieldString .= $comma . ' ' . $key;
        $comma = ',';
        $binding[] = $value;
      }
      return $this->insert($table, $fieldString, $binding);
    }

		public function insert($table, $fieldString, $binding = NULL){
			$this->reset();

			$this->queryType = 'INSERT';
			$this->binding = $binding;
			$this->table = $table;

			$fieldString = str_replace(' ', '', $fieldString);
			$explode = explode(',', $fieldString);

			$qMarks = ''; $flag = 0;



			foreach($explode as &$item){

				if(substr($item,0,1)=='*'){

					$item = substr($item,1);
					$mark = $this->encrypt('?');

				}else{

					$mark = '?';

				}

				if($flag ==0){
					$qMarks = $mark;
					$flag = 1;
				}else{

					$qMarks .= ", $mark";
				}

			}

			$fieldString = implode(',', $explode);

			$this->query = "INSERT INTO $table($fieldString) values($qMarks)";
			return $this->execute();


		}

		private function xss_Safe($binding){

			//disables as using escaping via the underscore templates
			return $binding;

			foreach($binding as &$item){

				foreach($item as &$data){

					!is_numeric($data) ? $data = htmlspecialchars($data) : null;
				}

			}

			return $binding;

		}

		private function encrypt($name, $DECRYPT = NULL){

// 			if($name != '?'){ $name = "'$name'";}

			if($DECRYPT == NULL){

					if($this->isCaseInsensitive == true){

							return "lower(AES_ENCRYPT($name,UNHEX('". DB_SALT ."')))";}

					else{

						return "AES_ENCRYPT($name,UNHEX('". DB_SALT ."'))";

					}

			}

			else {

					return "AES_DECRYPT($name,UNHEX('". DB_SALT ."')) AS $name";

			}

		}


	  public function execute(){
			if($this->writeLog){$this->lastquery = $this->query;}
			$this->STH = $this->conn->prepare($this->query);
			$this->STH->execute($this->binding);

			$return = NULL;
			switch($this->queryType){

				case 'SELECT':

					$this->STH->setFetchMode(PDO::FETCH_ASSOC);

					$return = $this->xss_Safe($this->STH->fetchAll());

					$this->rowCount = $this->rowCount();

					break;


				case 'INSERT':

						// if($this->writeLog) {$return = $this->conn->lastInsertId();}
            $return = $this->conn->lastInsertId();
            break;

				case 'DELETE' :

						$return = $this->rowCount(); break;

				case 'UPDATE' :

						$return = $this->rowCount(); break;


			}

			if($this->writeLog){$this->log();}

			return $return;

		}


	  public function rowCount(){

			return $this->STH->rowCount();

		}

	  private function reset(){

			$this->query = '';
			$this->queryType = '';


		}

		private function log(){
			return;
			global $user_id;

			$table = $this->table;
			$type = $this->queryType;
			$time = date("d/m/y : H:i:s", time());
			$query = $this->query;
			$ip = $_SERVER['REMOTE_ADDR'];

			$data = array($table, $type, $time, $query, $user_id, $ip);

// 			$sql = new SQL();

			$this->writeLog = FALSE;
			$this->insert('l_db', "tbl, type, time, query, user, *ip", $data);
			$this->writeLog = TRUE;



		}


}

?>
