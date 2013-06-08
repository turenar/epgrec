<?php
//include_once( INSTALL_PATH . '/config.php');

// ライブラリ

  if( ! defined( 'EPERM' )  ) define( 'EPERM',  '1'  );
  if( ! defined( 'ESRCH' )  ) define( 'ESRCH',  '3'  );

function toTimestamp( $string ) {
	sscanf( $string, '%4d-%2d-%2d %2d:%2d:%2d', $y, $mon, $day, $h, $min, $s );
	return mktime( $h, $min, $s, $mon, $day, $y );
}

function toDatetime( $timestamp ) {
	return date('Y-m-d H:i:s', $timestamp);
}


function jdialog( $message, $url = "index.php" ) {
    header( "Content-Type: text/html;charset=utf-8" );
    exit( "<script type=\"text/javascript\">\n" .
          "<!--\n".
         "alert(\"". $message . "\");\n".
         "window.open(\"".$url."\",\"_self\");".
         "// -->\n</script>" );
}

// マルチバイトstr_replace

function mb_str_replace($search, $replace, $target, $encoding = "UTF-8" ) {
	$notArray = !is_array($target) ? TRUE : FALSE;
	$target = $notArray ? array($target) : $target;
	$search_len = mb_strlen($search, $encoding);
	$replace_len = mb_strlen($replace, $encoding);
	
	foreach ($target as $i => $tar) {
		$offset = mb_strpos($tar, $search);
		while ($offset !== FALSE){
			$tar = mb_substr($tar, 0, $offset).$replace.mb_substr($tar, $offset + $search_len);
			$offset = mb_strpos($tar, $search, $offset + $replace_len);
		}
		$target[$i] = $tar;
	}
	return $notArray ? $target[0] : $target;
}


// psのレコードからトークン切り出し
function ps_tok( $src ){
	$ps_tk->uid   = strtok( $src, " \t" );
	$ps_tk->pid   = strtok( " \t" );
	$ps_tk->ppid  = strtok( " \t" );
	$ps_tk->tok   = strtok( " \t" );
	$ps_tk->stime = strtok( " \t" );
	return $ps_tk;
}


// 指定予約のdo_record.shのpsレコード取得
function search_reccmd( $rec_id ){
	$ps_output = shell_exec( PS_CMD );
	$rarr = explode( "\n", $ps_output );
	$catch_cmd = DO_RECORD.' '.$rec_id;
	for( $cc=0; $cc<count($rarr); $cc++ ){
		if( strpos( $rarr[$cc], $catch_cmd ) !== FALSE ){
			$ps = ps_tok( $rarr[$cc] );
			do{
				$cc++;
				$c_ps = ps_tok( $rarr[$cc] );
				if( $ps->pid == $c_ps->ppid ){
					return $c_ps;
				}
			}while( $cc < count($rarr) );
		}
	}
	return FALSE;
}

function shm_put_var_surely( $shm_id, $shm_name, $sorce ){
	while(1){
		while( shm_put_var( $shm_id, $shm_name, $sorce ) === FALSE )
			usleep( 100 );
		if( shm_get_var( $shm_id, $shm_name ) !== $sorce )
			usleep( 100 );
		else
			break;
	}
}

function putProgramHtml( $src, $type, $channel_id, $genre, $sub_genre ){
	if( $src !== "" ){
		$temp = trim($src);
		if( strncmp( $temp, '[￥]', 5 ) == 0 ){
			$out_title = substr( $temp, 5 );
		}else
			$out_title = $temp;
		if( strpos( $out_title, ' #' ) === FALSE ){
			$delimiter = strpos( $out_title, '「' )===FALSE ? "" : '「';
		}else
			$delimiter = ' #';
		if( $delimiter !== "" ){
			$keyword = explode( $delimiter, $out_title );
			if( $keyword[0] === "" )
				$keyword[0] = $out_title;
		}else
			$keyword[0] = $out_title;
		return 'programTable.php?search='.rawurlencode(str_replace( ' ', '%', $keyword[0] )).'&type='.$type.'&station='.$channel_id.'&category_id='.$genre.'&sub_genre='.$sub_genre;
	}else
		return "";
}

function parse_time( $time_char )
{
	$time_stk = $cnt = 0;
	if( strncmp( $time_char, '-', 1 ) == 0 ){
		$flag = -1;
		$time_char = substr( $time_char, 1 );
	}else
		$flag = 1;
	$times = explode( ':', $time_char );
	switch( count( $times ) ){
		case 1:
			$time_stk = (int)($times[0] * 60);
			break;
		case 3:
			$time_stk = (int)$times[$cnt++] * 60;
		case 2:
			$time_stk += (int)$times[$cnt++];
			$time_stk *= 60;
			$time_stk += (int)$times[$cnt];
			break;
	}
	return $time_stk * $flag;
}

function transTime( $second, $view=FALSE )
{
	if( $second < 0 ){
		$second *= -1;
		$flag = '-';
	}else
		$flag = "";
	if( $second % 60 || $view )
		return $flag.sprintf( '%02d:%02d:%02d', $second/3600, (int)($second/60)%60, $second%60 );
	else
		return $flag.($second/60);
}

function get_device_name( $dvnum )
{
	$drtype = $dvnum >> 8;
	$drnum  = $dvnum & 0x0ff;
	if( $drtype ){
		// 環境依存かも・・・
		$rd_arr = file( '/sys/dev/block/'.$drtype.':'.$drnum.'/uevent', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if( $rd_arr !== FALSE ){
			foreach( $rd_arr as $rd_tg ){
				if( strncmp( $rd_tg, 'DEVNAME=', 8 ) == 0 )
					return '/dev/'.substr( $rd_tg, 8 );
			}
		}
		return $drtype==8 ? '/dev/sd'.chr(0x61+($drnum>>4)).($drnum&0x0f) : $drtype.':'.$drnum;
	}else
		return 'tmpfs(0:'.$drnum.')';
}
?>
