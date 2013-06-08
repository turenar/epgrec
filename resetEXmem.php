#!/usr/bin/php
<?php
  $script_path = dirname( __FILE__ );
  chdir( $script_path );
  include_once( $script_path . '/config.php');
  include_once( INSTALL_PATH . '/Settings.class.php' );
	include_once( INSTALL_PATH . '/reclib.php' );

	$settings = Settings::factory();

	while(1){
		$shm_id = shm_attach( 2 );
		if( $shm_id === FALSE )
			usleep( 100 );
		else
			break;
	}
	if( isset( $argv[1] ) ){
		$val = isset( $argv[2] ) ? (int)$argv[2] : 0;
		shm_put_var_surely( $shm_id, $argv[1], $val );
	}else{
		for( $tuner=0; $tuner<$settings->gr_tuners;$tuner++ ){
			shm_put_var_surely( $shm_id, 1+$tuner, 0 );
		}
		for( $tuner=0; $tuner<$settings->bs_tuners;$tuner++ ){
			shm_put_var_surely( $shm_id, 21+$tuner, 0 );
		}
		for( $tuner=0; $tuner<10;$tuner++ ){
			shm_put_var_surely( $shm_id, 40+$tuner, 0 );
		}
	}
	shm_detach( $shm_id );
	exit();
?>
