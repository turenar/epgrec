<?php

include_once("config.php");
include_once( INSTALL_PATH . "/DBRecord.class.php" );
include_once( INSTALL_PATH . "/Smarty/Smarty.class.php" );
include_once( INSTALL_PATH . "/reclib.php" );
include_once( INSTALL_PATH . "/Settings.class.php" );
include_once( INSTALL_PATH . '/settings/menu_list.php' );

// 設定ファイルの有無を検査する
if( ! file_exists( INSTALL_PATH."/settings/config.xml") ) {
    header( "Content-Type: text/html;charset=utf-8" );
    exit( "<script type=\"text/javascript\">\n" .
          "<!--\n".
         "window.open(\"install/step1.php\",\"_self\");".
         "// -->\n</script>" );
}

$settings = Settings::factory();

$DAY_OF_WEEK = array( "(日)","(月)","(火)","(水)","(木)","(金)","(土)" );

// パラメータの処理
// 表示する長さ（時間）
$program_length = (int)$settings->program_length;
if( isset( $_GET['length']) ) $program_length = (int) $_GET['length'];
// 地上=GR/BS=BS
$type = "GR";
if( isset( $_GET['type'] ) ) $type = $_GET['type'];
// 現在の時間
$top_time = mktime( date("H"), 0 , 0 );
if( isset( $_GET['time'] ) ) {
	if( sscanf( $_GET['time'] , "%04d%2d%2d%2d", $y, $mon, $day, $h ) == 4 ) {
		$tmp_time = mktime( $h, 0, 0, $mon, $day, $y );
		if( ($tmp_time < ($top_time + 3600 * 24 * 8)) && ($tmp_time > ($top_time - 3600 * 24 * 8)) )
			$top_time = $tmp_time;
	}
}
$last_time = $top_time + 3600 * $program_length;

