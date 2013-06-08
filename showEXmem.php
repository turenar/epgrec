#!/usr/bin/php
<?php
  $script_path = dirname( __FILE__ );
  chdir( $script_path );

	$cnt = 0;
	while(1){
		$shm_id = shm_attach( 2 );
		if( $shm_id === FALSE ){
			if( ++$cnt < 100000 )
				usleep( 100 );
			else{
				$errno = posix_get_last_error();
				echo "shm_attach() fault\n".$errno.': '.posix_strerror( $errno )."\n";
				$php_err = error_get_last();
				echo "type[".$php_err[type]."]::".$php_err[message]."(".$php_err[file]." line:".$php_err[line].")\n";
				exit();
			}
		}else
			break;
	}
	for( $tuner=0; $tuner<60; $tuner++ ){
		if( shm_has_var( $shm_id, $tuner ) === TRUE ){
			$rv_smph = shm_get_var( $shm_id, $tuner );
			if( $rv_smph )
				echo $tuner.'::'.$rv_smph."\n";
		}
	}
	shm_detach( $shm_id );
	exit();
?>
