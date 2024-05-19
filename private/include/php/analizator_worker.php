<?php
    require_once('repos/vendor/autoload.php');
    require_once('config.php');
    require_once('common.php');
    require_once('main.php');
    require_once('path.php');

    use Pheanstalk\Pheanstalk;

    register_exit_handlers();
    analyze_audio_files();

    // function check_blacklist($str) {
    //     $blacklist = array(
    //         'Субтитры делал DimaTorzok',
    //         'Субтитры сделал DimaTorzok',
    //         'Субтитры создавал DimaTorzok',
    //         'Субтитры добавил DimaTorzok',
    //         'Субтитры предоставил DimaTorzok',
    //         'Продолжение следует...'
    //     );

    //     foreach ($blacklist as $entry) {
    //         if (strpos($str, $entry) !== false) {
    //             return false;
    //         }
    //     }

    //     return true;
    // }

    function analyze_audio_files() {
        global $CONFIG;

        $pheanstalk = new Pheanstalk($CONFIG['BEANSTALKD_HOST']);

        $RUNNING = true;
        while ($RUNNING) {
            $job = $pheanstalk->watch('rzhd_audio_files')->ignore('default')->reserve();

            if ($job) {
                $sha1_hash = $job->getdata();
                printf("Got task with file sha1 hash %s...\n", $sha1_hash);

                $file_name = ROOT_PATH.'/private/uploads/'.$sha1_hash.'.mp3';
                $cmd = "python ".ROOT_PATH."/private/include/python/model/main.py $file_name";
                printf("Executing '%s'...\n", $cmd);
                $out = shell_exec($cmd);
                printf("Model out: %s\n", $out);
                $text = file_get_contents(ROOT_PATH.'/private/include/php/out.txt');
                printf("Recognized text: %s\n", $text);
                $new_audio['recognized_text'] = $text;
                $new_audio['status'] = 6;
                db_connect();
                db_update_table(
                    'files',
                    $new_audio,
                    'sha1_hash',
                    $sha1_hash
                );
                // $message = get_message($message_id);
                // if (!$message || $message['type'] != '1' || !$message['audio_url']) {
                //     printf("Wrong voice message id!\n");
                //     $pheanstalk->delete($job);
                //     continue;
                // }

                // $tmp_folder = "C:\\Windows\\Temp\\";
                // $audio_file_path = realpath('../../../'.$message['audio_url']);
                // if (file_exists($audio_file_path)) {
                //     printf("Audio file path is %s\n", $audio_file_path);
                // } else {
                //     printf("Audio file %s not exists!\n", $audio_file_path);
                //     $pheanstalk->delete($job);
                //     continue;
                // }

                // $output = null;
                // $retval = null;
                // $whisper = "C:\\Users\\я\\AppData\\Local\\Programs\\Python\\Python310\\Scripts\\whisper";
                // $cmd = "$whisper $audio_file_path --output_dir=$tmp_folder --model large --language Russian";
                // printf("Running command \"%s\"...\n", $cmd);
                // exec($cmd, $output, $retval);
                // printf("Whisper is done with return value %d!\n", $retval);

                // if ($retval != 0) {
                //     printf("Command failed!\n");
                //     $pheanstalk->delete($job);
                //     continue;
                // }

                // $original_name = basename($audio_file_path);
                // $split_filename = explode('.', $original_name);

                // $base_doc = $tmp_folder.$split_filename[0];
                // $txt_doc = $base_doc.'.txt';
                // $json_doc = $base_doc.'.json';
                // $srt_doc = $base_doc.'.srt';
                // $tsv_doc = $base_doc.'.tsv';
                // $vtt_doc = $base_doc.'.vtt';

                // @unlink($json_doc);
                // @unlink($srt_doc);
                // @unlink($tsv_doc);
                // @unlink($vtt_doc);

                // $recognition = @file_get_contents($txt_doc);
                // if (!$recognition || !check_blacklist($recognition)) {
                //     $recognition = '';
                // }
                // $update['audio_recognition'] = $recognition;
                // @unlink($txt_doc);
                // db_update_table(
                //     'messenger_messages',
                //     $update,
                //     'message_id',
                //     $message_id
                // );
                // db_disconnect();
                $pheanstalk->delete($job);
            }
        }

        printf("Goodbye!\n");
    }

    function register_exit_handlers() {
        if (function_exists('pcntl_signal')) { // for Linux
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, 'linux_exit_handler');
            pcntl_signal(SIGTERM, 'linux_exit_handler');
        }

        if (function_exists('sapi_windows_set_ctrl_handler')) { // for Windows
            sapi_windows_set_ctrl_handler('windows_exit_handler', true);
        }
    }

    function linux_exit_handler($signo) {
        exit_handler();
    }

    function windows_exit_handler(int $event) {
        exit_handler();
    }

    function exit_handler() {
        global $RUNNING;

        $RUNNING = false;
    }
?>