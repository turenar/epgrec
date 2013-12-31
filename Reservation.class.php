<?php
//include_once( INSTALL_PATH . '/config.php');
include_once( INSTALL_PATH . '/DBRecord.class.php' );
include_once( INSTALL_PATH . '/reclib.php' );
include_once( INSTALL_PATH . '/Settings.class.php' );
include_once( INSTALL_PATH . '/recLog.inc.php' );


// 予約クラス

class Reservation {
	
	public static function simple( $program_id , $autorec = 0, $mode = 0, $discontinuity=0 ) {
		$settings = Settings::factory();
		$rval = 0;
		try {
			$prec = new DBRecord( PROGRAM_TBL, 'id', $program_id );
			
			$rval = self::custom(
				$prec->starttime,
				$prec->endtime,
				$prec->channel_id,
				$prec->title,
				$prec->description,
				$prec->category_id,
				$program_id,
				$autorec,
				$mode,
				$discontinuity );
				
		}
		catch( Exception $e ) {
			throw $e;
		}
		return $rval;
	}

	
	public static function custom(
		$starttime,				// 開始時間Datetime型
		$endtime,				// 終了時間Datetime型
		$channel_id,			// チャンネルID
		$title = 'none',		// タイトル
		$description = 'none',	// 概要
		$category_id = 0,		// カテゴリID
		$program_id = 0,		// 番組ID
		$autorec = 0,			// 自動録画ID
		$mode = 0,				// 録画モード
		$discontinuity = 0,		// 隣接禁止フラグ
		$dirty = 0,				// ダーティフラグ
		$man_priority = 0		// 優先度
	) {
		$settings = Settings::factory();

		// 時間を計算
		$start_time = toTimestamp( $starttime );
		$end_time   = toTimestamp( $endtime );
		if( $autorec ){
			$keyword = new DBRecord( KEYWORD_TBL, 'id', $autorec );
			$tmp_start = $start_time + $keyword->sft_start;
			$tmp_end   = $end_time + $keyword->sft_end;
			if( $tmp_start>=$end_time || $tmp_end<=$start_time || $tmp_start>=$tmp_end )
				throw new Exception( '時刻シフト量が異常なため、開始時刻が終了時刻以降に指定されています' );
			else{
				$start_time = $tmp_start;
				$end_time   = $tmp_end;
			}
			$priority = $keyword->priority;
		}else
			$priority = $man_priority;
		$job = 0;
		try {
			// 同一番組予約チェック
			if( $program_id && $autorec>0 ){
				if( $autorec <= 10 )
					$num = DBRecord::countRecords( RESERVE_TBL, "WHERE program_id = '".$program_id."' AND autorec >= '0'" );
				else
					$num = DBRecord::countRecords( RESERVE_TBL, "WHERE program_id = '".$program_id."' AND ( ( autorec >= '0' AND autorec <= '10' ) OR autorec = '".$autorec."' )" );
				if( $num ) {
					throw new Exception('同一の番組が録画予約されています');
				}
			}
			//チューナ仕様取得
			$crec = new DBRecord( CHANNEL_TBL, 'id', $channel_id );
			if( $crec->type == 'GR' ){
				$tuners   = (int)($settings->gr_tuners);
				$type_str = "type = 'GR'";
				$smf_type = 'GR';
			}else{
				$tuners   = (int)($settings->bs_tuners);
				$type_str = "(type = 'BS' OR type = 'CS')";
				$smf_type = 'BS';
			}
			$battings = DBRecord::countRecords( RESERVE_TBL, "WHERE complete = '0' AND ".$type_str.
															" AND starttime <= '".toDatetime($end_time) .
															"' AND endtime >= '".toDatetime($start_time)."'" );		//重複数取得
			if( $battings > 0 ) {
				//重複
				//予約群 先頭取得
				$prev_trecs = array();
				$stt_str    = toDatetime($start_time);
				while( 1 ){
					try{
						$sql_cmd = "WHERE complete = '0' AND ".$type_str.
															" AND starttime < '".$stt_str.
															"' AND endtime >= '".$stt_str."'";
						$cnt = DBRecord::countRecords( RESERVE_TBL, $sql_cmd );
						if( $cnt == 0 )
							break;
						$prev_trecs = DBRecord::createRecords( RESERVE_TBL, $sql_cmd.' ORDER BY starttime ASC' );
						if( $prev_trecs == null )
							break;
						$stt_str = $prev_trecs[0]->starttime;
					}catch( Exception $e ){
						break;
					}
				}
				//予約群 最後尾取得
				$end_str = toDatetime($end_time);
				while( 1 ){
					try{
						$sql_cmd = "WHERE complete = '0' AND ".$type_str.
															" AND starttime <= '".$end_str.
															"' AND endtime > '".$end_str."'";
						$cnt = DBRecord::countRecords( RESERVE_TBL, $sql_cmd );
						if( $cnt == 0 )
							break;
						$prev_trecs = DBRecord::createRecords( RESERVE_TBL, $sql_cmd.' ORDER BY endtime DESC' );
						if( $prev_trecs == null )
							break;
						$end_str = $prev_trecs[0]->endtime;
					}catch( Exception $e ){
						break;
					}
				}

				//重複予約配列取得
				$sql_cmd = "WHERE complete = '0' AND ".$type_str.
															" AND starttime >= '".$stt_str.
															"' AND endtime <= '".$end_str."' ORDER BY starttime ASC, endtime DESC";
				$prev_trecs = DBRecord::createRecords( RESERVE_TBL, $sql_cmd );
				// 予約修正に必要な情報を取り出す
				$trecs = array();
				for( $cnt=0; $cnt<count($prev_trecs) ; $cnt++ ){
					$trecs[$cnt]['id']            = $prev_trecs[$cnt]->id;
					$trecs[$cnt]['program_id']    = $prev_trecs[$cnt]->program_id;
					$trecs[$cnt]['channel_id']    = $prev_trecs[$cnt]->channel_id;
					$trecs[$cnt]['title']         = $prev_trecs[$cnt]->title;
					$trecs[$cnt]['description']   = $prev_trecs[$cnt]->description;
					$trecs[$cnt]['channel']       = $prev_trecs[$cnt]->channel;
					$trecs[$cnt]['category_id']   = $prev_trecs[$cnt]->category_id;
					$trecs[$cnt]['start_time']    = toTimestamp( $prev_trecs[$cnt]->starttime );
					$trecs[$cnt]['end_time']      = toTimestamp( $prev_trecs[$cnt]->endtime );
					$trecs[$cnt]['autorec']       = $prev_trecs[$cnt]->autorec;
					$trecs[$cnt]['path']          = $prev_trecs[$cnt]->path;
					$trecs[$cnt]['mode']          = $prev_trecs[$cnt]->mode;
					$trecs[$cnt]['dirty']         = $prev_trecs[$cnt]->dirty;
					$trecs[$cnt]['tuner']         = $prev_trecs[$cnt]->tuner;
					$trecs[$cnt]['priority']      = $prev_trecs[$cnt]->priority;
					$trecs[$cnt]['discontinuity'] = $prev_trecs[$cnt]->discontinuity;
					$trecs[$cnt]['status']        = 1;
				}
				//新規予約を既予約配列に追加
				$trecs[$cnt]['id']            = 0;
				$trecs[$cnt]['program_id']    = $program_id;
				$trecs[$cnt]['channel_id']    = $crec->id;
				$trecs[$cnt]['title']         = $title;
				$trecs[$cnt]['description']   = $description;
				$trecs[$cnt]['channel']       = $crec->channel;
				$trecs[$cnt]['category_id']   = $category_id;
				$trecs[$cnt]['start_time']    = $start_time;
				$trecs[$cnt]['end_time']      = $end_time;
				$trecs[$cnt]['autorec']       = $autorec;
				$trecs[$cnt]['path']          = "";
				$trecs[$cnt]['mode']          = $mode;
				$trecs[$cnt]['dirty']         = $dirty;
				$trecs[$cnt]['tuner']         = -1;
				$trecs[$cnt]['priority']      = $priority;
				$trecs[$cnt]['discontinuity'] = $discontinuity;
				$trecs[$cnt]['status']        = 1;

				//全重複予約をソート
				foreach( $trecs as $key => $row ){
					$volume[$key]  = $row['start_time'];
					$edition[$key] = $row['end_time'];
				}
				array_multisort( $volume, SORT_ASC, $edition, SORT_ASC, $trecs );

				$ed_tm_sft = $settings->former_time + $settings->rec_switch_time;
RETRY:;
				//予約配列参照用配列の初期化
				$r_cnt = 0;
				foreach( $trecs as $key => $row ){
					if( $row['status'] )
						$t_tree[0][$r_cnt++] = $key;
				}
				// 重複予約をチューナー毎に分配
				for( $t_cnt=0; $t_cnt<$tuners ; $t_cnt++ ){
					$b_rev = 0;
					$n_0 = 1;
					$n_1 = 0;
					if( isset( $t_tree[$t_cnt] ) )
					while( $n_0 < count($t_tree[$t_cnt]) ){
//file_put_contents( '/tmp/debug.txt', "[".count($t_tree[$t_cnt])."-".$n_0."]\n", FILE_APPEND );
						$bf_org_ed = $trecs[$t_tree[$t_cnt][$b_rev]]['end_time'];
						$bf_ed     = ( ($bf_org_ed-$trecs[$t_tree[$t_cnt][$b_rev]]['start_time'])%60 != 0 ) ? $bf_org_ed+$ed_tm_sft : $bf_org_ed;
						$af_st     = $trecs[$t_tree[$t_cnt][$n_0]]['start_time'];
						$af_ed     = $trecs[$t_tree[$t_cnt][$n_0]]['end_time'];
						if( $bf_ed>$af_st || ( ( $settings->force_cont_rec!=1 || $trecs[$t_tree[$t_cnt][$b_rev]]['discontinuity']==1 ) && $bf_ed==$af_st ) ){
							//完全重複 隣接禁止時もここ
							$t_tree[$t_cnt+1][$n_1] = $t_tree[$t_cnt][$n_0];
							$n_1++;
//file_put_contents( '/tmp/debug.txt', ' '.count($t_tree[$t_cnt]).">", FILE_APPEND );
							array_splice( $t_tree[$t_cnt], $n_0, 1 );
//file_put_contents( '/tmp/debug.txt', count($t_tree[$t_cnt])."\n", FILE_APPEND );
						}else
						if( $bf_ed == $af_st ){
							//隣接重複
							// 重複数算出
							$t_ovlp = 0;
							if( isset( $t_tree[$t_cnt+1] ) )
								foreach( $t_tree[$t_cnt+1] as $trunk ){
									if( $trecs[$trunk]['start_time']<=$bf_ed && $trecs[$trunk]['end_time']>=$bf_ed )
										$t_ovlp++;
								}
//file_put_contents( '/tmp/debug.txt', ' $t_ovlp '.$t_ovlp." -> ", FILE_APPEND );
							$s_ch = -1;
							for( $br_lmt=$n_0; $br_lmt<count($t_tree[$t_cnt]); $br_lmt++ ){
								//同じ開始時間の物をカウント
								if( $bf_ed == $trecs[$t_tree[$t_cnt][$br_lmt]]['start_time'] ){
									$t_ovlp++;
									//同じCh
									if( $trecs[$t_tree[$t_cnt][$b_rev]]['channel_id'] == $trecs[$t_tree[$t_cnt][$br_lmt]]['channel_id'] )
										$s_ch = $br_lmt;
								}else
									break;
							}
//file_put_contents( '/tmp/debug.txt', $t_ovlp."\n", FILE_APPEND );

							if( $t_ovlp<=$tuners-$t_cnt || ( $settings->force_cont_rec==1 && $trecs[$t_tree[$t_cnt][$b_rev]]['discontinuity']!=1 ) ){
//file_put_contents( '/tmp/debug.txt', ' '.count($t_tree[$t_cnt]).">>\n", FILE_APPEND );
								if( $t_ovlp<=TUNER_UNIT1-1-$t_cnt && $t_ovlp <= $tuners-1-$t_cnt ){
									//(使い勝手の良い)チューナに余裕あり
									for( $cc=$n_0; $cc<$br_lmt; $cc++ ){
										$t_tree[$t_cnt+1][$n_1] = $t_tree[$t_cnt][$cc];
										$n_1++;
									}
//file_put_contents( '/tmp/debug.txt', " array1-(".($br_lmt-$n_0).")\n", FILE_APPEND );
									array_splice( $t_tree[$t_cnt], $n_0, $br_lmt-$n_0 );
								}else{
									//チューナに余裕なし
									if( $s_ch != -1 ){
										//同じCh同士を隣接 いらんかな？
										for( $cc=$n_0; $cc<$s_ch; $cc++ ){
											$t_tree[$t_cnt+1][$n_1] = $t_tree[$t_cnt][$cc];
											$n_1++;
										}
										for( $cc=$s_ch+1; $cc<$br_lmt; $cc++ ){
											$t_tree[$t_cnt+1][$n_1] = $t_tree[$t_cnt][$cc];
											$n_1++;
										}
//file_put_contents( '/tmp/debug.txt', " array2-1-(".$t_ovlp." ".$br_lmt." ".$s_ch." ".$n_0.")\n", FILE_APPEND );
//file_put_contents( '/tmp/debug.txt', " array2-2-(".($br_lmt-($s_ch+1)).")\n", FILE_APPEND );
										if( $br_lmt-($s_ch+1) > 0 )
											array_splice( $t_tree[$t_cnt], $s_ch+1, $br_lmt-($s_ch+1) );
//file_put_contents( '/tmp/debug.txt', " array2-3-(".($s_ch-$n_0).")\n", FILE_APPEND );
										if( $s_ch-$n_0 > 0 )
											array_splice( $t_tree[$t_cnt], $n_0, $s_ch-$n_0 );
										$b_rev++;
										$n_0++;
									}else{
										//頭の予約を隣接
										$b_rev++;
										$n_0++;
										for( $cc=$n_0; $cc<$br_lmt; $cc++ ){
											$t_tree[$t_cnt+1][$n_1] = $t_tree[$t_cnt][$cc];
											$n_1++;
										}
//file_put_contents( '/tmp/debug.txt', " array3A-(".($br_lmt-$n_0).")\n", FILE_APPEND );
										if( $br_lmt-$n_0 > 0 )
											array_splice( $t_tree[$t_cnt], $n_0, $br_lmt-$n_0 );
									}
								}
							}else
								goto PRIORITY_CHECK;
//file_put_contents( '/tmp/debug.txt', "  >>".count($t_tree[$t_cnt])."\n", FILE_APPEND );
						}else{
							//隣接なし
							$b_rev++;
							$n_0++;
//file_put_contents( '/tmp/debug.txt', "  <<<".count($t_tree[$t_cnt]).">>>\n", FILE_APPEND );
						}
//file_put_contents( '/tmp/debug.txt', " [[".count($t_tree[$t_cnt])."-".$n_0."]]\n", FILE_APPEND );
					}
				}
//file_put_contents( '/tmp/debug.txt', "分配完了\n\n", FILE_APPEND );
//var_dump($t_tree);
				//重複解消不可処理
				if( count($t_tree) > $tuners ){
PRIORITY_CHECK:
					if( $autorec ){
						//優先度判定
						$sql_cmd = "WHERE complete = '0' AND ".$type_str." AND priority < '".$priority.
															"' AND starttime <= '".toDatetime($end_time).
															"' AND endtime >= '".toDatetime($start_time)."'";
						$pri_lmt = DBRecord::countRecords( RESERVE_TBL, $sql_cmd );
						if( $pri_lmt ){
							$pri_ret = DBRecord::createRecords( RESERVE_TBL, $sql_cmd.' ORDER BY priority ASC' );
							for( $cnt=$pri_c=0; $cnt<count($trecs) ; $cnt++ )
								if( $trecs[$cnt]['id'] == $pri_ret[$pri_c]->id ){
									if( $trecs[$cnt]['status'] ){
										//優先度の低い予約を仮無効化
										$trecs[$cnt]['status'] = 0;
										unset( $t_tree );
										goto RETRY;
									}
									if( ++$pri_c == $pri_lmt )
										break;
								}
						}
						//自動予約禁止
//						$event = new DBRecord(PROGRAM_TBL, "id", $program_id );
//						$event->autorec = 0;
						reclog( $crec->channel_disc.'-Ch'.$crec->channel.' <a href="index.php?type='.$crec->type.'&length='.$settings->program_length.'&time='.date( 'YmdH', toTimestamp( $starttime ) ).'">'.$starttime.'</a>『'.htmlspecialchars($title).'』は重複により予約できません', EPGREC_WARN );
					}
					throw new Exception( '重複により予約できません' );
				}
// file_put_contents( '/tmp/debug.txt', "重複解消\n", FILE_APPEND );
				//チューナ番号の解決
				$t_blnk        = array_fill( 0, $tuners, 0 );
				$t_num         = array_fill( 0, $tuners, -1 );
				$tuner_no      = array_fill( 0, $tuners, -1 );
				$tuner_cnt     = array_fill( 0, $tuners, -1 );
				$tree_lmt      = count( $t_tree );
				$division_mode = 0;
				//録画中のチューナ番号取得
				for( $tree_cnt=0; $tree_cnt<$tree_lmt; $tree_cnt++ )
					if( $trecs[$t_tree[$tree_cnt][0]]['id'] != 0 ){
						$prev_start_time = $trecs[$t_tree[$tree_cnt][0]]['start_time'] - $settings->former_time;
						if( time() >= $prev_start_time ){
							$t_num[$tree_cnt]          = $trecs[$t_tree[$tree_cnt][0]]['tuner'];
							$t_blnk[$t_num[$tree_cnt]] = 2;
							$division_mode             = 1;
						}
					}
				//チューナー毎の予約配列中で多数使用しているチューナー番号を採用・重複時は早い者勝ち
				for( $tree_cnt=0; $tree_cnt<$tree_lmt; $tree_cnt++ )
					if( $t_num[$tree_cnt] == -1 ){
						$stk = array_fill( 0, $tuners, 0 );
						//各チューナーの予約数集計
						for( $rv_cnt=0; $rv_cnt<count($t_tree[$tree_cnt]); $rv_cnt++ ){
							$tmp_tuner = $trecs[$t_tree[$tree_cnt][$rv_cnt]]['tuner'];
							if( $tmp_tuner != -1 )
								$stk[$tmp_tuner]++;
						}
						//予約数最多のチューナー番号を選択
						for( $tuner_c=0; $tuner_c<$tuners; $tuner_c++ )
							if( $t_blnk[$tuner_c]!=2 && $stk[$tuner_c] > $tuner_cnt[$tree_cnt] ){
								$tuner_no[$tree_cnt]  = $tuner_c;
								$tuner_cnt[$tree_cnt] = $stk[$tuner_c];
							}
					}
				//指定チューナー番号を最多指定している予約配列に仮決定
				for( $tuner_c=0; $tuner_c<$tuners; $tuner_c++ )
					if( $t_blnk[$tuner_c] != 2 ){
						$tmp_cnt  = 0;
						$tmp_tree = -1;
						for( $tree_cnt=0; $tree_cnt<$tree_lmt; $tree_cnt++ )
							if( $tuner_no[$tree_cnt]==$tuner_c && $tuner_cnt[$tree_cnt]>$tmp_cnt ){
								$tmp_cnt  = $tuner_cnt[$tree_cnt];
								$tmp_tree = $tree_cnt;
							}
						if( $tmp_tree != -1 ){
							$t_num[$tmp_tree] = $tuner_c;
							$t_blnk[$tuner_c] = 1;
						}
					}
				for( $tree_cnt=0; $tree_cnt<$tree_lmt; $tree_cnt++ )
					//未決定な配列への空番号割り当て
					if( $t_num[$tree_cnt] == -1 ){
						for( $tuner_c=0; $tuner_c<$tuners; $tuner_c++ )
							if( !$t_blnk[$tuner_c] ){
								$t_num[$tree_cnt] = $tuner_c;
								$t_blnk[$tuner_c] = 1;
								break;
							}
					}else
						//前に空がありハード的にチューナーが変更される場合のみチューナー番号変更
						if( $t_num[$tree_cnt]>=TUNER_UNIT1 && $t_num[$tree_cnt]>=$tree_lmt )
							for( $tuner_c=0; $tuner_c<TUNER_UNIT1; $tuner_c++ )
								if( !$t_blnk[$tuner_c] ){
									if( $t_blnk[$t_num[$tree_cnt]] != 2 ){
										$t_blnk[$t_num[$tree_cnt]] = 0;
										$t_num[$tree_cnt]          = $tuner_c;
										$t_blnk[$tuner_c]          = 1;
									}else
										//録画中の予約以外を別配列に移動
										if( $tree_lmt < $tuners ){
											$t_tree[$tree_lmt] = array_slice( $t_tree[$tree_cnt], 1 );
											array_splice( $t_tree[$tree_cnt], 1 );
											$t_num[$tree_lmt++] = $tuner_c;
											$t_blnk[$tuner_c]   = 1;
										}
									break;
								}
				//優先度判定で削除になった予約をキャンセル
				foreach( $trecs as $sel )
					if( !$sel['status'] ){
						self::cancel( $sel['id'] );
					}
				$tuner_chg = 0;
				//新規予約・隣接解消再予約等 隣接禁止については分配時に解決済
				for( $t_cnt=0; $t_cnt<$tuners ; $t_cnt++ ){
// file_put_contents( '/tmp/debug.txt', ($t_cnt+1)."(".count($t_tree[$t_cnt]).")\n", FILE_APPEND );
//var_dump($t_tree[$t_cnt]);
					if( isset( $t_tree[$t_cnt] ) )
					for( $n_0=0,$n_lmt=count($t_tree[$t_cnt]); $n_0<$n_lmt ; $n_0++ ){
						// 予約修正に必要な情報を取り出す
						$prev_id            = $trecs[$t_tree[$t_cnt][$n_0]]['id'];
						$prev_program_id    = $trecs[$t_tree[$t_cnt][$n_0]]['program_id'];
						$prev_channel_id    = $trecs[$t_tree[$t_cnt][$n_0]]['channel_id'];
						$prev_title         = $trecs[$t_tree[$t_cnt][$n_0]]['title'];
						$prev_description   = $trecs[$t_tree[$t_cnt][$n_0]]['description'];
						$prev_channel       = $trecs[$t_tree[$t_cnt][$n_0]]['channel'];
						$prev_category_id   = $trecs[$t_tree[$t_cnt][$n_0]]['category_id'];
						$prev_start_time    = $trecs[$t_tree[$t_cnt][$n_0]]['start_time'];
						$prev_end_time      = $trecs[$t_tree[$t_cnt][$n_0]]['end_time'];
						$prev_autorec       = $trecs[$t_tree[$t_cnt][$n_0]]['autorec'];
						$prev_path          = $trecs[$t_tree[$t_cnt][$n_0]]['path'];
						$prev_mode          = $trecs[$t_tree[$t_cnt][$n_0]]['mode'];
						$prev_dirty         = $trecs[$t_tree[$t_cnt][$n_0]]['dirty'];
						$prev_tuner         = $trecs[$t_tree[$t_cnt][$n_0]]['tuner'];
						$prev_priority      = $trecs[$t_tree[$t_cnt][$n_0]]['priority'];
						$prev_discontinuity = $trecs[$t_tree[$t_cnt][$n_0]]['discontinuity'];
						if( $n_0 < $n_lmt-1 )
							$next_start_time = $trecs[$t_tree[$t_cnt][$n_0+1]]['start_time'];
						if( $prev_id == 0 ){
							//新規予約
							if( $n_0 < $n_lmt-1 && $prev_end_time == $next_start_time )
								$prev_end_time -= $ed_tm_sft;
							try {
								$job = self::at_set( 
									$prev_start_time,			// 開始時間Datetime型
									$prev_end_time,				// 終了時間Datetime型
									$prev_channel_id,			// チャンネルID
									$prev_title,				// タイトル
									$prev_description,			// 概要
									$prev_category_id,			// カテゴリID
									$prev_program_id,			// 番組ID
									$prev_autorec,				// 自動録画
									$prev_mode,
									$prev_dirty,
									$t_num[$t_cnt],				// チューナ
									$prev_priority,
									$prev_discontinuity
									);
							}
							catch( Exception $e ) {
								throw new Exception( '新規予約できません' );
							}
							continue;
						}else
							if( time() < $prev_start_time-$settings->former_time ){
								//録画開始前
								if( $prev_tuner != $t_num[$t_cnt] )
									$tuner_chg = 1;
								if( $n_0 < $n_lmt-1 ){
									if( $prev_end_time == $next_start_time ){
										//隣接解消再予約
										$prev_end_time -= $ed_tm_sft;
										try {
											// いったん予約取り消し
											self::cancel( $prev_id );
											// 再予約
											self::at_set( 
												$prev_start_time,			// 開始時間Datetime型
												$prev_end_time,				// 終了時間Datetime型
												$prev_channel_id,			// チャンネルID
												$prev_title,				// タイトル
												$prev_description,			// 概要
												$prev_category_id,			// カテゴリID
												$prev_program_id,			// 番組ID
												$prev_autorec,				// 自動録画
												$prev_mode,
												$prev_dirty,
												$t_num[$t_cnt],				// チューナ
												$prev_priority,
												$prev_discontinuity
												);
										}
										catch( Exception $e ) {
											throw new Exception( '予約できません' );
										}
										continue;
									}else{
										$tmp_end_time = (int)( $prev_end_time + $ed_tm_sft );
										if( $tmp_end_time%60==0 && $tmp_end_time < $next_start_time ){
											//終了時間短縮解消再予約
											$prev_end_time = $tmp_end_time;
											try {
												// いったん予約取り消し
												self::cancel( $prev_id );
												// 再予約
												self::at_set( 
													$prev_start_time,			// 開始時間Datetime型
													$prev_end_time,				// 終了時間Datetime型
													$prev_channel_id,			// チャンネルID
													$prev_title,				// タイトル
													$prev_description,			// 概要
													$prev_category_id,			// カテゴリID
													$prev_program_id,			// 番組ID
													$prev_autorec,				// 自動録画
													$prev_mode,
													$prev_dirty,
													$t_num[$t_cnt],				// チューナ
													$prev_priority,
													$prev_discontinuity
													);
											}
											catch( Exception $e ) {
												throw new Exception( '予約できません' );
											}
											continue;
										}
									}
								}
								//チューナ変更処理
								if( $prev_tuner != $t_num[$t_cnt] ){
									try {
										// いったん予約取り消し
										self::cancel( $prev_id );
										// 再予約
										self::at_set( 
											$prev_start_time,			// 開始時間Datetime型
											$prev_end_time,				// 終了時間Datetime型
											$prev_channel_id,			// チャンネルID
											$prev_title,				// タイトル
											$prev_description,			// 概要
											$prev_category_id,			// カテゴリID
											$prev_program_id,			// 番組ID
											$prev_autorec,				// 自動録画
											$prev_mode,
											$prev_dirty,
											$t_num[$t_cnt],				// チューナ
											$prev_priority,
											$prev_discontinuity
											);
									}
									catch( Exception $e ) {
										throw new Exception( 'チューナ機種の変更に失敗' );
									}
								}
							}
/*ここから(PT1 only)*/
							else
							if( $n_0==0 && ( ( USE_RECPT1 && $prev_tuner<TUNER_UNIT1 ) || ( $prev_tuner>=TUNER_UNIT1 && $OTHER_TUNERS_CHARA["$smf_type"][$prev_tuner-TUNER_UNIT1]['cntrl'] ) ) ){
								//録画中
								if( $n_lmt > 1 ){
									if( $prev_end_time == $next_start_time ){
										//録画時間短縮指示
										$ps = search_reccmd( $prev_id );
										if( $ps !== FALSE ){
											exec( RECPT1_CTL.' --pid '.$ps->pid.' --extend -'.($ed_tm_sft+$settings->extra_time) );		//(PT1用)
											for( $i=0; $i<count($prev_trecs) ; $i++ ){
												if( $prev_id == $prev_trecs[$i]->id ){
													$prev_trecs[$i]->endtime = toDatetime( $prev_end_time-$ed_tm_sft );
													break;
												}
											}
										}
									}else
									if( (($prev_end_time+$ed_tm_sft)%60)==0 && $prev_end_time+$ed_tm_sft < $next_start_time ){
										//録画時間延伸指示
										$ps = search_reccmd( $prev_id );
										if( $ps !== FALSE ){
											exec( RECPT1_CTL.' --pid '.$ps->pid.' --extend '.($ed_tm_sft+$settings->extra_time) );		//(PT1用)
											for( $i=0; $i<count($prev_trecs) ; $i++ ){
												if( $prev_id == $prev_trecs[$i]->id ){
													$prev_trecs[$i]->endtime = toDatetime( $prev_end_time+$ed_tm_sft );
													break;
												}
											}
										}
									}
								}
							}
/*ここまで(PT1 only)*/
					}
				}
				return $job.':'.$tuner_chg;			// 成功
			}else{
				//単純予約
				try {
					$job = self::at_set(
						$start_time,
						$end_time,
						$channel_id,
						$title,
						$description,
						$category_id,
						$program_id,
						$autorec,
						$mode,
						$dirty,
						0,		// チューナー番号
						$priority,
						$discontinuity
					);
				}
				catch( Exception $e ) {
					throw new Exception( '予約できません' );
				}
				return $job.':0';			// 成功
			}
		}
		catch( Exception $e ) {
			throw $e;
		}
	}
	// custom 終了

