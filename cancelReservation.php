<?php
include_once('config.php');
include_once( INSTALL_PATH . '/DBRecord.class.php' );
include_once( INSTALL_PATH . '/Reservation.class.php' );
include_once( INSTALL_PATH . '/reclib.php' );
include_once( INSTALL_PATH . '/Settings.class.php' );

$program_id = 0;
$reserve_id = 0;
$settings = Settings::factory();

if( isset($_GET['reserve_id']) ){
	$reserve_id = $_GET['reserve_id'];
	$db_clean   = isset($_GET['db_clean']) ? (boolean)$_GET['db_clean'] : FALSE;
	try{
		$rev_obj    = new DBRecord( RESERVE_TBL );
		$rec        = $rev_obj->fetch_array( 'id', $reserve_id );
		$program_id = $rec[0]['program_id'];

		try{
			$ret_code = Reservation::cancel( $reserve_id, $program_id, $db_clean );
		}
		catch( Exception $e ){
			exit( 'Error' . $e->getMessage() );
		}

		if( isset( $_GET['delete_file'] ) && (int)$_GET['delete_file']==1 ){
			$trans_obj = new DBRecord( TRANSCODE_TBL );
			$del_trans = $trans_obj->fetch_array( null, null, 'rec_id='.$reserve_id.' ORDER BY status' );
			foreach( $del_trans as $del_file ){
				switch( $del_file['status'] ){
					case 1:		// 処理中(0は処理済)
						$ps_output = shell_exec( PS_CMD );
						$rarr      = explode( "\n", $ps_output );
						killtree( $rarr, (int)$del_file['pid'] );
						sleep(1);
						break;
					case 2:		// 正常終了
					case 3:		// 異常終了
						if( file_exists( $del_file['path'] ) )
							@unlink( $del_file['path'] );
						break;
				}
				$trans_obj->force_delete( $del_file['id'] );
			}
			// ファイルを削除
			$reced = INSTALL_PATH.$settings->spool.'/'.$rec[0]['path'];
			if( file_exists( $reced ) )
				@unlink( $reced );
		}
		// サムネイル削除
		$thumbs = INSTALL_PATH.$settings->thumbs.'/'.end(explode( '/', $rec[0]['path'] )).'.jpg';
		if( file_exists( $thumbs ) )
			@unlink( $thumbs );
	}
	catch( Exception $e ){
		exit( 'Error' . $e->getMessage() );
	}
}else if( isset($_GET['program_id']) ){
	$program_id = $_GET['program_id'];
	// 予約取り消し実行
	try{
		$ret_code = Reservation::cancel( 0, $program_id );
	}
	catch( Exception $e ){
		exit( 'Error' . $e->getMessage() );
	}
}else
	exit( 'error:no id' );

// 自動録画対象フラグ変更
if( isset($_GET['autorec']) ){
	$autorec = $_GET['autorec'];
	if( $program_id ){
		try{
			$rec = new DBRecord(PROGRAM_TBL, 'id', $program_id );
			$rec->autorec = $autorec ? 0 : 1;
			$rec->update();
		}
		catch( Exception $e ){
			// 無視
		}
	}
}
exit($ret_code);
?>
