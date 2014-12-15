#!/usr/bin/php
<?php
	exit( function_exists( 'shmop_open' ).':'.function_exists( 'sem_get' ).':'.function_exists( 'pcntl_setpriority' ) );
?>
