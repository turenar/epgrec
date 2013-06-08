#!/usr/bin/php
<?php
  $script_path = dirname( __FILE__ );
  chdir( $script_path );
  include_once( $script_path . '/config.php');
  include_once( INSTALL_PATH . '/DBRecord.class.php' );
  include_once( INSTALL_PATH . '/Reservation.class.php' );
  include_once( INSTALL_PATH . '/Keyword.class.php' );
  include_once( INSTALL_PATH . '/Settings.class.php' );
  include_once( INSTALL_PATH . '/storeProgram.inc.php' );
  include_once( INSTALL_PATH . '/recLog.inc.php' );

	while(1){
		$shm_id = shm_attach( 2 );
		if( $shm_id === FALSE )
			usleep( 100 );
		else
			break;
	}
  doKeywordReservation( '*', $shm_id );	// キーワード予約
  shm_detach( $shm_id );
  exit();
?>
