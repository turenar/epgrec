<?php
include_once('config.php');
include_once( INSTALL_PATH . '/DBRecord.class.php' );
include_once( INSTALL_PATH . '/Smarty/Smarty.class.php' );
include_once( INSTALL_PATH . '/Settings.class.php' );
include_once( INSTALL_PATH . '/Keyword.class.php' );
include_once( INSTALL_PATH . '/settings/menu_list.php' );

$settings = Settings::factory();

$options = " WHERE starttime > '".date("Y-m-d H:i:s", time() + 200 )."'";

// 曜日
$weekofdays = array(
					array( "name" => "月", "id" => 0, "selected" => "" ),
					array( "name" => "火", "id" => 1, "selected" => "" ),
					array( "name" => "水", "id" => 2, "selected" => "" ),
					array( "name" => "木", "id" => 3, "selected" => "" ),
					array( "name" => "金", "id" => 4, "selected" => "" ),
					array( "name" => "土", "id" => 5, "selected" => "" ),
					array( "name" => "日", "id" => 6, "selected" => "" ),
					array( "name" => "なし", "id" => 7, "selected" => "" ),
);
$week_tb = array( "日", "月", "火", "水", "木", "金", "土" );


$autorec_modes = $RECORD_MODE;

$enable = TRUE;
$search = "";
$use_regexp = 0;
$ena_title  = TRUE;
$ena_desc   = TRUE;
$type = "*";
$typeGR      = TRUE;
$typeBS      = TRUE;
$typeCS      = TRUE;
$first_genre = 1;
$category_id = 0;
$sub_genre   = 16;
$channel_id = 0;
$weekofday = 7;
$prgtime = 24;
$sft_start     = 0;
$sft_end       = 0;
$discontinuity = 0;
$priority      = 10;
$keyword_id    = 0;
$do_keyword = 0;
$filename = $settings->filename_format;
$spool    = $settings->spool."/";
$directory = "";