// 番組表
$programs = array();
if( $type == "BS" ) $channel_map = $BS_CHANNEL_MAP;
else if( $type == "GR" ) $channel_map = $GR_CHANNEL_MAP;
else if( $type == "CS" ) $channel_map = $CS_CHANNEL_MAP;
$st = 0;
$prec = null;
try {
	$prec = new DBRecord(PROGRAM_TBL);
}
catch( Exception $e ) {
	exit('プログラムテーブルが存在しないようです。インストールをやり直してください.');
}
$num_ch     = 0;
$num_all_ch = 0;
$wave_type  = ($type === 'GR' ? '地上D' : $type ).':';
foreach( $channel_map as $channel_disc => $channel ) {
 	try {
		if( $type === 'GR' )
			$options = "WHERE type = 'GR' AND channel = '".$channel."' ORDER BY sid ASC";
		else
			$options = "WHERE channel_disc = '".$channel_disc."'";
		$ch_cnt = DBRecord::countRecords( CHANNEL_TBL, $options );
		if( $ch_cnt > 0 ){
			$chd = DBRecord::createRecords( CHANNEL_TBL, $options );
			foreach( $chd as $crec ){
				$num_all_ch++;
				$prev_end = $top_time;
				$programs[$st]["id"]   = $ch_id = $crec->id;
				$programs[$st]["skip"] = $crec->skip;
				$programs[$st]["channel_disc"] = $crec->channel_disc;
				$programs[$st]["station_name"] = $crec->name;
				$programs[$st]["sid"] = $crec->sid;
				$programs[$st]["ch_hash"] = md5($crec->channel_disc);
				$programs[$st]["channel"] = $crec->channel;
				$programs[$st]['list'] = array();

				$reca = $prec->fetch_array( "channel_disc", $crec->channel_disc,
				                                  "endtime > '".toDatetime($top_time)."' ".
				                                  "AND starttime < '". toDatetime($last_time)."' ".
				                                  "ORDER BY starttime ASC "
				                               );
				$num = 0;
				if( count( $reca ) > 1 || !( count( $reca )==1 && (string)$reca[0]['title']==="放送休止" ) ){
					$ch_num = $wave_type.$crec->channel.'ch';
					foreach( $reca as $prg ) {
						// 前プログラムとの空きを調べる
						$program_id = $prg['id'];
						$start_str  = $prg['starttime'];
						$start      = toTimestamp( $start_str );
						if( $start > $prev_end ){
							$programs[$st]['list'][$num]['category_name'] = "none";
							$programs[$st]['list'][$num]['genre']         = 0;
							$programs[$st]['list'][$num]['sub_genre']     = 0;
							$programs[$st]['list'][$num]['height']        = (int)( ($start-$prev_end) * (int)$settings->height_per_hour / 3600 );
							$programs[$st]['list'][$num]['title'] = "";
							$programs[$st]['list'][$num]['starttime'] = "";
							$programs[$st]['list'][$num]['description'] = "";
							$num++;
						}
						$prev_end = toTimestamp( $prg['endtime'] );
            
						// プログラムを埋める
						$cat = new DBRecord( CATEGORY_TBL, 'id', $prg['category_id'] );
						$programs[$st]['list'][$num]['category_name'] = $cat->name_en;
						$programs[$st]['list'][$num]['genre']         = $prg['category_id'];
						$programs[$st]['list'][$num]['sub_genre']     = $prg['sub_genre'];
						$programs[$st]['list'][$num]['height']        =
							(int)( ( ($prev_end>=$last_time ? $last_time : $prev_end) - ($start<=$top_time ? $top_time : $start) ) * (int)$settings->height_per_hour / 3600 );
						$programs[$st]['list'][$num]['title']         = $prg['title'];
						$programs[$st]['list'][$num]['starttime']     = date('H:i:s', $start );
						$programs[$st]['list'][$num]['description']   = $prg['description'];
						$programs[$st]['list'][$num]['prg_start']     = str_replace( '-', '/', $start_str);
						$programs[$st]['list'][$num]['duration']      = (string)($prev_end - $start);
						$programs[$st]['list'][$num]['channel']       = $ch_num;
						$programs[$st]['list'][$num]['id']            = $program_id;
						$programs[$st]['list'][$num]['autorec']       = $prg['autorec'];
						$rec_cnt = DBRecord::countRecords(RESERVE_TBL, "WHERE complete = '0' AND program_id = '".$program_id."'" );
						$programs[$st]['list'][$num]['rec'] = $rec_cnt;
						if( $rec_cnt ){
							$rev = DBRecord::createRecords(RESERVE_TBL, "WHERE complete = '0' AND program_id = '".$program_id."'" );
							$programs[$st]['list'][$num]['tuner'] = $rev[0]->tuner;
							// 複数ある場合の対処無し
						}else
							$programs[$st]['list'][$num]['tuner'] = "";
						$programs[$st]['list'][$num]['keyword'] = putProgramHtml( $prg['title'], $type, $ch_id, $prg['category_id'], $prg['sub_genre'] );
						$num++;
					}
					if( $crec->skip==0 && $num>0 )
						$num_ch++;
				}
				// 空きを埋める
				if( $last_time > $prev_end ){
					$programs[$st]['list'][$num]['category_name'] = "none";
					$programs[$st]['list'][$num]['genre']         = 0;
					$programs[$st]['list'][$num]['sub_genre']     = 0;
					$programs[$st]['list'][$num]['height']        = (int)( ( $last_time - $prev_end ) * (int)$settings->height_per_hour / 3600 );
					$programs[$st]['list'][$num]['title'] = "";
					$programs[$st]['list'][$num]['starttime'] = "";
					$programs[$st]['list'][$num]['description'] = "";
				}
				$st++;
			}
		}
	}
	catch( exception $e ) {
//		exit( $e->getMessage() );
//		何もしない
 	}
}
$prec = null;
 
// 局の幅
$ch_set_width = (int)($settings->ch_set_width);
// 全体の幅
$chs_width = $ch_set_width * $num_ch;

// GETパラメタ
$get_param = $_SERVER['SCRIPT_NAME'] . "?type=".$type."&length=".$program_length."";

$smarty = new Smarty();

// カテゴリ一覧
$crec = DBRecord::createRecords( CATEGORY_TBL );
$cats = array();
$num = 0;
foreach( $crec as $val ) {
	$cats[$num]['name_en'] = $val->name_en;
	$cats[$num]['name_jp'] = $val->name_jp;
	$num++;
}
$smarty->assign( "cats", $cats );


