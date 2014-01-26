<?php
header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
header("Last-Modified: ". gmdate("D, d M Y H:i:s"). " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


include_once("config.php");
include_once(INSTALL_PATH . "/DBRecord.class.php" );
include_once(INSTALL_PATH . "/reclib.php" );
include_once(INSTALL_PATH . "/Settings.class.php" );

$settings = Settings::factory();

if( ! isset( $_GET['reserve_id'] )) jdialog("予約番号が指定されていません", "recordedTable.php");
$reserve_id = $_GET['reserve_id'];


try{
	$rrec = new DBRecord( RESERVE_TBL, "id", $reserve_id );

	$start_time = toTimestamp($rrec->starttime);
	$end_time = toTimestamp($rrec->endtime );
	$duration = $end_time - $start_time;
	
	header('Content-type: video/mpeg');
	header('Content-Disposition: inline; filename="'.$rrec->path.'"');
	// 動画のサイズを取得してLengthにしてないのはなぜ？
	//$size = 3 * 1024 * 1024 * $duration;    // 1秒あたり3MBと仮定
	//header('Content-Length: ' . $size );

	// 間違ったContent-Lengthはコネクション切断がされなくなったり
	// 途中で切られてしまうかもしれないからいっそのこと chunked 送信にしよう。
	header('Transfer-Encoding: chunked');
	
	flush();
	
	$fp = @fopen( INSTALL_PATH.$settings->spool."/".$rrec->path, "r" );
	if( $fp !== false ) {
		do {
			$start = microtime(true);
			if( feof( $fp ) ) break;
			$buf = fread( $fp, 6292 );
			// output chunk size
			echo sprintf("%x\r\n", strlen($buf));
			// output buffer
			echo $buf . "\r\n";
			@usleep( 2000 - (int)((microtime(true) - $start) * 1000 * 1000));
			flush();
		}
		while( connection_aborted() == 0 );
	}
	fclose($fp);
	echo "0\r\n\r\n";
}
catch(exception $e ) {
	exit( $e->getMessage() );
}
?>