	private static function at_set(
		$start_time,				// 開始時間
		$end_time,				// 終了時間
		$channel_id,			// チャンネルID
		$title = 'none',		// タイトル
		$description = 'none',	// 概要
		$category_id = 0,		// カテゴリID
		$program_id = 0,		// 番組ID
		$autorec = 0,			// 自動録画
		$mode = 0,				// 録画モード
		$dirty = 0,				// ダーティフラグ
		$tuner = 0,				// チューナ
		$priority,
		$discontinuity
	) {
		global $RECORD_MODE;
		$settings   = Settings::factory();
		$spool_path = INSTALL_PATH.$settings->spool;
		$crec_      = new DBRecord( CHANNEL_TBL, 'id', $channel_id );

		//即時録画の指定チューナー確保
		$epg_time = array( 'GR' => FIRST_REC, 'BS' => 180, 'CS' => 120 );
		if( $start_time-$settings->former_time-$epg_time[$crec_->type] <= time() ){
			$shm_nm   = array( SEM_GR_START, SEM_ST_START );
			$sem_type = $crec_->type=='GR' ? 0 : 1;
			$shm_name = $shm_nm[$sem_type] + $tuner;
			$sem_id   = sem_get_surely( $shm_name );
			if( $sem_id === FALSE )
				throw new Exception( 'セマフォ・キー確保に失敗' );
			$cc=0;
			while(1){
				if( sem_acquire( $sem_id ) === TRUE ){
					$shm_id = shmop_open_surely();
					$smph   = shmop_read_surely( $shm_id, $shm_name );
					if( $smph == 2 ){
						// リアルタイム視聴停止
						$real_view = (int)trim( file_get_contents( REALVIEW_PID ) );
						posix_kill( $real_view, 9 );		// 録画コマンド停止
						shmop_write_surely( $shm_id, $shm_name, 0 );
						shmop_write_surely( $shm_id, SEM_REALVIEW, 0 );		// リアルタイム視聴tunerNo clear
						shmop_close( $shm_id );
						$sleep_time = $settings->rec_switch_time;
					}else
						if( $smph == 1 ){
							// EPG受信停止
							$rec_trace = 'TUNER='.$tuner.' MODE=0 OUTPUT='.$settings->temp_data.'_'.$crec_->type;
							$ps_output = shell_exec( PS_CMD );
							$rarr      = explode( "\n", $ps_output );
							for( $cc=0; $cc<count($rarr); $cc++ ){
								if( strpos( $rarr[$cc], $rec_trace ) !== FALSE ){
									$ps = ps_tok( $rarr[$cc] );
									while( ++$cc < count($rarr) ){
										$c_ps = ps_tok( $rarr[$cc] );
										if( $ps->pid == $c_ps->ppid ){
											$ps = $c_ps;
											while( ++$cc < count($rarr) ){
												$c_ps = ps_tok( $rarr[$cc] );
												if( $ps->pid == $c_ps->ppid ){
													posix_kill( $c_ps->pid, 15 );		//EPG受信停止
													$sleep_time = $settings->rec_switch_time;
													break 4;
												}
											}
										}
									}
									$sleep_time = $settings->rec_switch_time;
									break 2;
								}
							}
						}
					break;
				}else
					if( ++$cc < 5 )
						sleep(1);
					else
						throw new Exception( 'チューナー確保に失敗' );
			}
		}

		//時間がらみ調整
		$now_time = time();
		if( $start_time-$settings->former_time <= $now_time ){	// すでに開始されている番組
			$at_start = $now_time;
			if( isset( $sleep_time ) )
				$now_time += $sleep_time;
			else
				$sleep_time = 0;
			$rec_start = $start_time = $now_time;		// 即開始
		}else{
			if( $now_time < $end_time ){
				$rec_start  = $start_time - $settings->former_time;
				$padding_tm = $start_time%60 ? PADDING_TIME+$start_time%60 : PADDING_TIME;
				$at_start   = ( $start_time-$padding_tm <= $now_time ) ? $now_time : $start_time - $padding_tm;
				$sleep_time = $rec_start - $at_start;
			}else
				throw new Exception( '終わっている番組です' );
		}
		$duration = $end_time - $rec_start;
		if( $duration < $settings->former_time ) {	// 終了間際の番組は弾く
			throw new Exception( '終わりつつある/終わっている番組です' );
		}
		$annex_extime = ( $settings->force_cont_rec!=1 || $discontinuity ) ? TRUE : FALSE;
		if( $program_id ){
			$prg = new DBRecord( PROGRAM_TBL, 'id', $program_id );
			$resolution = (int)$prg->video_type & 0xF0;
			$aspect     = (int)$prg->video_type & 0x0F;
			$audio_type = (int)$prg->audio_type;
			$bilingual  = (int)$prg->multi_type;
			$eid        = (int)$prg->eid;
			if( $autorec ){
				$keyword = new DBRecord( KEYWORD_TBL, 'id', $autorec );
				if( $end_time == toTimestamp($prg->endtime)+$keyword->sft_end )
					$annex_extime = TRUE;
			}else
				if( $prg->endtime==$end_time || $end_time%30==0 )
					$annex_extime = TRUE;
		}else{
			$resolution = 0;
			$aspect     = 0;
			$audio_type = 0;
			$bilingual  = 0;
			$eid        = 0;
			if( $end_time%30 == 0 )
				$annex_extime = TRUE;
		}
		if( $annex_extime )
			$duration += $settings->extra_time;			//重複による短縮がされてないものは糊代を付ける
		$rrec = null;
		try {
			// ここからファイル名生成
/*
			%TITLE%	番組タイトル
			// %TITLEn%	番組タイトル(n=1-9 1枠の複数タイトルから選別変換 '/'でセパレートされているものとする)
			%ST%	開始日時（ex.200907201830)
			%ET%	終了日時
			%TYPE%	GR/BS/CS
			%CH%	チャンネル番号
			// %SID%	サービスID
			// %CHNAME%	チャンネル名
			%DOW%	曜日（Sun-Mon）
			%DOWJ%	曜日（日-土）
			%YEAR%	開始年
			%MONTH%	開始月
			%DAY%	開始日
			%HOUR%	開始時
			%MIN%	開始分
			%SEC%	開始秒
			%DURATION%	録画時間（秒）
			// %DURATIONHMS%	録画時間（hh:mm:ss）
*/
			$day_of_week = array( '日','月','火','水','木','金','土' );
			$filename = $autorec&&$keyword->filename_format!="" ? $keyword->filename_format : $settings->filename_format;
			// %TITLE%
			$temp = trim($title);
			if( strncmp( $temp, '[￥]', 5 ) == 0 ){
				$out_title = substr( $temp, 5 );
			}else
				$out_title = $temp;
			$filename = mb_str_replace('%TITLE%', $out_title, $filename);
			// %TITLEn%	番組タイトル(n=1-9 1枠の複数タイトルから選別変換 '/'でセパレートされているものとする)
			$magic_c = strpos( $filename, '%TITLE' );
			if( $magic_c !== FALSE ){
				$tl_num = $filename[$magic_c+6];
				if( ctype_digit( $tl_num ) && strpos( $out_title, '/' )!==FALSE ){
					$split_tls = explode( '/', $out_title );
					$filename  = mb_str_replace( '%TITLE'.$tl_num.'%', $split_tls[(int)$tl_num-1], $filename );
				}else
					$filename = mb_str_replace( '%TITLE'.$tl_num.'%', $out_title.$tl_num, $filename );
			}
			// %ST%	開始日時
			$filename = mb_str_replace('%ST%',date('YmdHis', $start_time), $filename );
			// %ET%	終了日時
			$filename = mb_str_replace('%ET%',date('YmdHis', $end_time), $filename );
			// %TYPE%	GR/BS
			$filename = mb_str_replace('%TYPE%',$crec_->type, $filename );
			// %SID%	サービスID
			$filename = mb_str_replace('%SID%',$crec_->sid, $filename );
			// %CH%	チャンネル番号
			$filename = mb_str_replace('%CH%',$crec_->channel, $filename );
			// %CHNAME%	チャンネル名
			$filename = mb_str_replace('%CHNAME%',$crec_->name, $filename );
			// %DOW%	曜日（Sun-Mon）
			$filename = mb_str_replace('%DOW%',date('D', $start_time), $filename );
			// %DOWJ%	曜日（日-土）
			$filename = mb_str_replace('%DOWJ%',$day_of_week[(int)date('w', $start_time)], $filename );
			// %YEAR%	開始年
			$filename = mb_str_replace('%YEAR%',date('Y', $start_time), $filename );
			// %MONTH%	開始月
			$filename = mb_str_replace('%MONTH%',date('m', $start_time), $filename );
			// %DAY%	開始日
			$filename = mb_str_replace('%DAY%',date('d', $start_time), $filename );
			// %HOUR%	開始時
			$filename = mb_str_replace('%HOUR%',date('H', $start_time), $filename );
			// %MIN%	開始分
			$filename = mb_str_replace('%MIN%',date('i', $start_time), $filename );
			// %SEC%	開始秒
			$filename = mb_str_replace('%SEC%',date('s', $start_time), $filename );
			// %DURATION%	録画時間（秒）
			$filename = mb_str_replace('%DURATION%',$duration, $filename );
			// %DURATIONHMS%	録画時間（hh:mm:ss）
			$filename = mb_str_replace('%DURATIONHMS%',transTime($duration,TRUE), $filename );
			// %[YmdHisD]*%	開始日時(date()に書式をそのまま渡す 非変換部に'%'を使う場合は誤変換に注意・対策はしない)
			if( substr_count( $filename, '%' ) >= 2 ){
				$split_tls = explode( '%', $filename );
				$iti       = $filename[0]=='%' ? 0 : 1;
				$filename  = mb_str_replace('%'.$split_tls[$iti].'%',date( $split_tls[$iti], $start_time ), $filename );
			}

			// あると面倒くさそうな文字を全部_に
//			$filename = preg_replace("/[ \.\/\*:<>\?\\|()\'\"&]/u","_", trim($filename) );
			
			// 全角に変換したい場合に使用
/*			$trans = array( "[" => "［",
							"]" => "］",
							"/" => "／",
							"'" => "’",
							"\"" => "”",
							"\\" => "￥",
						);
			$filename = strtr( $filename, $trans );
*/
			// UTF-8に対応できない環境があるようなのでmb_ereg_replaceに戻す
//			$filename = mb_ereg_replace("[ \./\*:<>\?\\|()\'\"&]","_", trim($filename) );
			$filename = mb_ereg_replace("[\\/\'\"]","_", trim($filename) );

			// ディレクトリ付加
			$add_dir = $autorec && $keyword->directory!="" ? $keyword->directory.'/' : "";

			// 文字コード変換
			if( defined( 'FILESYSTEM_ENCODING' ) ) {
				$filename = mb_convert_encoding( $filename, FILESYSTEM_ENCODING, 'UTF-8' );
				$add_dir  = mb_convert_encoding( $add_dir, FILESYSTEM_ENCODING, 'UTF-8' );
			}

			// ファイル名長制限+ファイル名重複解消
			$fl_len     = strlen( $filename );
			$fl_len_lmt = 255 - strlen( $RECORD_MODE["$mode"]['suffix'] );
			// サムネール
			if( (boolean)$settings->use_thumbs ){
				$gen_thumbnail = defined( 'GEN_THUMBNAIL' ) ? GEN_THUMBNAIL : INSTALL_PATH.'/gen-thumbnail.sh';
				$fl_len_lmt   -= 4;
			}
			if( $fl_len > $fl_len_lmt ){
				$filename = mb_strcut( $filename, 0, $fl_len_lmt );
				$fl_len   = strlen( $filename );
			}
			$files = scandir( $spool_path.'/'.$add_dir );
			if( $files !== FALSE )
				array_splice( $files, 0, 2 );
			else
				$files = array();
			$file_cnt = 0;
			$tmp_name = $filename;
			$sql_que  = "WHERE path LIKE '".mysql_real_escape_string($add_dir.$tmp_name.$RECORD_MODE["$mode"]['suffix'])."'";
			while( in_array( $tmp_name.$RECORD_MODE["$mode"]['suffix'], $files ) || DBRecord::countRecords( RESERVE_TBL, $sql_que )!=0 ){
				$file_cnt++;
				$len_dec = strlen( (string)$file_cnt );
				if( $fl_len > $fl_len_lmt-$len_dec ){
					$filename = mb_strcut( $filename, 0, $fl_len_lmt-$len_dec );
					$fl_len   = strlen( $filename );
				}
				$tmp_name = $filename.$file_cnt;
				$sql_que  = "WHERE path LIKE '".mysql_real_escape_string($add_dir.$tmp_name.$RECORD_MODE["$mode"]['suffix'])."'";
			}
			$filename  = $tmp_name.$RECORD_MODE["$mode"]['suffix'];
			$thumbname = $filename.'.jpg';

			// ファイル名生成終了

			// 予約レコード生成
			$rrec = new DBRecord( RESERVE_TBL );
			$rrec->channel_disc  = $crec_->channel_disc;
			$rrec->channel_id    = $crec_->id;
			$rrec->program_id    = $program_id;
			$rrec->type          = $crec_->type;
			$rrec->channel       = $crec_->channel;
			$rrec->title         = $title;
			$rrec->description   = $description;
			$rrec->category_id   = $category_id;
			$rrec->starttime     = toDatetime( $start_time );
			$rrec->endtime       = toDatetime( $end_time );
			$rrec->path          = $add_dir.$filename;
			$rrec->autorec       = $autorec;
			$rrec->mode          = $mode;
			$rrec->tuner         = $tuner;
			$rrec->priority      = $priority;
			$rrec->discontinuity = $discontinuity;
			$rrec->reserve_disc  = md5( $crec_->channel_disc . toDatetime( $start_time ). toDatetime( $end_time ) );
			//
			$descriptor = array( 0 => array( 'pipe', 'r' ),
			                     1 => array( 'pipe', 'w' ),
			                     2 => array( 'pipe', 'w' ),
			);
			// AT発行準備
			$cmdline = $settings->at.' '.date('H:i m/d/Y', $at_start);
			$env = array( 'CHANNEL'    => $crec_->channel,
						  'DURATION'   => $duration,
						  'OUTPUT'     => $spool_path.'/'.$add_dir.$filename,
						  'TYPE'       => $crec_->type,
						  'TUNER'      => $tuner,
						  'MODE'       => $mode,
						  'TUNER_UNIT' => TUNER_UNIT1,
						  'THUMB'      => INSTALL_PATH.$settings->thumbs.'/'.$thumbname,
						  'FORMER'     => $settings->former_time,
						  'FFMPEG'     => $settings->ffmpeg,
						  'SID'        => $crec_->sid,
						  'EID'        => $eid,
						  'RESOLUTION' => $resolution,
						  'ASPECT'     => $aspect,
						  'AUDIO_TYPE' => $audio_type,
						  'BILINGUAL'  => $bilingual,
			);
			// ATで予約する
			$process = proc_open( $cmdline , $descriptor, $pipes, $spool_path, $env );
			if( !is_resource( $process ) ) {
				$rrec->delete();
				reclog( 'atの実行に失敗した模様', EPGREC_ERROR);
				throw new Exception('AT実行エラー');
			}
			fwrite($pipes[0], 'echo $$ >/tmp/tuner_'.$rrec->type.$tuner."\n" );		//SHのPID
			if( $sleep_time ){
				if( $program_id && $sleep_time > $settings->rec_switch_time )
					fwrite($pipes[0], "echo 'temp' > ".$spool_path.'/tmp & sync & '.INSTALL_PATH.'/scoutEpg.php '.$rrec->id." &\n" );		//HDD spin-up + 単発EPG更新
				else
					fwrite($pipes[0], "echo 'temp' > ".$spool_path."/tmp & sync &\n" );		//HDD spin-up
				fwrite($pipes[0], $settings->sleep.' '.$sleep_time."\n" );
			}
			fwrite($pipes[0], DO_RECORD." ".$rrec->id."\n" );		//$rrec->id追加は録画キャンセルのためのおまじない
			fwrite($pipes[0], COMPLETE_CMD." ".$rrec->id."\n" );
			if( $settings->use_thumbs == 1 ) {
				fwrite($pipes[0], $gen_thumbnail."\n" );
			}
			fclose($pipes[0]);
			// 標準エラーを取る
			$rstring = stream_get_contents( $pipes[2]);
			
			fclose( $pipes[2] );
		    fclose( $pipes[1] );
			proc_close( $process );
			// job番号を取り出す
			$rarr = array();
			$tok = strtok( $rstring, " \n" );
			while( $tok !== false ) {
				array_push( $rarr, $tok );
				$tok = strtok( " \n" );
			}
			// OSを識別する(Linux、またはFreeBSD)
			//$job = php_uname('s') == 'FreeBSD' ? 'Job' : 'job';
			$job = PHP_OS == 'FreeBSD' ? 'Job' : 'job';
			$key = array_search( $job, $rarr );
			if( isset( $sem_id ) )
				while( sem_release( $sem_id ) === FALSE )
					usleep( 100 );
			if( $key !== false ) {
				if( is_numeric( $rarr[$key+1]) ) {
					$rrec->job = $rarr[$key+1];
					$rrec->update();
					reclog( '予約ID:'.$rrec->id.' '.$rrec->channel_disc.':T'.$rrec->tuner.'-Ch'.$rrec->channel.' '.$rrec->starttime.'『'.$title.'』を登録' );
					return $program_id.':'.$tuner.':'.$rrec->id;			// 成功
				}
			}
			// エラー
			$rrec->delete();
			reclog( 'ジョブNoの取得に失敗', EPGREC_ERROR );
			throw new Exception( 'ジョブNoの取得に失敗' );
		}
		catch( Exception $e ) {
			if( $rrec != null ) {
				if( $rrec->id ) {
					// 予約を取り消す
					$rrec->delete();
				}
			}
			throw $e;
		}
	}

