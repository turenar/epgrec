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

	header('Content-type: video/x-ms-asf; charset="UTF-8"');
	header('Content-Disposition: inline; filename="'.$rrec->path.'.asx"');
	echo '<ASX version = "3.0">';
	echo '<PARAM NAME = "Encoding" VALUE = "UTF-8" />';
	echo '<ENTRY>';
	if( $NET_AREA==='G' && strpos( $settings->install_url, '://192.168.' )===FALSE && strpos( $settings->install_url, '://localhost/' )===FALSE ){
		$url_parts = parse_url( $settings->install_url );
		$scheme    = $url_parts['scheme'].'://';
		$view_url  = $url_parts['host'];
		if( isset( $url_parts['port'] ) )
			$port = $url_parts['port'];
	}else{
		$scheme = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ) ? 'https://' : 'http://';
		if( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '://' ) !== FALSE ){
			$url_parts = parse_url( $_SERVER['HTTP_REFERER'] );
			$view_url  = $url_parts['host'];
			if( isset( $url_parts['port'] ) )
				$port = $url_parts['port'];
		}else{
			if( isset( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST']!=='' )
				$view_url = $_SERVER['HTTP_HOST'];
			else
				if( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '://' )!==FALSE ){
					$url_parts = parse_url( $_SERVER['REQUEST_URI'] );
					$view_url  = $url_parts['host'];
					if( isset( $url_parts['port'] ) )
						$port = $url_parts['port'];
				}else
					if( $NET_AREA==='G' && get_net_area( $_SERVER['SERVER_ADDR'] )!=='G' ){
						$name_stat = get_net_area( $_SERVER['SERVER_NAME'] );
						if( $name_stat==='T' || $name_stat==='G' ){
							$view_url = $_SERVER['SERVER_NAME'];
							$port     = $_SERVER['SERVER_PORT'];
						}else{
							// ここは適当 たぶんダメ
							if( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
								$view_url = $_SERVER['REMOTE_ADDR'];	// proxy
								$port     = $_SERVER['REMOTE_PORT'];
							}else{
								$view_url = $_SERVER['SERVER_ADDR'];	// NAT
								$port     = $_SERVER['SERVER_PORT'];
							}
						}
					}else{
						$view_url = $_SERVER['SERVER_ADDR'];
						$port     = $_SERVER['SERVER_PORT'];
					}
		}
	}
	if( STREAMURL_INC_PW && $AUTHORIZED && isset($_SERVER['PHP_AUTH_USER']) )
		$scheme .= $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'].'@';
	if( strpos( $view_url, ':' )!==FALSE &&  strpos( $view_url, '[' )===FALSE )
		$view_url = '['.$view_url.']';
	if( isset($port) && $port !== '80' )
		$view_url .= ':'.$port;
	$part_path = explode( '/', $_SERVER['PHP_SELF'] );
	array_pop( $part_path );
	$base_path = implode( '/', $part_path );
	if( $stream_mode )
		echo '<REF HREF="'.$scheme.$view_url.$base_path.'/sendstream.php?reserve_id='.$rrec->id.$trans_op.'" />';
	else{
		$paths = explode( '/', $target_path );
		$path  = '';
		foreach( $paths as $part ){
			if( $part !== '' )
				$path .= '/'.rawurlencode( $part );
		}
		echo '<REF HREF="'.$scheme.$view_url.$base_path.$path.'" />';
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
