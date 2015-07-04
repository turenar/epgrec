#!/usr/bin/php
<?php
$script_path = dirname( __FILE__ );
chdir( $script_path );
include_once( $script_path . '/config.php');
include_once(INSTALL_PATH.'/DBRecord.class.php');
include_once(INSTALL_PATH.'/reclib.php');
include_once(INSTALL_PATH.'/Settings.class.php');

$settings = Settings::factory();

try {

  $recs = DBRecord::createRecords(RESERVE_TBL );

// DB接続
  $dbh = mysqli_connect( $settings->db_host, $settings->db_user, $settings->db_pass );
  if( mysqli_connect_error() ) exit( "mysql connection fail: ".mysqli_connect_error() );
  mysqli_select_db($dbh, $settings->db_name);
  mysqli_set_charset($dbh, 'utf8');

  foreach( $recs as $rec ) {
	  $title = mysqli_real_escape_string($dbh, $rec->title)."(".date("Y/m/d", toTimestamp($rec->starttime)).")";
      $sqlstr = "update mt_cds_object set metadata='dc:description=".mysqli_real_escape_string($dbh, $rec->description)."&epgrec:id=".$rec->id."' where dc_title='".$rec->path."'";
      mysqli_query( $dbh, $sqlstr );
      $sqlstr = "update mt_cds_object set dc_title='".$title."' where dc_title='".$rec->path."'";
      mysqli_query( $dbh, $sqlstr );
  }
}
catch( Exception $e ) {
    exit( $e->getMessage() );
}
?>
