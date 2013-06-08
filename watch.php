<?php
  $script_path = dirname( __FILE__ );
  chdir( $script_path );
  include_once( $script_path . '/config.php');
  include_once( INSTALL_PATH . '/DBRecord.class.php' );
  include_once( INSTALL_PATH . '/Settings.class.php' );
	include_once( INSTALL_PATH . '/reclib.php' );
	include_once( INSTALL_PATH . '/recLog.inc.php' );

	$settings = Settings::factory();

	$channel = $_GET['ch'];
	if( isset( $_GET['sid'] ) )
		$sid = $_GET['sid'];
	$GR_max = (int)$settings->gr_tuners;
	$TH_max = (int)$settings->bs_tuners;
	for( $sem_cnt=0; $sem_cnt<$GR_max; $sem_cnt++ )
		while(1){
			$sem_id[$sem_cnt+1] = sem_get( $sem_cnt+1 );
			if( $sem_id[$sem_cnt+1] === FALSE )
				usleep( 100 );
			else
				break;
		}
	for( $sem_cnt=0; $sem_cnt<$TH_max; $sem_cnt++ )
		while(1){
			$sem_id[$sem_cnt+21] = sem_get( $sem_cnt+21 );
			if( $sem_id[$sem_cnt+21] === FALSE )
				usleep( 100 );
			else
				break;
		}
	if( $channel!=='-' && isset( $_GET['type'] ) ){
		$type = substr( $_GET['type'], 0, 2 );			// index.htmlのchannel_discから流用してるため
		if( $type === 'GR' ){
			$sql_type = "type = 'GR'";
			$smf_key  = 1;
			$tuners   = $GR_max;
		}else{
			//BS/CS
			$sql_type = "(type = 'BS' OR type = 'CS')";
			$smf_key  = 21;
			$tuners   = $TH_max;
		}
	}else
		$type = "";
	while(1){
		$shm_id = shm_attach( 2 );
		if( $shm_id === FALSE )
			usleep( 100 );
		else
			break;
	}
	while(1){
		$rv_sem = sem_get( 42 );
		if( $rv_sem === FALSE )
			usleep( 100 );
		else
			break;
	}
