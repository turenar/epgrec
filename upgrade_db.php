#!/usr/bin/php
<?php
$script_path = dirname( __FILE__ );
chdir( $script_path );
include_once($script_path . '/config.php');
include_once(INSTALL_PATH . '/Settings.class.php' );
include_once(INSTALL_PATH . '/DBRecord.class.php' );
include_once(INSTALL_PATH . '/tableStruct.inc.php' );
include_once(INSTALL_PATH . '/reclib.php' );

$settings = Settings::factory();
$dbh = mysql_connect( $settings->db_host, $settings->db_user, $settings->db_pass );
if( $dbh !== FALSE ) {

	$sqlstr = "use ".$settings->db_name;
	mysql_query( $sqlstr );

	$sqlstr = "set NAMES 'utf8'";
	mysql_query( $sqlstr );

	// KEYWORD_TBL
	mysql_query( "ALTER TABLE ".$settings->tbl_prefix.KEYWORD_TBL." ADD split_time integer not null default '0' AFTER overlap" );
	mysql_query( "ALTER TABLE ".$settings->tbl_prefix.KEYWORD_TBL." ADD duration_chg boolean not null default '0' AFTER discontinuity " );

	// PROGRAM_TBL
	mysql_query( "ALTER TABLE ".$settings->tbl_prefix.PROGRAM_TBL." ADD split_time integer not null default '0' AFTER key_id" );
	mysql_query( "ALTER TABLE ".$settings->tbl_prefix.PROGRAM_TBL." ADD rec_ban_parts integer not null default '0' AFTER split_time" );
}
else
	exit( "DBの接続に失敗\n" );
?>
