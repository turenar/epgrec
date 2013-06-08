<?php
include_once('config.php');
include_once(INSTALL_PATH."/DBRecord.class.php");
include_once(INSTALL_PATH."/reclib.php");
include_once(INSTALL_PATH."/Settings.class.php");

$settings = Settings::factory();

if( !isset( $_POST['reserve_id'] ) ) {
	exit("Error: IDが指定されていません" );
}
$reserve_id = $_POST['reserve_id'];

$dbh = false;
if( $settings->mediatomb_update == 1 ) {
	$dbh = @mysql_connect( $settings->db_host, $settings->db_user, $settings->db_pass );
	if( $dbh !== false ) {
		mysql_select_db($settings->db_name);
		mysql_set_charset('utf8');
	}
}

try {
	$rec = new DBRecord(RESERVE_TBL, "id", $reserve_id );
	
	if( isset( $_POST['title'] ) ) {
		$rec->title = trim( $_POST['title'] );
		$rec->dirty = 1;
		if( ($dbh !== false) && ($rec->complete == 1) ) {
			$title = trim( mysql_real_escape_string($_POST['title']));
			$title .= "(".date("Y/m/d", toTimestamp($rec->starttime)).")";
			$sqlstr = "update mt_cds_object set dc_title='".$title."' where metadata regexp 'epgrec:id=".$reserve_id."$'";
			@mysql_query( $sqlstr );
		}
	}
	
	if( isset( $_POST['description'] ) ) {
		$rec->description = trim( $_POST['description'] );
		$rec->dirty = 1;
		if( ($dbh !== false) && ($rec->complete == 1) ) {
			$desc = "dc:description=".trim( mysql_real_escape_string($_POST['description']));
			$desc .= "&epgrec:id=".$reserve_id;
			$sqlstr = "update mt_cds_object set metadata='".$desc."' where metadata regexp 'epgrec:id=".$reserve_id."$'";
			@mysql_query( $sqlstr );
		}
	}
}
catch( Exception $e ) {
	exit("Error: ". $e->getMessage());
}

exit("complete");

?>
