<?php
// ------------------------------------------------------------
// 
// BAN for IP
// 
// T.Kabu/MyDNS.JP           http://www.MyDNS.JP/
// Future Versatile Group    http://www.fvg-on.net/
// 
// ------------------------------------------------------------
?>
<?php
// ----------------------------------------------------------------------
// Sub Routine
// ----------------------------------------------------------------------
require(__DIR__."/ban4ipd_ban.php");
?>
<?php
// ----------------------------------------------------------------------
// Sub Routine
// ----------------------------------------------------------------------
function ban4ip_close($TARGET_CONF)
{
    // UNIXソケットを切断
    socket_close($TARGET_CONF['socket']);
    
    // inotifyによる監視を削除
    inotify_rm_watch($TARGET_CONF['target_inotify'], $TARGET_CONF['target_watch']);
    fclose($TARGET_CONF['target_inotify']);
    fclose($TARGET_CONF['target_fp']);
    
    // パラメータを戻す
    return $TARGET_CONF;
}
?>
<?php
// ----------------------------------------------------------------------
// Sub Routine
// ----------------------------------------------------------------------
function ban4ip_loop($TARGET_CONF)
{
    // ログデータ初期化
    $LOGDATA = '';
    
    // イベントの有無が読める限り
    while (($EVENTS = inotify_read($TARGET_CONF['target_inotify'])) !== false)
    {
        // イベント発生
        foreach($EVENTS as $EVENT)
        {
            // ファイルが新しくなったなら
            if ($EVENT['mask'] & IN_MOVE_SELF)
            {
                // 対象ファイルが切り詰められた？旨のメッセージを設定
                $TARGET_CONF['log_msg'] = date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: INFO [".$TARGET_CONF['target_service']."] Stop. Target File was rotate? (".$TARGET_CONF['conf_file'].")"."\n";
                // 親プロセスに送信
                ban4ip_sendmsg($TARGET_CONF);
                // ログデータ解析処理を抜ける
                return $TARGET_CONF;
            }
            // ファイルに変更があったのなら
            if (($EVENT['mask'] & IN_MODIFY))
            {
                // 対象ログから読み込み
                $LOGDATA .= fread($TARGET_CONF['target_fp'], 8192);
                
                // 読み込んだログデータを一行ごとのログ配列に格納
                $LOGARRAY = explode("\n", $LOGDATA);
                
                // ログ配列から一行ずつ処理
                foreach($LOGARRAY as $LOGSTR)
                {
                    // データが無いなら
                    if ($LOGSTR == '')
                    {
                        // 飛ばして次の行に行く
                        continue;
                    }
                    // 対象文字列かどうかを検査
                    foreach($TARGET_CONF['target_str'] AS $TARGET_PATTERN)
                    {
                        // 対象文字列があるなら
                        if (preg_match($TARGET_PATTERN, $LOGSTR, $TARGET_MATCH) == 1)
                        {
                            // 対象文字列がIPアドレスなら
                            if (filter_var($TARGET_MATCH[1], FILTER_VALIDATE_IP) !== FALSE)
                            {
                                // 現在日時を設定
                                $TARGET_CONF['logtime'] = time();
                                // 対象文字列を対象IPアドレスに設定
                                $TARGET_CONF['target_address'] = $TARGET_MATCH[1];
                                // ホスト名の逆引きがONになっていたら
                                if ($TARGET_CONF['hostname_lookup'] == 1)
                                {
                                    // 対象IPアドレスから対象ホスト名を取得して設定
                                    $TARGET_CONF['target_hostname'] = gethostbyaddr($TARGET_CONF['target_address']);
                                }
                                
                                // カウントデータベースにカウント対象IPアドレスを登録
                                $TARGET_CONF['count_db']->exec("INSERT INTO count_tbl VALUES ('".$TARGET_CONF['target_address']."','".$TARGET_CONF['target_service']."',".$TARGET_CONF['logtime'].")");
                                
                                // カウントデータベースで対象IPアドレスが対象時間内に何個存在するか取得
                                $RESULT = $TARGET_CONF['count_db']->query("SELECT address FROM count_tbl WHERE address = '".$TARGET_CONF['target_address']."' AND service = '".$TARGET_CONF['target_service']."' AND registdate > (".($TARGET_CONF['logtime'] - $TARGET_CONF['findtime']).")");
                                
                                // 対象IPアドレスの検出回数を取得
                                $RESULT_COUNT = 0;
                                while ($DB_DATA = $RESULT->fetchArray(SQLITE3_ASSOC))
                                {
                                    $RESULT_COUNT ++;
                                }
                                // もし検出回数以上になったら
                                if ($RESULT_COUNT >= $TARGET_CONF['maxretry'])
                                {
                                    // BANする
                                    $TARGET_CONF = ban4ip_ban($TARGET_CONF);
                                    // 親プロセスにログメッセージを送信
                                    ban4ip_sendmsg($TARGET_CONF);
                                }
                                // 検出回数未満なら
                                else
                                {
                                    // 対象IPアドレスのカウント数のメッセージを設定
                                    $TARGET_CONF['log_msg'] = date("Y-m-d H:i:s", $TARGET_CONF['logtime'])." ban4ip[".getmypid()."]: INFO [".$TARGET_CONF['target_service']."] Found ".$TARGET_CONF['target_address']." (".$RESULT_COUNT."/".$TARGET_CONF['maxretry']." counts)"."\n";
                                    // 親プロセスに送信
                                    ban4ip_sendmsg($TARGET_CONF);
                                }
                            }
                        }
                    }
                }
                // 行の途中かもしれないのでログデータに戻す
                $LOGDATA = $LOGSTR;
            }
        }
    }
    // パラメータを戻す
    return $TARGET_CONF;
}
?>
<?php
// ----------------------------------------------------------------------
// Sub Routine
// ----------------------------------------------------------------------
function ban4ip_init($TARGET_CONF)
{
    // ログメッセージを初期化
    $TARGET_CONF['log_msg'] = '';
    
    // BAN時間[s](--bantime)が設定されていないなら
    if (!isset($TARGET_CONF['bantime']))
    {
        // エラーメッセージに、BAN時間[s]の設定がない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] bantime is not set!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    // BAN時間[s]が数字ではないなら(もっと厳密にチェックする？)
    else if (!is_numeric($TARGET_CONF['bantime']))
    {
        // エラーメッセージに、BAN時間[s]の設定がない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] bantime is not integer!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    
    
    // 対象プロトコル(--protcol)が設定されているなら
    if (isset($TARGET_CONF['target_protcol']))
    {
        // 対象プロトコルが文字列指定でないか、tcpでもudpでもallでもないなら
        if (!is_string($TARGET_CONF['target_protcol']) || ($TARGET_CONF['target_protcol'] != 'tcp' && $TARGET_CONF['target_protcol'] != 'udp' && $TARGET_CONF['target_protcol'] != 'all'))
        {
            // エラーメッセージに、BAN時間[s]の設定がない旨を設定
            $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] ".$TARGET_CONF['target_protcol']." is not support protcol!? (".$TARGET_CONF['conf_file'].")"."\n";
        }
    }
    //対象ポートが設定されていないなら
    else
    {
        // 全ポート(-1)をBAN対象として設定
        $TARGET_CONF['target_protcol'] = 'all';
    }
    
    
    // 対象ポート(--port)が設定されているなら
    if (isset($TARGET_CONF['target_port']))
    {
        // 対象ポートが数字ではなく(＝文字列指定の)、ポート番号が引けないか、'all'でないなら
        if (!is_numeric($TARGET_CONF['target_port']) && (getservbyname($TARGET_CONF['target_port'],'tcp') == FALSE && $TARGET_CONF['target_port'] != 'all'))
        {
            // エラーメッセージに、BAN時間[s]の設定がない旨を設定
            $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] ".$TARGET_CONF['target_protcol']." is not support port!? (".$TARGET_CONF['conf_file'].")"."\n";
        }
    }
    //対象ポートが設定されていないなら
    else
    {
        // 全ポート(-1)をBAN対象として設定
        $TARGET_CONF['target_port'] = 'all';
    }
    
    
    // 必須パラメータ
    // 対象ルール(--rule)が設定されていないなら
    if (!isset($TARGET_CONF['target_rule']))
    {
        // エラーメッセージに、BAN時間[s]の設定がない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Rule is not set!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    // 対象ルールが文字列指定だった場合、DROPでもREJECTでもLOGでもないなら
    else if (!is_string($TARGET_CONF['target_rule']) || ($TARGET_CONF['target_rule'] != 'DROP' && $TARGET_CONF['target_rule'] != 'REJECT' && $TARGET_CONF['target_rule'] != 'LOG'))
    {
        // エラーメッセージに、BAN時間[s]の設定がない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Rule is not support rule!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    
    
    // デーモンでは必須パラメータ
    // 対象サービス(--service)が設定されていないなら
    if (!isset($TARGET_CONF['target_service']))
    {
        // エラーメッセージに、BAN時間[s]の設定がない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] target_service is not set!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    
    // 対象プロトコルと対象ポートが片方だけallなら
    if (
        ($TARGET_CONF['target_protcol'] != 'all' && $TARGET_CONF['target_port'] == 'all') ||
        ($TARGET_CONF['target_protcol'] == 'all' && $TARGET_CONF['target_port'] != 'all')
        )
    {
        // エラーメッセージに、BAN時間[s]の設定がない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] protcol(".$TARGET_CONF['target_protcol'].") and port(".$TARGET_CONF['target_port'].") mismatch!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    
    // --------------------------------
    
    // UNIXソケットを開く
    $TARGET_CONF['socket'] = socket_create(AF_UNIX, SOCK_DGRAM, 0);
    // UNIXソケットが開けなかったら
    if ($TARGET_CONF['socket'] == FALSE )
    {
        // エラーメッセージに、UNIXソケットを開けない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Cannot create socket!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    // UNIXソケットをノンブロッキングモードに変更できなかったら
    if (socket_set_nonblock($TARGET_CONF['socket']) == FALSE)
    {
        // エラーメッセージに、UNIXソケットをノンブロッキングモードに変更できない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Cannot set nonblock socket!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    // UNIXソケットの接続を確立できるまで無限ループ(@をつけてエラー出力を抑制)
    while(@socket_connect($TARGET_CONF['socket'], $TARGET_CONF['socket_file']) == FALSE)
    {
        // 100msくらいのウェイトを置く
        usleep(100000);
    }
    
    // 対象ログがないなら
    if (!is_file($TARGET_CONF['target_log']))
    {
        // エラーメッセージに、対象ログが開けない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Cannot find target_log!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    // 対象ログを開く
    $TARGET_CONF['target_fp'] = fopen($TARGET_CONF['target_log'], "r");
    // 対象ログが開けなかったら
    if ($TARGET_CONF['target_fp'] == FALSE)
    {
        // エラーメッセージに、対象ログが開けない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Cannot open target_log!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    
    // 対象ログの最後にシーク
    $RESULT = fseek($TARGET_CONF['target_fp'], 0, SEEK_END);
    // 対象ログの最後にシークできなかったら
    if ($RESULT == -1)
    {
        // エラーメッセージに、対象ログの最後にシークできない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Cannot seek target_log!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    
    // inotifyモジュール初期化
    $TARGET_CONF['target_inotify'] = inotify_init();
    // 失敗したなら
    if ($TARGET_CONF['target_inotify'] === false)
    {
        // エラーメッセージに、対象ログの最後にシークできない旨を設定
        $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Failed to obtain an inotify instance!? (".$TARGET_CONF['conf_file'].")"."\n";
    }
    else
    {
        // 指定ファイルに変化があったらイベントを起こすように設定(IN_MODIFYは追記があったとき、IN_MOVE_SELFはlogrotateで切り詰めがあったとき)
        $TARGET_CONF['target_watch'] = inotify_add_watch($TARGET_CONF['target_inotify'], $TARGET_CONF['target_log'], IN_MODIFY | IN_MOVE_SELF);
        
        // 設定が失敗したら
        if ($TARGET_CONF['target_watch'] === false)
        {
            // エラーメッセージに、イベントを起こすように設定できない旨を設定
            $TARGET_CONF['log_msg'] .= date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: ERROR [".$TARGET_CONF['target_service']."] Cannot watch target_log!? (".$TARGET_CONF['conf_file'].")"."\n";
        }
    }
    // パラメータを戻す
    return $TARGET_CONF;
}
?>
<?php
// ----------------------------------------------------------------------
// Sub Routine
// ----------------------------------------------------------------------
function ban4ip_start($TARGET_CONF)
{
    do // loop_mode=1なら、ログ切りつめでも動作し続ける
    {
        // 対象ログの監視＆BAN処理の初期化
        $TARGET_CONF = ban4ip_init($TARGET_CONF);
        // 初期化に失敗したら
        if (strlen($TARGET_CONF['log_msg']))
        {
            // メッセージを表示
            print $TARGET_CONF['log_msg'];
            // 親プロセスに送信
            ban4ip_sendmsg($TARGET_CONF);
            // 終わり
            exit;
        }
        // 成功したら
        else
        {
            // 子プロセスがスタートした旨を表示
            $TARGET_CONF['log_msg'] = date("Y-m-d H:i:s")." ban4ip[".getmypid()."]: INFO [".$TARGET_CONF['target_service']."] Co-Process Start. (".$TARGET_CONF['conf_file'].")"."\n";
            // 親プロセスに送信
            ban4ip_sendmsg($TARGET_CONF);
        }
        // 対象ログの監視＆BAN処理開始
        $TARGET_CONF = ban4ip_loop($TARGET_CONF);
        
        // 対象ログの監視＆BAN処理の終了処理
        $TARGET_CONF = ban4ip_close($TARGET_CONF);
    }
    while ($TARGET_CONF['loop_mode'] == 1);
}
?>