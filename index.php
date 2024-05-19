<?php
    ini_set('display_errors', 'On');
    ini_set('error_reporting', E_ERROR);

    header('Content-Type: text/html; charset=UTF-8');

    require_once('private/include/php/repos/vendor/autoload.php');
    require_once('private/include/php/path.php');
    require_once('private/include/php/config.php');
    require_once('private/include/php/common.php');
    require_once('private/include/php/main.php');

    session_start();
    check_session_hash();
    parse_input();
    handle_actions();
    show_main();
    db_disconnect();
?>