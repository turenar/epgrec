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
  $dbh = mysql_connect( $settings->db_host, $settings->db_user, $settings->db_pass );
  if( $dbh === false ) exit( "mysql connection fail" );
  mysql_select_db($settings->db_name);
  mysql_set_charset('utf8');

  foreach( $recs as $rec ) {
	  $title = mysql_real_escape_string($rec->title)."(".date("Y/m/d", toTimestamp($rec->starttime)).")";
      $sqlstr = "update mt_cds_object set metadata='dc:description=".mysql_real_escape_string($rec->description)."&epgrec:id=".$rec->id."' where dc_title='".$rec->path."'";
      mysql_query( $sqlstr );
      $sqlstr = "update mt_cds_object set dc_title='".$title."' where dc_title='".$rec->path."'";
      mysql_query( $sqlstr );
  }
}
catch( Exception $e ) {
    exit( $e->getMessage() );
}
?>