//	@unlink( '/tmp/*.asx' );		// 所有権がapacheにない(ブラウザが所持)
	while(1){
		if( sem_acquire( $rv_sem ) === TRUE ){
			// リアルタイム視聴中確認
			if( shm_has_var( $shm_id, 42 ) === TRUE ){
				$rv_smph = shm_get_var( $shm_id, 42 );
				if( $rv_smph > 0 ){
					// 使用中チューナ仕様取得
					if( $rv_smph < 21 ){
						// GR
						$now_tuner = $rv_smph - 1;
						$now_type  = 'GR';
					}else{
						// satelite
						$now_tuner = $rv_smph - 21;
						$now_type  = 'BS';
					}
					$wave_disc = $type==='GR' ? 'GR' : 'BS';
					$ctl_chng  = FALSE;
					if( $channel === '-' )
						$tuner_stop = TRUE;
					else
						if( $now_tuner < TUNER_UNIT1 )
							$tuner_stop = !USE_RECPT1;			// DVBドライバーの対応は無し
						else
							if( $wave_disc === $now_type )
								if( $OTHER_TUNERS_CHARA["$now_type"][$now_tuner-TUNER_UNIT1]['httpS'] )
									$tuner_stop = FALSE;
								else
									if( $OTHER_TUNERS_CHARA["$now_type"][$now_tuner-TUNER_UNIT1]['cntrl'] ){
										$tuner_stop = FALSE;
										$ctl_chng   = TRUE;
									}else
										$tuner_stop = TRUE;
							else
								$tuner_stop = TRUE;
					$real_view = (int)trim( file_get_contents( REALVIEW_PID ) );
					// 録画コマンド常駐確認
					posix_kill( $real_view, 0 );
					$errno = posix_get_last_error();
					if( $errno==ESRCH || $tuner_stop ){
						unlink( REALVIEW_PID );
						if( $errno != ESRCH ){
							// 非httpサーバ化対応録画コマンド終了 or リアルタイム視聴終了
							if( posix_kill( $real_view, 9 ) ){		// 録画コマンド停止 cvlcは自動終了
								do{
									posix_kill( $real_view, 0 );
								}while( posix_get_last_error() != ESRCH );
							}else{
								$errno = posix_get_last_error();
								if( $errno != ESRCH ){
									// 録画コマンド非常駐以外
									while( sem_acquire( $sem_id[$rv_smph] ) === FALSE )
										usleep( 100 );
									shm_put_var_surely( $shm_id, $rv_smph, 0 );		// チューナー開放
									while( sem_release( $sem_id[$rv_smph] ) === FALSE )
										usleep( 100 );
									shm_put_var_surely( $shm_id, 42, 0 );		// リアルタイム視聴tunerNo Clear
									while( sem_release( $rv_sem ) === FALSE )
										usleep( 100 );
									shm_detach( $shm_id );
									reclog( 'watch.php::('.$errno.')'.posix_strerror( $errno ), EPGREC_WARN );
									exit( 0 );
								}
							}
						}
						if( $now_type === $type )
							sleep( (int)$settings->rec_switch_time );
						unset( $now_type );
						while( sem_acquire( $sem_id[$rv_smph] ) === FALSE )
							usleep( 100 );
						shm_put_var_surely( $shm_id, $rv_smph, 0 );		// チューナー開放
						while( sem_release( $sem_id[$rv_smph] ) === FALSE )
							usleep( 100 );
						shm_put_var_surely( $shm_id, 42, 0 );		// リアルタイム視聴tunerNo Clear
					}else{
						if( $now_type === $wave_disc ){
							// チューナ継続使用
							$slc_tuner = $now_tuner;
							// recpt1ctlによるチャンネル変更
							if( $ctl_chng ){
								$real_view = (int)trim( file_get_contents( REALVIEW_PID ) );
								exec( RECPT1_CTL.' --pid '.$real_view.' --channel '.$channel.' --sid '.$sid.' >/dev/null' );
							}
							goto OUTPUT;
						}
					}
				}
			}
			break;
		}
	}
	if( $channel === '-' ){
		while( sem_release( $rv_sem ) === FALSE )
			usleep( 100 );
		shm_detach( $shm_id );
		echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body onLoad="history.back()"></body></html>';

		exit( 1 );
	}

	$lp = 0;
	while(1){
		$sql_cmd    = "WHERE complete = '0' AND ".$sql_type." AND endtime > now() AND starttime < addtime( now(), '00:03:00' )";
		$off_tuners = DBRecord::countRecords( RESERVE_TBL, $sql_cmd );
		if( $off_tuners < $tuners ){
			//空チューナー降順探索
			$revs = DBRecord::createRecords( RESERVE_TBL, $sql_cmd );
			for( $slc_tuner=$tuners-1; $slc_tuner>=0; $slc_tuner-- ){
				for( $cnt=0; $cnt<$off_tuners; $cnt++ ){
					if( $revs[$cnt]->tuner == $slc_tuner )
						continue 2;
				}
				$shm_name = $smf_key + $slc_tuner;
				if( sem_acquire( $sem_id[$shm_name] ) === TRUE ){
					if( shm_has_var( $shm_id, $shm_name ) === TRUE ){
						$smph = shm_get_var( $shm_id, $shm_name );
					}else
						$smph = 0;
					if( $smph == 0 ){
						// recpt1常駐判定
						if( isset( $now_type ) ){
							if( $slc_tuner >= TUNER_UNIT1 ){
								// チューナー渡りのためリアルタイム視聴一時終了
								$real_view = (int)trim( file_get_contents( REALVIEW_PID ) );
								if( posix_kill( $real_view, 9 ) ){		// 録画コマンド停止 cvlcは自動終了
									do{
										posix_kill( $real_view, 0 );
									}while( posix_get_last_error() != ESRCH );
								}else{
									$errno = posix_get_last_error();
/*									echo $errno.': '.posix_strerror( $errno )."\n";
									while( sem_release( $rv_sem ) === FALSE )
										usleep( 100 );
									shm_detach( $shm_id );
									exit( 0 );
*/
									unlink( REALVIEW_PID );
									if( $errno != ESRCH ){
										// 録画コマンド非常駐以外
										while( sem_acquire( $sem_id[$rv_smph] ) === FALSE )
											usleep( 100 );
										shm_put_var_surely( $shm_id, $rv_smph, 0 );		// チューナー開放
										while( sem_release( $sem_id[$rv_smph] ) === FALSE )
											usleep( 100 );
										shm_put_var_surely( $shm_id, 42, 0 );		// リアルタイム視聴tunerNo Clear
										while( sem_release( $rv_sem ) === FALSE )
											usleep( 100 );
										shm_detach( $shm_id );
										reclog( 'watch.php::('.$errno.')'.posix_strerror( $errno ), EPGREC_WARN );
										exit( 0 );
									}
								}
								unset( $now_type );
							}
							while( sem_acquire( $sem_id[$rv_smph] ) === FALSE )
								usleep( 100 );
							shm_put_var_surely( $shm_id, $rv_smph, 0 );		// チューナー開放
							while( sem_release( $sem_id[$rv_smph] ) === FAlSE )
								usleep( 100 );
							shm_put_var_surely( $shm_id, 42, 0 );		// リアルタイム視聴tunerNo set
						}
						if( !isset( $now_type ) ){
							// リアルタイム視聴コマンド常駐
							$cmdline = 'CHANNEL='.$channel.' SID='.$sid." DURATION='-' TYPE=".$type.' TUNER_UNIT='.TUNER_UNIT1.' TUNER='.$slc_tuner." MODE=1 OUTPUT='-' ".DO_RECORD.' >/dev/null 2>&1';
							while(1){
								system( $cmdline );
								$real_cmd  = trim( file_get_contents( REALVIEW_PID.'_cmd' ) );
								$ps_output = shell_exec( PS_CMD );
								$rarr      = explode( "\n", $ps_output );
								for( $cc=0; $cc<count($rarr); $cc++ ){
									if( strpos( $rarr[$cc], $real_cmd ) !== FALSE ){
										$ps        = ps_tok( $rarr[$cc] );
										$real_view = (int)$ps->pid;
										// 常駐確認
										if( posix_kill( $real_view, 0 ) ){
											// 常駐成功
											unlink( REALVIEW_PID.'_cmd' );
											$handle = fopen( REALVIEW_PID, 'w' );
											fwrite( $handle, (string)$real_view );
											fclose( $handle );
											break 2;
										}else{
											$errno = posix_get_last_error();
											if( $errno == ESRCH )
												continue 2;		// retry
											else{
												reclog( 'watch.php::('.$errno.')'.posix_strerror( $errno ), EPGREC_WARN );
//												unlink( REALVIEW_PID.'_cmd' );
												while( sem_release( $sem_id[$shm_name] ) === FALSE )
													usleep( 100 );
												while( sem_release( $rv_sem ) === FALSE )
													usleep( 100 );
												shm_detach( $shm_id );
												echo '録画コマンドの常駐に失敗しました。';
												exit( 0 );
											}
										}
									}
								}
							}
						}
						shm_put_var_surely( $shm_id, $shm_name, 2 );		// リアルタイム視聴指示
						shm_put_var_surely( $shm_id, 42, $shm_name );		// リアルタイム視聴tunerNo set
						while( sem_release( $sem_id[$shm_name] ) === FALSE )
							usleep( 100 );
						break 2;
					}else
						//占有失敗
						while( sem_release( $sem_id[$shm_name] ) === FALSE )
							usleep( 100 );
				}
			}
		}
		if( $lp++ > 60 ){
			while( sem_release( $rv_sem ) === FALSE )
				usleep( 100 );
			shm_detach( $shm_id );
			echo '別処理でチューナーを使用中です。';
			exit( 1 );
		}
		sleep(1);
	}
