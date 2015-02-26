<?php
header('Expires: Thu, 01 Dec 1994 16:00:00 GMT');
header('Last-Modified: '. gmdate('D, d M Y H:i:s'). ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');


include_once('config.php');
include_once(INSTALL_PATH . '/DBRecord.class.php' );
include_once(INSTALL_PATH . '/reclib.php' );
include_once(INSTALL_PATH . '/Settings.class.php' );

$settings = Settings::factory();

if( ! isset( $_GET['reserve_id'] )) jdialog('予約番号が指定されていません', 'recordedTable.php');
$reserve_id = $_GET['reserve_id'];

try{
	$rrec = new DBRecord( RESERVE_TBL, 'id', $reserve_id );

	if( isset( $_GET['trans_id'] ) ){
		$trans_set = new DBRecord( TRANSCODE_TBL, 'id', $_GET['trans_id'] );
		if( strncmp( $trans_set->path, INSTALL_PATH, strlen(INSTALL_PATH) ) )
			jdialog( 'URLルートで始まるパスではないので視聴が出来ません<br>'.$trans_set->path, 'recordedTable.php' );
		$target_path = substr( $trans_set->path, strlen(INSTALL_PATH)+1 );
		if( $trans_set->status == 1 ){
			$stream_mode = TRUE;
			$trans_op    = '&trans_id='.$_GET['trans'];
		}else{
			$stream_mode = FALSE;
			$trans_op    = '';
		}
	}else
		if( isset( $_GET['trans'] ) ){
			$stream_mode = TRUE;
			$trans_op    = '&trans='.$_GET['trans'];
		}else{
			$target_path = $settings->spool.'/'.$rrec->path;
			$stream_mode = $rrec->complete==0;
			$trans_op    = '';
		}

	$start_time = toTimestamp($rrec->starttime);
	$end_time = toTimestamp($rrec->endtime );
	$duration = $end_time - $start_time + $settings->former_time;

	$dh = $duration / 3600;
	$duration = $duration % 3600;
	$dm = $duration / 60;
	$duration = $duration % 60;
	$ds = $duration;
	
	$title = htmlspecialchars(str_replace(array("\r\n","\r","\n"), '', $rrec->title),ENT_QUOTES);
	$abstract = htmlspecialchars(str_replace(array("\r\n","\r","\n"), '', $rrec->description),ENT_QUOTES);
	if( strpos( $settings->install_url, 'http://192.168.' )!==FALSE && strpos( $settings->install_url, 'http://localhost' )!==FALSE ){
		$view_url = $settings->install_url;
	}else{
		$part_path = explode( '/', $_SERVER['PHP_SELF'] );
		array_pop( $part_path );
		$base_path = implode( '/', $part_path );
		$host_url = explode( $base_path, $_SERVER['HTTP_REFERER'] );		// SCRIPT_NAME -> HTTP_REFERER
		$view_url = $host_url[0].$base_path;
	}
	if( STREAMURL_INC_PW && $AUTHORIZED && isset($_SERVER['PHP_AUTH_USER']) )
		$view_url = str_replace( 'http://', 'http://'.$_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'].'@', $view_url );
	
	header('Content-type: video/x-ms-asf; charset="UTF-8"');
	header('Content-Disposition: inline; filename="'.$rrec->path.'.asx"');
	echo '<ASX version = "3.0">';
	echo '<PARAM NAME = "Encoding" VALUE = "UTF-8" />';
	echo '<ENTRY>';
	if( $stream_mode )
		echo '<REF HREF="'.$view_url.'/sendstream.php?reserve_id='.$rrec->id.$trans_op.'" />';
	else{
		$paths = explode( '/', $target_path );
		$path  = '';
		foreach( $paths as $part ){
			if( $part !== '' )
				$path .= '/'.rawurlencode( $part );
		}
		echo '<REF HREF="'.$view_url.$path.'" />';
	}
	echo '<TITLE>'.$title.'</TITLE>';
	echo '<ABSTRACT>'.$abstract.'</ABSTRACT>';
	echo '<DURATION VALUE=';
	echo '"'.sprintf( '%02d:%02d:%02d',$dh, $dm, $ds ).'" />';
	echo '</ENTRY>';
	echo '</ASX>';
}
catch(exception $e ) {
	exit( $e->getMessage() );
}
?>
