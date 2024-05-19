<?php
    function init_cache() {
        global $CONFIG, $CACHE;

        if (isset($CACHE)) return;

        if (!class_exists('Memcached')) {
            die('Memcached not exists!');
        }

        $CACHE = new Memcached();
        $CACHE->addServer($CONFIG['MEMCACHED_HOST'], $CONFIG['MEMCACHED_PORT']);
    }

    function small_ddos_protection() {
        global $CACHE;

        $rps = $CACHE->get("rps_$_SERVER[REMOTE_ADDR]");
        !$rps ? $rps = 1 : $rps++;

        if ($rps > 200) {
            echo "Blocking $_SERVER[REMOTE_ADDR] for $rps seconds...";
            $CACHE->set("rps_$_SERVER[REMOTE_ADDR]", $rps, $rps);
            exit;
        } else {
            $CACHE->set("rps_$_SERVER[REMOTE_ADDR]", $rps, 1);
        }
    }

    function db_connect() {
        global $CONFIG, $DBH;

        if (isset($DBH)) return;

        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;

        try {
            $DBH = new mysqli(
                $CONFIG['DB_HOST'],
                $CONFIG['DB_USER'],
                $CONFIG['DB_PASSWORD'],
                $CONFIG['DB_NAME']
            );
        } catch (mysqli_sql_exception $e) {
            die('Connection to DB failed!');
        }
    }

    function db_disconnect() {
        global $DBH;

        if (isset($DBH)) {
            $DBH->close();
            unset($GLOBALS['DBH']);
        }
    }

    function db_query($query) {
	    global $DBH;

        try {
	        return $DBH->query($query);
        } catch (mysqli_sql_exception $e) {
            log_mysql_error($query, $e->getMessage());
        }
    }

    function db_query_array($query) {
	    return db_query($query)->fetch_array();
    }

    function db_query_all($query) {
	    return db_query($query)->fetch_all(MYSQLI_ASSOC);
    }

    function db_insert_table($table, $arr) {
        global $DBH;

        $keys = '';
        $values = '';

        foreach($arr as $key => $value) {
            $keys .= "$key, ";
            $values .= "'$value', ";
        }

        $keys = substr($keys, 0, strlen($keys) - 2);
        $values = substr($values, 0, strlen($values) - 2);

        db_query("INSERT $table ($keys) VALUES ($values)");

        return $DBH->insert_id;
    }

    function db_update_table($table, $arr, $kid, $id) {
        $query = "UPDATE $table SET ";

        foreach($arr as $key => $value) {
            $query .= "$key='$value', ";
        }

        $query = substr($query, 0, strlen($query) - 2);
        $query .= " WHERE $kid='$id'";

        db_query($query);
    }

    function zerofill($num, $len) {
        $num_str = "$num";
        $num_len = strlen($num_str);

        for ($i = 0; $i < $len - $num_len; $i++) {
            $num_str = "0$num_str";
        }

        return $num_str;
    }

    function textdate($timestamp) {
	    $arr = getdate($timestamp);

        return 
            zerofill($arr['mday'], 2).'-'.
            zerofill($arr['mon'], 2).'-'.
            $arr['year'].', '.
            zerofill($arr['hours'], 2).':'.
            zerofill($arr['minutes'], 2);
    }

    function log_mysql_error($query, $error) {
        global $CONFIG;

        $log_time = textdate(time());
        $log_file = $CONFIG['MYSQL_ERROR_LOG_FILE'];
        $log_path = dirname($log_file);
        if (!file_exists($log_path)) {
            mkdir($log_path, 0777, true);
        }

        $log = fopen($log_file, 'a');
        fwrite($log,
            "Time: $log_time\n".
            "Ip: $_SERVER[REMOTE_ADDR]\n".
            "User-Agent: $_SERVER[HTTP_USER_AGENT]\n".
		    "Query: $query\n".
            "Error: $error\n\n\n\n");
        fclose($log);

        die('MySQL error has occurred!');
    }

    function check_var($var) {
        return isset($var) && !empty($var);
    }

    function parse_input() {
        global $INPUT;

        foreach ($_POST as $key => $value) {
            $INPUT[$key] = stripslashes($value);
            $INPUT[$key] = str_replace("'", "`", $INPUT[$key]);
            $INPUT[$key] = str_replace(array('\\', "'"), '', $INPUT[$key]);
		}

        foreach ($_GET as $key => $value) {
            $INPUT[$key] = stripslashes($value);
            $INPUT[$key] = str_replace(array('`', '\\', "'"), '', $INPUT[$key]);
        }
    }

    function parse_template($file, $arr) {
        global $CONFIG;

        $html = implode('', file("$CONFIG[TEMPLATES_FOLDER]$file"));
        $tokens = explode('!!', $html);
        $tlen = sizeof($tokens);

	    for ($ct = 1; $ct < $tlen; $ct += 2) $tokens[$ct] = $arr[$tokens[$ct]];
        return join('', $tokens);
    }

    function parse_template_tokens($template, $tokens_to_parse) {
        $tokens = explode('!!', $template);
        $tlen = sizeof($tokens);

	    for ($ct = 1; $ct < $tlen; $ct += 2) {
            $data = $tokens_to_parse[$tokens[$ct]];
            $tokens[$ct] = $data;
        }
        return join('', $tokens);
    }
?>