// パラメータの処理
if(isset( $_POST['do_search'] )) {
	if( isset($_POST['search'])){
		$search = $_POST['search'];
		if( isset($_POST['use_regexp']) && ($_POST['use_regexp']) ) {
			$use_regexp = (int)($_POST['use_regexp']);
		}
		if( !isset($_POST['ena_title'])){
			$ena_title = FALSE;
		}
		if( !isset($_POST['ena_desc'])){
			$ena_desc = FALSE;
		}
	}
	if( isset($_POST['enable'])) {
		if( !(boolean)$_POST['enable'] )
			$type = '-';
	}
	if( !isset($_POST['typeGR'])){
		$typeGR = FALSE;
	}
	if( !isset($_POST['typeBS'])){
		$typeBS = FALSE;
	}
	if( !isset($_POST['typeCS'])){
		$typeCS = FALSE;
	}
	if( isset($_POST['category_id'])) {
		$category_id = (int)($_POST['category_id']);
		if( isset($_POST['first_genre']) ) {
			$first_genre = (int)($_POST['first_genre']);
		}
		if( isset($_POST['sub_genre'])) {
			$sub_genre = (int)($_POST['sub_genre']);
		}
	}
	if( isset($_POST['station'])) {
		$channel_id = (int)($_POST['station']);
	}
	if( isset($_POST['weekofday']) ) {
		$weekofday = (int)($_POST['weekofday']);
	}
	if( isset($_POST['prgtime']) ) {
		$prgtime = (int)($_POST['prgtime']);
	}
	if( isset($_POST['keyword_id']) ) {
		$keyword_id = (int)($_POST['keyword_id']);
		if( isset($_POST['sft_start']) ) {
			$sft_start = transTime( parse_time( $_POST['sft_start'] ) );
		}
		if( isset($_POST['sft_end']) ) {
			$sft_end = transTime( parse_time( $_POST['sft_end'] ) );
		}
		if( isset($_POST['discontinuity']) ) {
			$discontinuity = (int)($_POST['discontinuity']);
		}
		if( isset($_POST['priority']) ) {
			$priority = (int)($_POST['priority']);
		}
		if( isset($_POST['filename']) ) {
			$filename = $_POST['filename'];
		}
		if( isset($_POST['directory']) ) {
			$directory = $_POST['directory'];
		}
	}
	$autorec_modes[(int)($settings->autorec_mode)]['selected'] = "selected";
	$do_keyword = 1;
}else{
	if( isset($_GET['keyword_id']) ) {
		$keyword_id    = (int)($_GET['keyword_id']);
		$keyc          = new DBRecord( KEYWORD_TBL, "id", $keyword_id );
		$search        = $keyc->keyword;
		$use_regexp    = (int)($keyc->use_regexp);
		$ena_title     = (boolean)$keyc->ena_title;
		$ena_desc      = (boolean)$keyc->ena_desc;
		$type          = $keyc->type;
		$typeGR        = (boolean)$keyc->typeGR;
		$typeBS        = (boolean)$keyc->typeBS;
		$typeCS        = (boolean)$keyc->typeCS;
		$channel_id    = (int)($keyc->channel_id);
		$category_id   = (int)($keyc->category_id);
		$first_genre   = (int)($keyc->first_genre);
		$sub_genre     = (int)($keyc->sub_genre);
		$weekofday     = (int)($keyc->weekofday);
		$prgtime       = (int)($keyc->prgtime);
		$sft_start     = transTime( $keyc->sft_start );
		$sft_end       = transTime( $keyc->sft_end );
		$discontinuity = (int)($keyc->discontinuity);
		$priority      = (int)($keyc->priority);
		$filename      = $keyc->filename_format;
		$directory     = $keyc->directory;
		$autorec_modes[(int)($keyc->autorec_mode)]['selected'] = "selected";
		$do_keyword = 1;
	}else{
		if( isset($_GET['search'])){
			$search = $_GET['search'];
			if( isset($_GET['use_regexp']) && ($_GET['use_regexp']) ) {
				$use_regexp = (int)($_GET['use_regexp']);
			}
			if( isset($_GET['ena_title'])){
				$ena_title = (boolean)$_GET['ena_title'];
			}
			if( isset($_GET['ena_desc'])){
				$ena_desc = (boolean)$_GET['ena_desc'];
			}
			$do_keyword = 1;
		}
		if( isset($_GET['station'])) {
			$channel_id = (int)($_GET['station']);
			$do_keyword = 1;
		}
		if( isset($_GET['type'])) {
			$type = $_GET['type'];
			switch( $type ){
				case 'GR';
					$typeBS = FALSE;
					$typeCS = FALSE;
					break;
				case 'BS';
					$typeGR = FALSE;
					$typeCS = FALSE;
					break;
				case 'CS';
					$typeGR = FALSE;
					$typeBS = FALSE;
					break;
			}
			$do_keyword = 1;
		}
		if( isset($_GET['category_id'])) {
			$category_id = (int)($_GET['category_id']);
			if( isset($_GET['sub_genre'])) {
				$sub_genre = (int)($_GET['sub_genre']);
			}
			$do_keyword = 1;
		}
		$autorec_modes[(int)($settings->autorec_mode)]['selected'] = "selected";
	}
}

if( $type === '-' ){
	if( $typeGR ){
		$type = !$typeBS && !$typeCS ? 'GR' : '*';
	}else{
		if( $typeBS ){
			$type = !$typeCS ? 'BS' : '*';
		}else
			if( $typeCS )
				$type = 'CS';
			else{
				$typeGR = TRUE;
				$typeBS = TRUE;
				$typeCS = TRUE;
			}
	}
	$enable = FALSE;
}

if( !$ena_title && !$ena_desc ){
	$ena_title  = TRUE;
	$ena_desc   = TRUE;
}

