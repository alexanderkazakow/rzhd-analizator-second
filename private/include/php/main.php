<?php
    use Pheanstalk\Pheanstalk;

    function check_session_hash() {
        if (!isset($_SESSION['hash'])) {
            $_SESSION['hash'] = sha1(time().generate_rand_str(100));
        }
    }

    /**
     * Валидация данных для id пользователя.
     */
    function check_user_id($user_id) {
        return check_var($user_id) && preg_match('/^[0-9]+$/', $user_id);
    }

    /**
     * Валидация данных для email.
     */
    function check_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Валидация данных для пароля.
     */
    function check_password($password) {
        return check_var($password) && strlen($password) >= 6;
    }

    /**
     * Генерация случайной строки определенной длины.
     */
    function generate_rand_str($len = 10) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars_len = strlen($chars);

        $rand_str = '';
        for ($i = 0; $i < $len; $i++) {
            $rand_str .= $chars[rand(0, $chars_len - 1)];
        }

        return $rand_str;
    }

    /**
     * Генерация хэша для новой сессии пользователя.
     */
    function generate_session_hash($user_id) {
        return password_hash(
            time().
            $user_id.
            $_SERVER['REMOTE_ADDR'].
            $_SERVER['HTTP_USER_AGENT'].
            generate_rand_str(100),
            PASSWORD_DEFAULT
        );
    }

    /**
     * Формирование хэша для пароля пользователя.
     */
    function get_password_hash($password) {
        global $CONFIG;

        return md5($password.$CONFIG['PASSWORD_SALT']);
    }

    /**
     * Извлечение пользователя по его id.
     * Если есть кэшированная запись, то вернуть её.
     * В противном случае - попытаться извлечь данные из БД, данные записываются в кэш.
     */
    function get_user($user_id) {
        global $CACHE;

        $user = $CACHE->get("raildoctech_user_$user_id");
        if (!$user) {
            db_connect();

            $user = db_query_array(
                "SELECT * FROM users WHERE user_id=$user_id"
            );
            if ($user) $CACHE->set("raildoctech_user_$user_id", $user);
        }

        return $user;
    }

    /**
     * Очистка данных куки, использующихся для авторизации.
     */
    function clear_auth_cookies() {
        setcookie('user_id', '', time() - 3600);
        setcookie('session_hash', '', time() - 3600);
    }

    /**
     * Извлечение id пользователя по его email.
     */
    function get_user_id_by_email($email) {
        if (!check_email($email)) return;

        db_connect();
        $user = db_query_array("SELECT user_id FROM users WHERE email='$email'");
        if ($user) return $user['user_id'];
    }

    /**
     * Извлечение активных сессий пользователя по его id.
     * Если есть кэшированная запись с массивом сессий данного пользователя, то вернуть её.
     * В противном случае - попытаться извлечь данные из БД, данные записываются в кэш.
     */
    function get_sessions($user_id) {
        global $CACHE;

        $sessions = $CACHE->get("raildoctech_sessions_$user_id");
        if (!$sessions) {
            db_connect();

            $now_str = date('Y-m-d H:i:s', time());
            $sessions = db_query_all(
                "SELECT * FROM sessions ".
                "WHERE user_id=$user_id AND expires_in>'$now_str'"
            );
            if ($sessions) $CACHE->set("raildoctech_sessions_$user_id", $sessions);
        }

        return $sessions;
    }

    /**
     * Создание и выдача новой сессии пользователя.
     */
    function get_free_session($user_id) {
        global $CONFIG;

        db_connect();
        $now = time();
        $now_str = date('Y-m-d H:i:s', $now);
        $num_active_sessions = db_query_array(
            "SELECT COUNT(*) FROM sessions ".
            "WHERE user_id=$user_id AND expires_in>'$now_str'"
        );

        // ограничение на максимальное количество сессий
        if ($num_active_sessions[0] >= $CONFIG['MAX_SESSIONS_PER_USER']) return;

        $session_id = 0;

        $result = db_query_array(
            "SELECT session_id FROM sessions ".
            "WHERE expires_in<='$now_str' LIMIT 1"
        );
        if ($result) $session_id = $result['session_id'];

        $session['user_id'] = $user_id;
        $session['hash'] = generate_session_hash($user_id);
        $session['created_at'] = $now_str;
        $session['expires_in'] = date('Y-m-d H:i:s', $now + $CONFIG['SESSION_DURATION']);
        $session['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $session['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        if ($session_id) {
            db_update_table(
                'sessions',
                $session,
                'session_id',
                $session_id
            );
        } else {
            $session_id = db_insert_table('sessions', $session);
        }

        $session['session_id'] = $session_id;

        return $session;
    }

    /**
     * Поиск сессии пользователя по его id и хэшу сессии (данные куки) среди активных сессий.
     */
    function get_session($user_id, $session_hash) {
        if (!check_user_id($user_id) || !check_var($session_hash)) return;

        $sessions = get_sessions($user_id);
        if ($sessions) {
            foreach ($sessions as $session) {
                if ($session['hash'] == $session_hash) return $session;
            }
        }
    }

    /**
     * Создание и регистрация новой сессии пользователя.
     */
    function register_user_session($user_id) {
        global $CONFIG, $CACHE;

        if (
            check_user_id($user_id) &&
            $session = get_free_session($user_id)
        ) {
            $CACHE->delete("raildoctech_sessions_$user_id");
            get_sessions($user_id);

            $cookie_expires = time() + $CONFIG['SESSION_DURATION'];

            setcookie(
                'user_id',
                $session['user_id'],
                $cookie_expires
            );

            setcookie(
                'session_hash',
                $session['hash'],
                $cookie_expires
            );

            return true;
        }
    }

    function auth_user() {
        global $CUR_USER, $CUR_SESSION;

        // куки авторизации
        $user_id = $_COOKIE['user_id'];
        $session_hash = $_COOKIE['session_hash'];

        // поиск среди активных сессий
        $session = get_session($user_id, $session_hash);
        if (!$session || strtotime($session['expires_in']) <= time()) {
            clear_auth_cookies();
            return;
        }

        $CUR_SESSION = $session;
        $CUR_USER = get_user($user_id);
    }

    /**
     * Процедура входа пользователя по E-mail и паролю.
     */
    function login() {
        global $INPUT;

        if ( // валидация данных для входа и регистрация сессии пользователя
            !check_email($email = $INPUT['email']) ||
            !check_password($password = $INPUT['password']) ||
            !($user_id = get_user_id_by_email($email)) ||
            !($user = get_user($user_id)) ||
            $user['password_hash'] != get_password_hash($password) ||
            !register_user_session($user_id)
        ) $answer = 'error';
        else $answer = 'ok';

        echo json_encode(array('answer' => $answer));
    }

    /**
     * Процедура завершения сессии пользователя.
     */
    function logout_session($user_id, $session_id) {
        global $CACHE;

        db_connect();
        $session['expires_in'] = date('Y-m-d H:i:s', time());
        db_update_table(
            'sessions',
            $session,
            'session_id',
            $session_id
        );
        $CACHE->delete("raildoctech_sessions_$user_id");
    }

    /**
     * Процедура завершения текущей сессии пользователя.
     */
    function logout() {
        global $CUR_SESSION;

        if ($CUR_SESSION) {
            logout_session($CUR_SESSION['user_id'], $CUR_SESSION['session_id']);

            // очистка куки авторизации
            clear_auth_cookies();
        }
    }

    function upload_doc() {
        global $CONFIG, $CUR_USER;

        $uploaddir = '/dev/shm/';

        $upload_name = $_FILES['uploadForm_File']['tmp_name'];
        $original_name = $_FILES['uploadForm_File']['name'];
        $split_filename = explode('.', $original_name);
        $extension = strtolower(end($split_filename));
        $tmp_filename = end(explode('/', $upload_name));

        $uploadfile = $uploaddir.$tmp_filename;

        if (move_uploaded_file($_FILES['uploadForm_File']['tmp_name'], $uploadfile)) {
            $output = null;
            $retval = null;

            exec("md5sum $uploadfile", $output, $retval);
            $doc_md5sum = explode(' ', $output[0])[0];
        
            $new_doc_filename = md5(md5("user$CUR_USER[user_id]").$doc_md5sum).".".$extension;

            $new_doc_folder = "/var/www/local.net$CONFIG[ROOT_PATH]static/documents/";
            $new_images_folder = "/var/www/local.net$CONFIG[ROOT_PATH]static/images/";
            $new_doc_path = $new_doc_folder.$new_doc_filename;

            if (check_doc_exists($new_doc_path)) {
                echo 'error';
            } else {
                exec("mv $uploadfile $new_doc_path", $output, $retval);
                if ($extension == 'pdf') {
                    echo 'error2';
                    $out = "/dev/shm/pdf-to-png";
                    exec("pdftoppm -png $new_doc_path $out", $output, $retval);
                    $preview = $out."-1.png";
                    $new_preview_path = $new_images_folder.md5(md5("user$CUR_USER[user_id]").$doc_md5sum).".png";
                    exec("mv $preview $new_preview_path", $output, $retval);
                }

                db_connect();
                $document['path'] = "$CONFIG[ROOT_PATH]static/documents/$new_doc_filename";
                $document['user_id'] = $CUR_USER['user_id'];
                $document_id = db_insert_table('documents', $document);
        $document = get_document($document_id);

        if ($document && !$document['recognition_started']) {
            $data = "$document_id:$document[path]";

            $pheanstalk = new Pheanstalk('localhost');
            $pheanstalk->useTube('raildoctech')->put($data);
        }
            }
        }
    }

    function handle_actions() {
        global $CONFIG, $INPUT, $CUR_USER;

        $act = $INPUT['act'];
        if (!check_var($act)) return;

        switch ($act) {
            case 'add_audio_file_to_queue':
                add_audio_file_to_queue();
                break;
            case 'get_data':
                get_data();
                break;
        }
    }

    function show_main() {
        global $CONFIG, $TEMPLATE, $CUR_USER;

        $TEMPLATE['title'] = 'РЖД Анализатор';
        $TEMPLATE['subtitle'] = ' - анализ служебных переговоров';
        $TEMPLATE['year_now'] = getdate(time())['year'];

        if ($CUR_USER) {
            $TEMPLATE['info'] = get_password_hash('12345678');
            $TEMPLATE['upload_form'] = file_get_contents('private/include/templates/upload.html');
            $TEMPLATE['main_content'] = file_get_contents('private/include/templates/tabs.html');
            $TEMPLATE['main_content'] = parse_template_tokens($TEMPLATE['main_content'], $TEMPLATE);
            
        } else {
            $TEMPLATE['scripts'] = '<script>$(document).ready(function() {showLoginDialog();});</script>';
            $TEMPLATE['dialogs'] = file_get_contents('private/include/templates/dialog-login.html');
        }

        echo parse_template('main.html', $TEMPLATE);
    }

    function add_audio_file_to_queue() {
        global $INPUT;

        header('Content-Type: text/json');

        $file_size = intval(@$INPUT['file_size']);
        $sha1_hash = @$INPUT['sha1_hash'];

        if (
            $file_size &&
            $sha1_hash &&
            ($tmp_file = $_FILES['user_file_upload']['tmp_name']) &&
            ($audio = file_get_contents($tmp_file))
        ) {
            $audio_ok = true;
        }

        if ($audio_ok) {
            $uploads_dir = ROOT_PATH.'/private/uploads/';
            if (!file_exists($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }

            $audio_file = $uploads_dir.'/'.$sha1_hash.'.mp3';
            $saved_len = file_put_contents($audio_file, $audio);
            if ($saved_len == $file_size) {
                $file_uploaded = true;
            }
        }

        if ($file_uploaded) {
            db_connect();
            $file = db_query_array("SELECT * FROM files WHERE sha1_hash='$sha1_hash'");
            if ($file) {
                $update['sha1_hash'] = $sha1_hash;
                $update['file_size'] = $file_size;
                $update['session_hash'] = $_SESSION['hash'];
                $update['status'] = 2;
                db_update_table(
                    'files',
                    $update,
                    'id',
                    $file['id']
                );
            } else {
                $new_file['sha1_hash'] = $sha1_hash;
                $new_file['file_size'] = $file_size;
                $new_file['session_hash'] = $_SESSION['hash'];
                $new_file['status'] = 2;
                db_insert_table(
                    'files',
                    $new_file
                );
            }
            $pheanstalk = new Pheanstalk('localhost');
            $pheanstalk->useTube('rzhd_audio_files')->put($sha1_hash);
            $answer = 'ok';
        } else {
            $answer = 'error';
        }

        $result['answer'] = $answer;
        echo json_encode($result);
        exit;
    }

    function get_data() {
        global $INPUT;

        header('Content-Type: text/json');

        $answer = 'ok';

        $result['hash'] = $_SESSION['hash'];
        $result['answer'] = $answer;

        db_connect();
        $session_hash = $_SESSION['hash'];
        $audios = db_query_all("SELECT * FROM files WHERE session_hash='$session_hash'");
        $result['files'] = $audios;

        echo json_encode($result);
        exit;
    }
?>