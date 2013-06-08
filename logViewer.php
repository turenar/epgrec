<?php
include_once('config.php');
include_once( INSTALL_PATH . '/DBRecord.class.php' );
include_once( INSTALL_PATH . '/Smarty/Smarty.class.php' );
include_once( INSTALL_PATH . '/Settings.class.php' );
include_once( INSTALL_PATH . '/settings/menu_list.php' );


$settings = Settings::factory();

$arr = DBRecord::createRecords( LOG_TBL, " ORDER BY logtime DESC, id DESC" );

if( (int)$settings->bs_tuners > 0 )
	$link_add = $settings->cs_rec_flg==0 ? 1 : 2;
else
	$link_add = 0;

$smarty = new Smarty();

$smarty->assign( "sitetitle" , "epgrec動作ログ" );
$smarty->assign( "logs", $arr );
$smarty->assign( "link_add", $link_add );
$smarty->assign( 'menu_list', $MENU_LIST );

$smarty->display( "logTable.html" );
?>