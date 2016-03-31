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
// Init Routine
// ----------------------------------------------------------------------
// 環境変数を設定する
// タイムゾーン
//date_default_timezone_set('Asia/Tokyo');
date_default_timezone_set(@date_default_timezone_get());

// 内部文字コード
mb_internal_encoding('UTF-8');

// 出力文字コード
mb_http_output('UTF-8');

// メイン設定ファイル名
$BAN4IPD_CONF['main_conf'] = '/etc/ban4ipd.conf';

?>
<?php
// ----------------------------------------------------------------------
// Sub Routine
// ----------------------------------------------------------------------
function psearch($PP, $PATTERN)
{
    // リソース$PPから一行ずつ読み込む
    while (($DATA = fgets($PP)) !== false)
    {
        // もし$DATA内に$PATTERNに該当するデータがあるなら
        if (preg_match($PATTERN, $DATA))
        {
            // TRUEを返す
            return TRUE;
        }
    }
    // 見つからなければ、FALSEを返す
    return FALSE;
}
?>
<?php
// ----------------------------------------------------------------------
// Check inotify module
// ----------------------------------------------------------------------
// inotifyモジュールがあるか確認
if (!extension_loaded('inotify'))
{
    print "inotify extension not loaded!?"."\n";
    exit -1;
}
?>
<?php
// ----------------------------------------------------------------------
// Check sqlite module
// ----------------------------------------------------------------------
// sqlite3モジュールがあるか確認
if (!extension_loaded('sqlite3'))
{
    print "sqlite3 extension not loaded!?"."\n";
    // 終わり
    exit -1;
}
?>
<?php
// ----------------------------------------------------------------------
// Check pdo_sqlite module
// ----------------------------------------------------------------------
// pdo_sqliteモジュールがあるか確認
if (!extension_loaded('pdo_sqlite'))
{
    print "pdo_sqlite extension not loaded!?"."\n";
    // 終わり
    exit -1;
}
?>
<?php
// ----------------------------------------------------------------------
// Check sockets module
// ----------------------------------------------------------------------
// socketsモジュールがあるか確認
if (!extension_loaded('sockets'))
{
    print "sockets extension not loaded!?"."\n";
    // 終わり
    exit -1;
}
?>
<?php
// ----------------------------------------------------------------------
// Read Configration
// ----------------------------------------------------------------------
// メイン設定ファイルがあるなら
if (is_file($BAN4IPD_CONF['main_conf']))
{
    // メイン設定ファイルを読み込んで変数展開する
    $BAN4IPD_CONF = parse_ini_file($BAN4IPD_CONF['main_conf'], FALSE, INI_SCANNER_NORMAL);
}
// 無ければ、
else
{
    print $BAN4IPD_CONF['main_conf']." not found!?"."\n";
    // 終わり
    exit -1;
    // エラーメッセージに、メイン設定ファイルがない旨を設定
    $ERR_MSG = 'Cannot read '.$BAN4IPD_CONF['main_conf'].'!?';
    // 設定値をFALSEにする。
    $BAN4IPD_CONF = FALSE;
}
// 読み込めなかったら
if ($BAN4IPD_CONF === FALSE)
{
    print $BAN4IPD_CONF['main_conf']." not loaded!?"."\n";
    // 終わり
    exit -1;
}
?>
<?php
// ----------------------------------------------------------------------
// Check ban4ip SQLite3 DB
// ----------------------------------------------------------------------
// SQLite3データベース用のディレクトリを作成(エラー出力を抑制)
@mkdir($BAN4IPD_CONF['db_dir']);
// SQLite3データベース用のディレクトリがないなら
if (!is_dir($BAN4IPD_CONF['db_dir']))
{
    print $BAN4IPD_CONF['db_dir']." not found!?"."\n";
    exit -1;
}

// カウントデータベースに接続(無ければ新規に作成)
$BAN4IPD_CONF['count_db'] = new SQLite3($BAN4IPD_CONF['db_dir'].'/count.db');

// カウントデータベースに接続できなかったら
if ($BAN4IPD_CONF['count_db'] === FALSE)
{
    print "Cannot connect ".$BAN4IPD_CONF['db_dir'].'/count.db'."!?"."\n";
    // 終わり
    exit -1;
}

// テーブルがなかったら作成
$BAN4IPD_CONF['count_db']->exec('CREATE TABLE IF NOT EXISTS count_tbl (address, service, registdate)');

// カウントデータベースのロックタイムアウト時間を少し長くする
$BAN4IPD_CONF['count_db']->busyTimeout($BAN4IPD_CONF['db_timeout']);

// BANデータベースに接続(無ければ新規に作成)
$BAN4IPD_CONF['ban_db'] = new SQLite3($BAN4IPD_CONF['db_dir'].'/ban.db');

// BANデータベースに接続できなかったら
if ($BAN4IPD_CONF['ban_db'] === FALSE)
{
    print "Cannot connect ".$BAN4IPD_CONF['db_dir'].'/ban.db'."!?"."\n";
    // 終わり
    exit -1;
}

// テーブルがなかったら作成
$BAN4IPD_CONF['ban_db']->exec('CREATE TABLE IF NOT EXISTS ban_tbl (address, service, protcol, port, rule, unbandate)');

// カウントデータベースのロックタイムアウト時間を少し長くする
$BAN4IPD_CONF['ban_db']->busyTimeout($BAN4IPD_CONF['db_timeout']);

?>
