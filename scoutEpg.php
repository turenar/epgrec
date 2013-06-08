#!/usr/bin/php
<?php
  $script_path = dirname( __FILE__ );
  chdir( $script_path );
  include_once( $script_path . '/config.php');
  include_once( INSTALL_PATH . '/DBRecord.class.php' );
  include_once( INSTALL_PATH . '/Settings.class.php' );
  include_once( INSTALL_PATH . '/Reservation.class.php' );
  include_once( INSTALL_PATH . '/storeProgram.inc.php' );
	include_once( INSTALL_PATH . '/reclib.php' );
  include_once( INSTALL_PATH . '/recLog.inc.php' );

	$settings = Settings::factory();

function search_getepg()
{
	$ps_output = shell_exec( PS_CMD." 2>/dev/null" );
	$rarr = explode( "\n", $ps_output );
	$catch_cmd = INSTALL_PATH.'/getepg.php';
	for( $cc=0; $cc<count($rarr); $cc++ ){
		if( strpos( $rarr[$cc], $catch_cmd ) !== FALSE )
			return TRUE;
	}
	return FALSE;
}

function sig_handler()
{
	global	$shm_name,$temp_xml,$temp_ts;

	// シャットダウンの処理
	if( isset( $shm_name ) ){
		//共有メモリー変数初期化
		$shm_id = shm_attach( 2 );
		if( shm_get_var( $shm_id, $shm_name ) ){
			shm_put_var_surely( $shm_id, $shm_name, 0 );
		}
		shm_detach( $shm_id );
		//テンポラリーファイル削除
		if( isset( $temp_ts ) && file_exists( $temp_ts ) )
			@unlink( $temp_ts );
		if( isset( $temp_xml ) && file_exists( $temp_xml ) )
			@unlink( $temp_xml );
	}
	exit;
}

	// シグナルハンドラを設定
	declare( ticks = 1 );
	pcntl_signal( SIGTERM, "sig_handler" );

	if( $argc == 2 ){
		$rev    = new DBRecord( RESERVE_TBL, "id", $argv[1] );
		$lmt_tm = toTimestamp( $rev->starttime ) - $settings->rec_switch_time - $settings->former_time - 2;
	}else{
		$rev    = new DBRecord( CHANNEL_TBL, "id", $argv[1] );
		$lmt_tm = time() + ( $argv[2]==1 ? FIRST_REC : SHORT_REC ) + $settings->rec_switch_time + $settings->former_time + 2;
	}
	$type     = $rev->type;		//GR/BS/CS
	$value    = $rev->channel;
	$ch_disc  = $type==='GR' ? strtok( $rev->channel_disc, '_' ) : '/'.$type;
	$rec_tm   = FIRST_REC;
	$pid      = posix_getpid();
	$temp_xml = $settings->temp_xml.$type.'_'.$pid;
	$temp_ts  = $settings->temp_data.'_'.$type.'_'.$pid;

	if( $type === 'GR' ){
		$smf_type = 'GR';
		$sql_type = "type = 'GR'";
		$smf_key  = 1;
		$tuners   = (int)$settings->gr_tuners;
	}else{
		$smf_type = 'BS';
		$sql_type = "(type = 'BS' OR type = 'CS')";
		$smf_key  = 21;
		$tuners   = (int)$settings->bs_tuners;
		strtok( $rev->channel_disc, '_' );
		$sid = strtok( '_' );
	}
	for( $sem_cnt=0; $sem_cnt<$tuners; $sem_cnt++ )
		while(1){
			$sem_id[$sem_cnt] = sem_get( $sem_cnt+$smf_key );
			if( $sem_id[$sem_cnt] === FALSE )
				usleep( 100 );
			else
				break;
		}
	while(1){
		$shm_id = shm_attach( 2 );
		if( $shm_id === FALSE )
			usleep( 100 );
		else
			break;
	}
	while(1){
		$sem_dump = sem_get( 40, 1, 0666 );
		if( $sem_dump === FALSE )
			usleep( 100 );
		else
			break;
	}
	while(1){
		$sem_store = sem_get( 41, 1, 0666 );
		if( $sem_store === FALSE )
			usleep( 100 );
		else
			break;
	}
	if( $argc == 2 ){
		// リアルタイム視聴チューナー事前開放
		$slc_tuner = $rev->tuner;		// 録画に使用するチューナー
		while(1){
			if( sem_acquire( $sem_id[$slc_tuner] ) === TRUE ){
				$shm_name = $smf_key + $slc_tuner;
				if( shm_has_var( $shm_id, $shm_name ) === TRUE ){
					$smph = shm_get_var( $shm_id, $shm_name );
					if( $smph == 2 ){
						// リアルタイム視聴停止
						$real_view = (int)trim( file_get_contents( REALVIEW_PID ) );
						posix_kill( $real_view, 9 );		// 録画コマンド停止
						shm_put_var_surely( $shm_id, $shm_name, 0 );		// リアルタイム視聴停止
						shm_put_var_surely( $shm_id, 42, 0 );		// リアルタイム視聴tunerNo clear
					}
				}
				while( sem_release( $sem_id[$slc_tuner] ) === FALSE )
					usleep( 100 );
				break;
			}
		}
	}
