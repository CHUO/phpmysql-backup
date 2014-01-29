<?php
define('BACKUP_DIR', './');
define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');

$tables = array(
);

/**
 * Backup the tables to an SQL file.
 *
 * INSECURE!!! this injects the tables names in the SQL.
 *
 * @param string $db_user
 * @param string $db_pass
 * @param string $db_name
 * @param string $db_host
 * @param string|array $tables
 *
 * @return bool
 */
function backup_tables_unsafe($db_user, $db_pass, $db_name, $db_host = 'localhost', $tables = '*')
{
	$db_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
	if ($db_conn->connect_errno) {
		echo "Failed to connect to MySQL: " . $db_conn->connect_error;

		return false;
	}

	//get all of the tables
	if ($tables === '*' || empty($tables)) {
		$tables = array();
		$result = $db_conn->query('SHOW TABLES');
		while ($row = $result->fetch_array(MYSQLI_NUM)) {
			$tables[] = $row[0];
		}
	} else {
		$tables = is_array($tables) ? $tables : explode(',', $tables);
	}

	$fp = fopen(BACKUP_DIR . 'db-backup-' . time() . '-' . (md5(implode(',', $tables))) . '.sql', 'w+');

	if (false === $fp) {
		$db_conn->close();
		echo "Failed to open backup file";

		return false;
	}

	$date = date('M j, Y \a\t H:i:s');
	$header = <<<SQL
-- CHUO MDDb SQL Dump
-- Generation Time: {$date}

--
-- Database: `{$db_name}`
--

-- --------------------------------------------------------
SQL;

	fwrite($fp, $header);

	//cycle through
	foreach ($tables as $table) {
		$temp = $db_conn->query("SHOW CREATE TABLE `{$table}`")->fetch_row();
		$create_sql = $temp[1];

		$fields = array();
		$types = array();
		$result = $db_conn->query("SHOW FIELDS FROM `{$table}`");
		while ($row = $result->fetch_array(MYSQLI_NUM)) {
			$fields[] = $row[0];
			$types[$row[0]] = $row[1];
		}
		$insert_sql = implode('`, `', $fields);

		$output = <<<SQL


--
-- Table structure for table `{$table}`
--

DROP TABLE IF EXISTS `{$table}`;
{$create_sql};

--
-- Table data for table `{$table}`
--

INSERT INTO `{$table}` (`{$insert_sql}`) VALUES
SQL;

		fwrite($fp, $output);

		$extra = "\n";
		$result = $db_conn->query("SELECT * FROM `{$table}`", MYSQLI_USE_RESULT);
		while ($row = $result->fetch_assoc()) {
			$func = function ($value, $key) use (&$row, &$types, &$line_sql) {
				if (false === strpos($types[$value], 'int')) {
					$line_sql .= (empty($line_sql) ? '' : ', ') . "'{$row[$value]}'";
				} else {
					$line_sql .= (empty($line_sql) ? '' : ', ') . $row[$value];
				}
			};
			$line_sql = '';
			array_walk($fields, $func);
			fwrite($fp, "{$extra}({$line_sql})");
			$extra = ",\n";
		}
		fwrite($fp, ';');
		$result->free();
	}

	//save file
	fclose($fp);

	//close mysql
	$db_conn->close();
}

if (php_sapi_name() === 'cli') {
	backup_tables_unsafe(DB_USER, DB_PASS, DB_NAME, DB_HOST, $tables);
} else {
	$filename = __FILE__;
	shell_exec("`which php` '{$filename}' 2>&1 >/dev/null &");
	echo "Backup job dispatched.";
}
