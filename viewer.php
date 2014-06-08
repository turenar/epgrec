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

	if( isset( $_GET['trans'] ) ){
		$trans_set = new DBRecord( TRANSCODE_TBL, 'id', $_GET['trans'] );
		if( strncmp( $trans_set->path, INSTALL_PATH, strlen(INSTALL_PATH) ) )
			jdialog( 'URLルートで始まるパスではないので視聴が出来ません<br>'.$trans_set->path, 'recordedTable.php' );
		$target_path = substr( $trans_set->path, strlen(INSTALL_PATH)+1 );
		$steam_mode  = $trans_set->status==1;
	}else{
		$target_path = $settings->spool.'/'.$rrec->path;
		$steam_mode  = $rrec->complete==0;
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
	
	header('Content-type: video/x-ms-asf; charset="UTF-8"');
	header('Content-Disposition: inline; filename="'.$rrec->path.'.asx"');
	echo '<ASX version = "3.0">';
	echo '<PARAM NAME = "Encoding" VALUE = "UTF-8" />';
	echo '<ENTRY>';
	if( $steam_mode )
		echo '<REF HREF="'.$settings->install_url.'/sendstream.php?reserve_id='.$rrec->id.(isset($_GET['trans']) ? '&trans='.$_GET['trans'] : '').'" />';
	else{
		$paths = explode( '/', $target_path );
		$path  = '';
		foreach( $paths as $part ){
//			if( $part != '' )
				$path .= '/'.rawurlencode( $part );
		}
		echo '<REF HREF="'.$settings->install_url.$path.'" />';
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