OUTPUT:
	while( sem_release( $rv_sem ) === FALSE )
		usleep( 100 );
	shm_detach( $shm_id );

$asf_buf  = "<ASX version = \"3.0\">";
$asf_buf .= "<PARAM NAME = \"Encoding\" VALUE = \"UTF-8\" />";
$asf_buf .= "<ENTRY>";
$asf_buf .= "<TITLE>".$channel.":".$sid.' '.$_GET['name']."</TITLE>";
$now_type = $type==='GR' ? 'GR' : 'BS';
if( ( $slc_tuner<TUNER_UNIT1 && USE_RECPT1 ) || ( $slc_tuner>=TUNER_UNIT1 && $OTHER_TUNERS_CHARA["$now_type"][$slc_tuner-TUNER_UNIT1]['httpS'] ) )
	$asf_buf .= "<REF HREF=\"http://".$_SERVER['SERVER_NAME'].':'.REALVIEW_HTTP_PORT.'/'.$channel."/".$sid."\" />";
else
	$asf_buf .= "<REF HREF=\"http://".$_SERVER['SERVER_NAME'].':'.REALVIEW_HTTP_PORT."/\" />";
$asf_buf .= "</ENTRY>";
$asf_buf .= "</ASX>";
if( !isset( $_GET['mode'] ) ){
	header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
	header("Last-Modified: ". gmdate("D, d M Y H:i:s"). " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	header("Content-type: video/x-ms-asf; charset=\"UTF-8\"");
	header('Content-Disposition: inline; filename="'.$channel.'.asx"');
	echo $asf_buf;
}else{
	// 別PCからチャンネル変更をする試み（失敗）
	$asf_file_name = '/tmp/'.$channel.'.asx';
	file_put_contents ( $asf_file_name, $asf_buf );
	exec( 'sudo -u user-name vlc '.$asf_file_name );		// --playlist-enqueue ここがうまくいかない
}
exit( 1 );
?> 