if( search_getepg() === FALSE ){
	while( time() < $lmt_tm ){
		while(1){
			$epg_tm  = $rec_tm + $settings->rec_switch_time;
			$wait_lp = $lmt_tm - time();
			if( $wait_lp > $epg_tm )
				$wait_lp = $epg_tm;
			else
				if( $wait_lp < $epg_tm ){
					if( $rec_tm == FIRST_REC ){
						$rec_tm = SHORT_REC;
						continue;
					}else
						break 2;
				}
			break;
		}
		$sql_cmd    = "WHERE complete = '0' AND ".$sql_type.' AND endtime > subtime( now(), sec_to_time('.($settings->extra_time+2).') ) AND starttime < addtime( now(), sec_to_time('.$epg_tm.') )';
		$off_tuners = DBRecord::countRecords( RESERVE_TBL, $sql_cmd );
		if( $off_tuners < $tuners ){
			//空チューナー降順探索
			$revs = DBRecord::createRecords( RESERVE_TBL, $sql_cmd );
			for( $slc_tuner=$tuners-1; $slc_tuner>=0; $slc_tuner-- ){
				for( $cnt=0; $cnt<$off_tuners; $cnt++ ){
					if( $revs[$cnt]->tuner == $slc_tuner )
						continue 2;
				}
				if( sem_acquire( $sem_id[$slc_tuner] ) === TRUE ){
					$shm_name = $smf_key + $slc_tuner;
					if( shm_has_var( $shm_id, $shm_name ) === TRUE ){
						$smph = shm_get_var( $shm_id, $shm_name );
						if( $smph==2 && $tuners-$off_tuners==1 ){
							// リアルタイム視聴停止
							$real_view = (int)trim( file_get_contents( REALVIEW_PID ) );
							posix_kill( $real_view, 9 );		// 録画コマンド停止
							$smph = 0;
							shm_put_var_surely( $shm_id, 42, 0 );		// リアルタイム視聴tunerNo clear
						}
					}else
						$smph = 0;
					if( $smph == 0 ){
						shm_put_var_surely( $shm_id, $shm_name, 1 );
						while( sem_release( $sem_id[$slc_tuner] ) === FALSE )
							usleep( 100 );
						sleep( (int)$settings->rec_switch_time );
						if( ( $slc_tuner<TUNER_UNIT1 && RECPT1_EPG_PATCH ) || ( $slc_tuner>=TUNER_UNIT1 && $OTHER_TUNERS_CHARA["$smf_type"][$slc_tuner-TUNER_UNIT1]['epgTs'] ) )
							$cmdline = 'SID=epg ';
						else
							$cmdline = "";
						$cmdline .= 'CHANNEL='.$value.' DURATION='.$rec_tm.' TYPE='.$type.' TUNER_UNIT='.TUNER_UNIT1.' TUNER='.$slc_tuner.' MODE=0 OUTPUT='.$temp_ts.' '.DO_RECORD.' >/dev/null 2>&1';
						exec( $cmdline );
						//チューナー占有解除
						while( sem_acquire( $sem_id[$slc_tuner] ) === FALSE )
							usleep( 100 );
						shm_put_var_surely( $shm_id, $shm_name, 0 );
						while( sem_release( $sem_id[$slc_tuner] ) === FALSE )
							usleep( 100 );
						//
						if( file_exists( $temp_ts ) ){
							$cmdline = $settings->epgdump.' '.$ch_disc.' '.$temp_ts.' '.$temp_xml;
							if( $rec_tm == SHORT_REC )
								$cmdline .= ' -pf';
							if( $type !== 'GR' )
								$cmdline .= ' -sid '.$sid;
							while(1){
								if( sem_acquire( $sem_dump ) === TRUE ){
									exec( $cmdline );
									while( sem_release( $sem_dump ) === FALSE )
										usleep( 100 );
									@unlink( $temp_ts );
									break;
								}
								usleep(100 * 1000);
							}
							if( file_exists( $temp_xml ) ){
								while(1){
									if( sem_acquire( $sem_store ) === TRUE ){
										$ch_id = storeProgram( $type, $temp_xml );
										@unlink( $temp_xml );
										if( $ch_id !== -1 ){
											doKeywordReservation( $type, $shm_id );	// キーワード予約
											while( sem_release( $sem_store ) === FALSE )
												usleep( 100 );
											if( posix_getppid() == 1 )		//親死亡=予約取り消し
												break 3;
											//
											$wait_lp  = $lmt_tm - time();
											$short_tm = SHORT_REC + $settings->rec_switch_time;
											if( $short_tm > $wait_lp )
												break 3;
											$wait_lp -= $short_tm;
											if( $rec_tm == FIRST_REC ){
												$sleep_tm = 60 - time()%60;
												if( $sleep_tm == 60 )
													$sleep_tm = 30;
											}else
												$sleep_tm = 30 - time()%30;
											if( $sleep_tm > $settings->rec_switch_time )
												$sleep_tm -= $settings->rec_switch_time;
											else
												$sleep_tm = 0;
											sleep( $sleep_tm<$wait_lp ?  $sleep_tm : $wait_lp );		//killされた時に待たされる?
											// $info = array();
											// pcntl_sigtimedwait( array(SIGTERM), $info, $sleep_tm<$wait_lp ?  $sleep_tm : $wait_lp );
										}else
											while( sem_release( $sem_store ) === FALSE )
												usleep( 100 );
										continue 3;
									}
									usleep(100 * 1000);
								}
							}
						}
						continue 2;
					}
					//占有失敗
					while( sem_release( $sem_id[$slc_tuner] ) === FALSE )
						usleep( 100 );
				}
			}
			//時間切れ
		}else{
			//空チューナー無し
			//先行録画が同ChならそこからEPGを貰うようにしたい
			//また取れない場合もあるので録画冒頭でEID自家判定するしかない?
		}
		sleep(1);
	}
}
	shm_detach( $shm_id );
	exit();
?>
