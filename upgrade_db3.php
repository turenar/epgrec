#!/usr/bin/php
<?php
$script_path = dirname( __FILE__ );
chdir( $script_path );
include_once($script_path . '/config.php');
include_once(INSTALL_PATH . '/Settings.class.php' );
include_once(INSTALL_PATH . '/DBRecord.class.php' );
include_once(INSTALL_PATH . '/tableStruct.inc.php' );

$settings = Settings::factory();
$dbh = mysqli_connect( $settings->db_host, $settings->db_user, $settings->db_pass );
if( $dbh !== FALSE ) {

	$sqlstr = "use ".$settings->db_name;
	mysqli_query( $dbh, $sqlstr );

	$sqlstr = "set NAMES 'utf8'";
	mysqli_query( $dbh, $sqlstr );

	// インデックス追加
	// RESERVE_TBL
	mysqli_query( $dbh, "ALTER TABLE ".$settings->tbl_prefix.RESERVE_TBL." add sub_genre integer not null default '16' AFTER category_id" );
	$resobj = new DBRecord(RESERVE_TBL);
	$prgobj = new DBRecord(PROGRAM_TBL);
	$recs = $resobj->fetch_array( 'complete', 0 );
	foreach( $recs as $rec ) {
		if( $rec['program_id'] > 0 ){
			$prg = $prgobj->fetch_array( 'id', $rec['program_id'] );
			$wrt_set['sub_genre'] = $prg[0]['sub_genre'];
			$resobj->force_update( $rec['id'], $wrt_set );
		}
	}
}
else
	exit( "DBの接続に失敗\n" );
?>
