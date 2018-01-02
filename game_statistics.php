<?php date_default_timezone_set('Europe/Stockholm'); ?>
<?php
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
	header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token');

	$game = $_GET['game'];
	if (preg_match('/[^_a-zA-Z0-9]/', $game)) {
		die(1);
	}

	$content = trim(file_get_contents("php://input"));
	$data = json_decode($content, true);
	
	class query {
		public $q;
		public $result;
		private $conHandle = null;

		public function __construct($q) {
				$this->q = $q;
				$this->connect();
		}

		public function error() {
				return pg_last_error($this->conHandle);
		}

		private function connect() {
				// [postgres@machine~] createuser --interactive
				// CREATE DATABASE DBName OWNER DBUser
				// ALTER USER DBUser WITH PASSWORD 'DBPassword';
				$this->conHandle = pg_connect("host=localhost user=DBUser password=DBPassword dbname=DBName");
				if (!$this->conHandle) {
						error_log("Connect failed: %s\n", $this->conHandle->connect_error);
						//exit();
						return false;
				} else {
						pg_query($this->conHandle, "CREATE TABLE IF NOT EXISTS gametitles (id SERIAL PRIMARY KEY, gametitle VARCHAR(255) NOT NULL, UNIQUE(gametitle));");
						pg_query($this->conHandle, "CREATE TABLE IF NOT EXISTS scores (id BIGSERIAL PRIMARY KEY, gametitle INT NOT NULL, registered TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(), data JSON NOT NULL DEFAULT '{}');");
						return true;
				}
		}

		public function execute() {
				$this->result = pg_query($this->conHandle, $this->q);

				if (!$this->result) {
						error_log($this->q);
						die("Error: %s\n" . $this->conHandle->error);
						$this->close();
				}
				$this->close();
		}

		public function get() {
				$this->result = pg_query($this->conHandle, $this->q);

				if ($this->result && $this->result !== TRUE) {
						while ($row = pg_fetch_assoc($this->result))
								yield $row;
				}
				$this->close();
		}

		public function close() {
				pg_close($this->conHandle);
		}
	}

	$gametitle_id_results = new query("SELECT * FROM gametitles WHERE gametitle='".$game."';");
	$gametitle_id = -1;
	if ($gametitle_id_results) {
		foreach ($gametitle_id_results->get() as $row) {
			$gametitle_id = $row['id'];
		}
	}
	if($gametitle_id < 0)
		die(1);

	if($data) {
		$data = json_encode($data);
		$tmp = new query("INSERT INTO scores (gametitle, data) VALUES(". $gametitle_id . ", '". $data . "');");
		$tmp->execute();
	}

	$stats_raw = new query("SELECT * FROM scores ORDER BY data->>'enemies_left' ASC, data->>'lives' DESC, data->>'shots_fired' ASC, data->>'time' ASC LIMIT 5;");
	$stats = [];
	if ($stats_raw) {
		foreach ($stats_raw->get() as $row) {
			//print_r($row);
			$stats[] = $row;
		}
	}
	print json_encode($stats);
?>