// タイプ選択
$types = array();
$i = 0;
if( $settings->gr_tuners != 0 ) {
	$types[$i]['selected'] = $type == "GR" ? 'class="selected"' : "";
	$types[$i]['link'] = $_SERVER['SCRIPT_NAME'] . "?type=GR&length=".$program_length."&time=".date( "YmdH", $top_time);
	$types[$i]['link2'] = $_SERVER['SCRIPT_NAME'] . "?type=GR&length=".$program_length;
	$types[$i]['name'] = "地デジ";
	$i++;
}
if( $settings->bs_tuners != 0 ) {
	$types[$i]['selected'] = $type == "BS" ? 'class="selected"' : "";
	$types[$i]['link'] = $_SERVER['SCRIPT_NAME'] . "?type=BS&length=".$program_length."&time=".date( "YmdH", $top_time);
	$types[$i]['link2'] = $_SERVER['SCRIPT_NAME'] . "?type=BS&length=".$program_length;
	$types[$i]['name'] = "BS";
	$i++;

	// CS
	if ($settings->cs_rec_flg != 0) {
		$types[$i]['selected'] = $type == "CS" ? 'class="selected"' : "";
		$types[$i]['link'] = $_SERVER['SCRIPT_NAME'] . "?type=CS&length=".$program_length."&time=".date( "YmdH", $top_time);
		$types[$i]['link2'] = $_SERVER['SCRIPT_NAME'] . "?type=CS&length=".$program_length;
		$types[$i]['name'] = "CS";
		$i++;
	}
}
$smarty->assign( "types", $types );

// 日付選択
$days = array();
$day = array();
$day['d'] = "昨日";
$day['link'] = $get_param . "&time=". date( "YmdH", time() - 3600 *24 );
$day['ofweek'] = "";
$day['selected'] = $top_time < mktime( 0, 0 , 0) ? 'class="selected"' : '';

array_push( $days , $day );
$day['d'] = "現在";
$day['link'] = $get_param;
$day['ofweek'] = "";
$day['selected'] = "";
array_push( $days, $day );
for( $i = 0 ; $i < 8 ; $i++ ) {
	$day['d'] = "".date("d", time() + 24 * 3600 * $i ) . "日";
	$day['link'] = $get_param . "&time=".date( "Ymd", time() + 24 * 3600 * $i) . date("H" , $top_time );
	$day['ofweek'] = $DAY_OF_WEEK[(int)date( "w", time() + 24 * 3600 * $i )];
	$day['selected'] = date("d", $top_time) == date("d", time() + 24 * 3600 * $i ) ? 'class="selected"' : '';
	array_push( $days, $day );
}
$smarty->assign( "days" , $days );

// 時間選択
$toptimes = array();
for( $i = 0 ; $i < 28; $i+=2 ) {
	$tmp = array();
	$tmp['hour'] = sprintf( "%02d", $i<=24 ? $i : $i-24 );
	$tmp_time = $i<24 ? $top_time : $top_time + 24 * 60 * 60;
	$tmp['link'] = $get_param . "&time=".date("Ymd", $tmp_time ) . sprintf("%02d", $i<24 ? $i : $i-24 );
	array_push( $toptimes, $tmp );
}
$smarty->assign( "toptimes" , $toptimes );

// 時刻欄
$tvtimes = array();
for( $i = 0 ; $i < $program_length; $i++ ) {
	$tmp = array();
	$tmp_time    = $top_time + 3600 * $i;
	$tmp['hour'] = date("H", $tmp_time );
	$tmp['link'] = $get_param . "&time=".date("YmdH", $tmp_time );
	array_push( $tvtimes, $tmp );
}

$smarty->assign( "tvtimes", $tvtimes );
$smarty->assign( "pre8link", $get_param."&time=".date("YmdH", $top_time - 8*3600 ) );
$smarty->assign( "prelink", $get_param."&time=".date("YmdH", $top_time - 3600 ) );

$smarty->assign( "programs", $programs );
$smarty->assign( "ch_set_width", (int)($settings->ch_set_width) );
$smarty->assign( "chs_width", $chs_width );
$smarty->assign( "height_per_hour", $settings->height_per_hour );
$smarty->assign( "height_per_min", (int)$settings->height_per_hour / 60 );
$smarty->assign( "num_ch", $num_ch );
$smarty->assign( "num_all_ch" , $num_all_ch );

$sitetitle = date( "Y", $top_time ) . "年" . date( "m", $top_time ) . "月" . date( "d", $top_time ) . "日". date( "H", $top_time ) .
              "時～".( $type == "GR" ? "地上" : $type )."デジタル番組表";
$smarty->assign("REALVIEW_HTTP", REALVIEW_HTTP ? 1 : 0 );

$smarty->assign("sitetitle", $sitetitle );

$smarty->assign("top_time", str_replace( "-", "/" ,toDatetime($top_time)) );
$smarty->assign("last_time", str_replace( "-", "/" ,toDatetime($last_time)) );
$smarty->assign( 'menu_list', $MENU_LIST );


$smarty->display("index.html");
?>
