<?php
include_once('config.php');
include_once( INSTALL_PATH . '/Smarty/Smarty.class.php' );
include_once( INSTALL_PATH . '/DBRecord.class.php' );
include_once( INSTALL_PATH . '/reclib.php' );
include_once( INSTALL_PATH . '/Reservation.class.php' );
include_once( INSTALL_PATH . '/Keyword.class.php' );
include_once( INSTALL_PATH . '/Settings.class.php' );
include_once( INSTALL_PATH . '/settings/menu_list.php' );

function word_chk( $chk_wd )
{
	return ( strpos( $chk_wd, "\"" )===FALSE && strpos( $chk_wd, "'" )===FALSE ? $chk_wd : "" );
}

$settings = Settings::factory();

$weekofdays = array( '月', '火', '水', '木', '金', '土', '日', '－' );
$prgtimes = array();
for( $i=0 ; $i < 25; $i++ ) {
	$prgtimes[$i] = $i == 24 ? 'なし' : $i.'時～';
}

// 新規キーワードがポストされた

if( isset($_POST['add_keyword']) ) {
	if( $_POST['add_keyword'] == 1 ) {
		try {
			$keyword_id = $_POST['keyword_id'];
			if( $keyword_id ){
				$rec = new Keyword( 'id', $keyword_id );
			}else
				$rec = new Keyword();
			$rec->keyword      = $_POST['k_search'];
			$rec->type         = isset($_POST['k_enable']) ? $_POST['k_type'] : '-';
			$rec->typeGR       = $_POST['k_typeGR'];
			$rec->typeBS       = $_POST['k_typeBS'];
			$rec->typeCS       = $_POST['k_typeCS'];
			$rec->category_id  = $_POST['k_category'];
			$rec->sub_genre    = $_POST['k_sub_genre'];
			$rec->first_genre  = $_POST['k_first_genre'];
			$rec->channel_id   = $_POST['k_station'];
			$rec->use_regexp   = $_POST['k_use_regexp'];
			$rec->ena_title    = $_POST['k_ena_title'];
			$rec->ena_desc     = $_POST['k_ena_desc'];
			$rec->weekofday    = $_POST['k_weekofday'];
			$rec->prgtime      = $_POST['k_prgtime'];
			$rec->autorec_mode = $_POST['autorec_mode'];
			$rec->sft_start    = parse_time( $_POST['k_sft_start'] );
			$rec->sft_end      = parse_time( $_POST['k_sft_end'] );
			if( isset($_POST['k_discontinuity']) && ($_POST['k_discontinuity']) ) {
				$rec->discontinuity = (int)($_POST['k_discontinuity']);
			}else
				$rec->discontinuity = 0;
			$rec->priority        = $_POST['k_priority'];
			$rec->filename_format = word_chk( $_POST['k_filename'] );
			$rec->directory       = word_chk( $_POST['k_directory'] );
			$rec->update();
			if( $keyword_id )
				$rec->rev_delete();
			if( $rec->type !== '-' ){
				// 録画予約実行
				while(1){
					$sem_key = sem_get( SEM_KEY, 1, 0666 );
					if( $sem_key === FALSE )
						usleep( 100 );
					else
						break;
				}
				while(1){
					$shm_id = shm_attach( 2 );
					if( $shm_id === FALSE )
						usleep( 100 );
					else
						break;
				}
				$rec->reservation( $rec->type, $shm_id, $sem_key );
				shm_detach( $shm_id );
			}
		}
		catch( Exception $e ) {
			exit( $e->getMessage() );
		}
	}
}


$cs_rec_flg = (boolean)$settings->cs_rec_flg;
$keywords   = array();
try {
	$recs = Keyword::createRecords(KEYWORD_TBL, 'ORDER BY id ASC' );
	foreach( $recs as $rec ) {
		$arr = array();
		$arr['id'] = $rec->id;
		$arr['keyword'] = $rec->keyword;
//		$arr['type'] = $rec->type == '*' ? 'ALL' : $rec->type;
		$arr['type'] = "";
		if( $rec->typeGR && $rec->typeBS && ( !$cs_rec_flg || $rec->typeCS ) ){
			$arr['type'] .= 'ALL';
		}else{
			$cnt = 0;
			if( $rec->typeGR ){
				$arr['type'] .= 'GR';
				$cnt++;
			}
			if( $rec->typeBS ){
				if( $cnt )
					$arr['type'] .= '+';
				$arr['type'] .= 'BS';
				$cnt++;
			}
			if( $rec->typeCS ){
				if( $cnt )
					$arr['type'] .= '+';
				$arr['type'] .= 'CS';
			}
		}
		$arr['k_type'] = $rec->type==='-' ? FALSE : TRUE;
		if( $rec->channel_id ) {
			try {
				$crec = new DBRecord(CHANNEL_TBL, 'id', $rec->channel_id );
				$arr['channel'] = $crec->name;
			}catch( exception $e ){
				$rec->channel_id = 0;
				$arr['channel']  = 'すべて';
			}
		}
		else $arr['channel'] = 'すべて';
//		$arr['k_channel'] = $rec->channel_id;
		if( $rec->category_id ) {
			$crec = new DBRecord(CATEGORY_TBL, 'id', $rec->category_id );
			$arr['category'] = $crec->name_jp;
		}
		else $arr['category'] = 'すべて';
		$arr['k_category'] = $rec->category_id;
		$arr['sub_genre'] = $rec->sub_genre;
		$arr['first_genre'] = $rec->first_genre;
		
		$arr['use_regexp'] = $rec->use_regexp;
		
		$arr['weekofday'] = $weekofdays[$rec->weekofday];
//		$arr['k_weekofday'] = $rec->weekofday;
		$arr['prgtime'] = $prgtimes[$rec->prgtime];
//		$arr['k_prgtime'] = $rec->prgtime;
		$arr['autorec_mode'] = $RECORD_MODE[(int)$rec->autorec_mode]['name'];
		$arr['sft_start'] = transTime( $rec->sft_start, TRUE );
		$arr['sft_end']   = transTime( $rec->sft_end, TRUE );
		$arr['discontinuity'] = $rec->discontinuity;
		$arr['priority'] = $rec->priority;
		array_push( $keywords, $arr );
	}
}
catch( Exception $e ) {
	exit( $e->getMessage() );
}

if( (int)$settings->bs_tuners > 0 )
	$link_add = $settings->cs_rec_flg==0 ? 1 : 2;
else
	$link_add = 0;

$smarty = new Smarty();

$smarty->assign( 'keywords', $keywords );
$smarty->assign( 'link_add', $link_add );
$smarty->assign( 'menu_list', $MENU_LIST );
$smarty->assign( 'sitetitle', '自動録画キーワードの管理' );
$smarty->display( 'keywordTable.html' );
?>