	// 取り消し
	public static function cancel( $reserve_id = 0, $program_id = 0 ) {
		$settings = Settings::factory();
		$rec = null;
		try {
			if( $reserve_id ) {
				$rec = new DBRecord( RESERVE_TBL, 'id' , $reserve_id );
				$ret = '0';
			}
			else if( $program_id ) {
				$prev_recs = DBRecord::createRecords( RESERVE_TBL, "WHERE complete = '0' AND program_id = '".$program_id."' ORDER BY starttime ASC" );
				$rec = $prev_recs[0];
				$ret = (string)(count( $prev_recs ) - 1);
			}
			if( $rec == null ) {
				throw new Exception('IDの指定が無効です');
			}
			if( ! $rec->complete ) {
				// 予約解除
				$rec_st = toTimestamp($rec->starttime);
				$pad_tm = $rec_st%60 ? PADDING_TIME+60-$rec_st%60 : PADDING_TIME;
				$rec_at = $rec_st - $pad_tm;
				$rec_st -= $settings->former_time;
				$rec_ed = toTimestamp($rec->endtime);
				$now_tm = time();
				if( $rec_at-2 <= $now_tm ){
					if( $rec_st-2 <= $now_tm ){
						// 実行中の予約解除
						if( $now_tm <= $rec_ed ){
							if( $rec_st >= $now_tm )
								sleep(3);
							//録画停止
							$ps = search_reccmd( $rec->id );
							if( $ps !== FALSE ){
								$rec->autorec = ( $rec->autorec + 1 ) * -1;
								$rec->update();
								$smf_type = $rec->type=='GR' ? 'GR' : 'BS';
								if( ( USE_RECPT1 && $rec->tuner<TUNER_UNIT1 ) || ( $rec->tuner>=TUNER_UNIT1 && $OTHER_TUNERS_CHARA["$smf_type"][$prev_tuner-TUNER_UNIT1]['cntrl'] ) ){
									// recpt1ctlで停止
									exec( RECPT1_CTL.' --pid '.$ps->pid.' --time 10 >/dev/null' );
								}else{
									//コントローラの無いチューナへの汎用処理
									posix_kill( $ps->pid, 15 );		//録画停止
								}
								return $ret;
							}
						}
						//DB残留 DB削除へ
					}else{
						if( $rec_at >= $now_tm )
							sleep(3);
						//sleep待機中の予約解除
						$sleep_ppid  = (int)trim( file_get_contents( '/tmp/tuner_'.$rec->type.$rec->tuner ) );
						$ps_output   = shell_exec( PS_CMD );
						$rarr        = explode( "\n", $ps_output );
						$scout_cmd   = INSTALL_PATH.'/scoutEpg.php '.$rec->id;
						$my_pid      = posix_getpid();
						$sleep_pid   = 0;
						$scout_pid   = 0;
						$dorec_pid   = 0;
						$dorecsh_pid = 0;
						$recepg_pid  = 0;
						$stop_stk    = 0;
						for( $cc=0; $cc<count($rarr); $cc++ ){
							if( $sleep_pid == 0 ){
								if( strpos( $rarr[$cc], 'sleep ' ) !== FALSE ){
									$ps = ps_tok( $rarr[$cc] );
									if( (int)$ps->ppid == $sleep_ppid ){
										posix_kill( $sleep_ppid, 15 );		//親プロセス(AT?)停止
										$sleep_pid = (int)$ps->pid;
										posix_kill( $sleep_pid, 15 );		//(sleep)停止
										$stop_stk++;
										continue;
									}
								}
							}
							if( $scout_pid == 0 ){
								if( strpos( $rarr[$cc], $scout_cmd ) !== FALSE ){
									$ps = ps_tok( $rarr[$cc] );
									$scout_pid = (int)$ps->pid;
									$temp_ts   = $settings->temp_data.'_'.$rec->type.'_'.$scout_pid;
									$stop_stk++;
								}
							}else
							if( $dorec_pid == 0 ){
								if( strpos( $rarr[$cc], $temp_ts.' '.DO_RECORD ) !== FALSE ){
									$ps = ps_tok( $rarr[$cc] );
									if( (int)$ps->ppid == $scout_pid ){
										if( $scout_pid != $my_pid )			//自殺防止
											posix_kill( $scout_pid, 15 );		//scoutEpg.php停止
										$dorec_pid = (int)$ps->pid;
									}
								}
							}else
							if( $dorecsh_pid == 0 ){
								if( strpos( $rarr[$cc], 'sh '.DO_RECORD ) !== FALSE ){
									$ps = ps_tok( $rarr[$cc] );
									if( (int)$ps->ppid == $dorec_pid ){
										posix_kill( $dorec_pid, 15 );		//do_record.sh停止
										$dorecsh_pid = (int)$ps->pid;
									}
								}
							}else
							if( $recepg_pid==0 && strpos( $rarr[$cc], $temp_ts )!==FALSE ){
								$ps = ps_tok( $rarr[$cc] );
								if( (int)$ps->ppid == $dorecsh_pid ){
									posix_kill( $dorecsh_pid, 15 );		//do_record.sh停止
									$recepg_pid = (int)$ps->pid;
									posix_kill( $recepg_pid, 15 );		//EPG録画停止
								}
							}
						}
						if( $stop_stk ){
							reclog( '予約ID:'.$rec->id.' '.$rec->channel_disc.':T'.$rec->tuner.'-Ch'.$rec->channel.' '.$rec->starttime.'『'.$rec->title.'』を削除' );
							$rec->delete();
							return $ret;
						}
						throw new Exception( '予約キャンセルに失敗した' );
					}
				}else{
					//AT削除
					while(1){
						$ret_cd = system( $settings->atrm . " " . $rec->job, $var_ret );
						if( $ret_cd!==FALSE && $var_ret==0 ){
							reclog( '予約ID:'.$rec->id.' '.$rec->channel_disc.':T'.$rec->tuner.'-Ch'.$rec->channel.' '.$rec->starttime.'『'.$rec->title.'』を削除' );
							break;
						}
						$rarr       = explode( "\n", str_replace( "\t", ' ', shell_exec( $settings->at.'q' ) ) );
						$search_job = $rec->job.' ';
						$search_own = posix_getlogin();
						foreach( $rarr as $str_var ){
							if( strncmp( $str_var, $search_job, strlen( $search_job ) ) == 0 ){
								if( strpos( $str_var, $search_own ) !== FALSE )
									continue 2;
								else{
									reclog( '予約ID:'.$rec->id.'の削除を中止しました。 AT-JOB:'.$rec->job.'の削除に失敗しました。 ('.$search_own.'以外でJOBが登録されている)', EPGREC_ERROR );
									return $ret;
								}
							}
						}
						reclog( '予約ID:'.$rec->id.'を削除しましたが AT-JOB:'.$rec->job.'の削除に失敗しました。 (JOBが有りませんでした)' );
						break;
					}
				}
			}
			$rec->delete();
			return $ret;
		}
		catch( Exception $e ) {
			reclog('Reservation::cancel 予約キャンセルでDB接続またはアクセスに失敗した模様 $reserve_id:'.$reserve_id.' $program_id:'.$program_id, EPGREC_ERROR );
			throw $e;
		}
	}
}
?>