try{
	$programs = array();
if( $do_keyword ){
	$precs = Keyword::search( $search, $use_regexp, $ena_title, $ena_desc, $typeGR, $typeBS, $typeCS, $category_id, $channel_id, $weekofday, $prgtime, $sub_genre, $first_genre );
	
	foreach( $precs as $p ) {
	try{
		$ch  = new DBRecord(CHANNEL_TBL, "id", $p->channel_id );
		$cat = new DBRecord(CATEGORY_TBL, "id", $p->category_id );
		$arr = array();
		$arr['type'] = $p->type;
		$arr['station_name'] = $ch->name;
		$start_time = toTimestamp($p->starttime);
		$end_time = toTimestamp($p->endtime);
		$arr['date'] = date( "m/d(", $start_time ).$week_tb[date( "w", $start_time )].')';
		$arr['starttime'] = date( "H:i:s-", $start_time );
		$arr['endtime'] = date( "H:i:s", $end_time );
		$arr['duration'] = date( "H:i:s", $end_time-$start_time-9*60*60 );
		$arr['prg_top'] = date( "YmdH", $start_time-60*60*1 );
		$arr['title'] = $p->title;
		$arr['description'] = $p->description;
		$arr['id']  = $p->id;
		$arr['cat'] = $cat->name_en;
		$rec_cnt    = DBRecord::countRecords(RESERVE_TBL, "WHERE program_id = '".$p->id."' AND complete = '0'");
		if( $rec_cnt ){
			$rev = DBRecord::createRecords(RESERVE_TBL, "WHERE program_id = '".$p->id."' AND complete = '0' ORDER BY starttime ASC");
			if( $keyword_id ){
				foreach( $rev as $r ){
					if( (int)$r->autorec == $keyword_id ){
						$arr['rev_id'] = $r->id;
						$arr['rec']    = $r->tuner + 1;
						$arr['key_id'] = $keyword_id;
						goto EXIT_REV;
					}
				}
			}
			$arr['rev_id'] = $rev[0]->id;
			$arr['rec']    = $rev[0]->tuner + 1;
			$arr['key_id'] = 0;
		}else{
			$arr['rev_id'] = 0;
			$arr['rec']    = 0;
			$arr['key_id'] = 0;
		}
EXIT_REV:;
		$arr['autorec'] = $p->autorec;
		$arr['keyword'] = putProgramHtml( $arr['title'], $p->type, $p->channel_id, $p->category_id, $p->sub_genre );
		array_push( $programs, $arr );
	}catch( exception $e ){}
	}
}
	$k_category_name = "";
	$crecs = DBRecord::createRecords(CATEGORY_TBL);
	$cats = array();
	$cats[0]['id'] = 0;
	$cats[0]['name'] = "すべて";
	$cats[0]['selected'] = $category_id == 0 ? "selected" : "";
	foreach( $crecs as $c ) {
		$arr = array();
		$arr['id'] = $c->id;
		$arr['name'] = $c->name_jp;
		$arr['selected'] = $c->id == $category_id ? "selected" : "";
		if( $c->id == $category_id ) $k_category_name = $c->name_jp;
		array_push( $cats, $arr );
	}
	
	$types = array();
	if( $settings->gr_tuners != 0 ) {
		$arr = array();
		$arr['name'] = "GR";
		$arr['value'] = "GR";
		$arr['checked'] = $typeGR ? 'checked' : "";
		array_push( $types, $arr );
	}
	if( $settings->bs_tuners != 0 ) {
		$arr = array();
		$arr['name'] = "BS";
		$arr['value'] = "BS";
		$arr['checked'] = $typeBS ? 'checked' : "";
		array_push( $types, $arr );

		// CS
		if ($settings->cs_rec_flg != 0) {
			$arr = array();
			$arr['name'] = "CS";
			$arr['value'] = "CS";
			$arr['checked'] = $typeCS ? 'checked' : "";
			array_push( $types, $arr );
		}
	}
	
	$k_station_name = "";
	$stations = array();
	$stations[0]['id'] = 0;
	$stations[0]['name'] = "すべて";
	$stations[0]['selected'] = (! $channel_id) ? "selected" : "";
	$crecs = DBRecord::createRecords( CHANNEL_TBL, "WHERE type = 'GR' AND skip = '0' ORDER BY id" );
	foreach( $crecs as $c ) {
		$arr = array();
		$arr['id'] = $c->id;
		$arr['name'] = $c->name;
		$arr['type'] = 'GR';
		$arr['selected'] = $channel_id == $c->id ? "selected" : "";
		if( $channel_id == $c->id ) $k_station_name = $c->name;
		array_push( $stations, $arr );
	}
	$crecs = DBRecord::createRecords( CHANNEL_TBL, "WHERE type = 'BS' AND skip = '0' ORDER BY sid" );
	foreach( $crecs as $c ) {
		$arr = array();
		$arr['id'] = $c->id;
		$arr['name'] = $c->name;
		$arr['type'] = 'BS';
		$arr['selected'] = $channel_id == $c->id ? "selected" : "";
		if( $channel_id == $c->id ) $k_station_name = $c->name;
		array_push( $stations, $arr );
	}
	$crecs = DBRecord::createRecords( CHANNEL_TBL, "WHERE type = 'CS' AND skip = '0' ORDER BY sid" );
	foreach( $crecs as $c ) {
		$arr = array();
		$arr['id'] = $c->id;
		$arr['name'] = $c->name;
		$arr['type'] = 'CS';
		$arr['selected'] = $channel_id == $c->id ? "selected" : "";
		if( $channel_id == $c->id ) $k_station_name = $c->name;
		array_push( $stations, $arr );
	}
	$weekofdays["$weekofday"]["selected"] = "selected" ;
	
	// 時間帯
	$prgtimes = array();
	for( $i=0; $i < 25; $i++ ) {
		array_push( $prgtimes, 
			array(  "name" => ( $i == 24  ? "なし" : sprintf("%0d時～",$i) ),
					"value" => $i,
					"selected" =>  ( $i == $prgtime ? "selected" : "" ) )
		);
	}


	if( (int)$settings->bs_tuners > 0 )
		$link_add = $settings->cs_rec_flg==0 ? 1 : 2;
	else
		$link_add = 0;


	$smarty = new Smarty();
	$smarty->assign("sitetitle", !$keyword_id ? "番組検索" : '自動録画キーワード編集 №'.$keyword_id );
	$smarty->assign( "link_add", $link_add );
	$smarty->assign( 'menu_list', $MENU_LIST );
	$smarty->assign("do_keyword", $do_keyword );
	$smarty->assign( "programs", $programs );
	$smarty->assign( "cats", $cats );
	$smarty->assign( "k_category", $category_id );
	$smarty->assign( "k_category_name", $k_category_name );
	$smarty->assign( "k_sub_genre", $sub_genre );
	$smarty->assign( "first_genre", $first_genre );
	$smarty->assign( "types", $types );
	$smarty->assign( "k_type", $type );
	$smarty->assign( "enable", $enable );
	$smarty->assign( "k_typeGR", $typeGR );
	$smarty->assign( "k_typeBS", $typeBS );
	$smarty->assign( "k_typeCS", $typeCS );
	$smarty->assign( "search" , $search );
	$smarty->assign( "use_regexp", $use_regexp );
	$smarty->assign( "ena_title", $ena_title );
	$smarty->assign( "ena_desc", $ena_desc );
	$smarty->assign( "stations", $stations );
	$smarty->assign( "k_station", $channel_id );
	$smarty->assign( "k_station_name", $k_station_name );
	$smarty->assign( "weekofday", $weekofday );
	$smarty->assign( "k_weekofday", $weekofdays["$weekofday"]["name"] );
	$smarty->assign( "weekofday", $weekofday );
	$smarty->assign( "weekofdays", $weekofdays );
	$smarty->assign( "autorec_modes", $autorec_modes );
	$smarty->assign( "prgtimes", $prgtimes );
	$smarty->assign( "prgtime", $prgtime );
	$smarty->assign( "keyword_id", $keyword_id );
	$smarty->assign( "sft_start", $sft_start );
	$smarty->assign( "sft_end", $sft_end );
	$smarty->assign( "discontinuity", $discontinuity );
	$smarty->assign( "priority", $priority );
	$smarty->assign( "filename", $filename );
	$smarty->assign( "spool", $spool );
	$smarty->assign( "directory", $directory );
	$smarty->display("programTable.html");
}
catch( exception $e ) {
	exit( $e->getMessage() );
}
?>