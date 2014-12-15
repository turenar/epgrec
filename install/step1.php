<?php

// パーミッションを返す
function getPerm( $file ) {
	
	$ss = @stat( $file );
	return sprintf('%o', ($ss['mode'] & 000777));
}

echo '<p><b>epgrecのインストール状態をチェックします</b></p>';

list( $chk_shmop, $chk_sem, $chk_pcntl ) = explode( ':', trim( exec( './chk_function.php' ) ) );
if( $chk_shmop==='0' || $chk_sem==='0' || $chk_pcntl==='0' ){
	if( $chk_shmop==='0' )
		echo 'PHP関数shmop_open()を利用できません<br>PHPからsystemVセマフォを操作できません<br>';
	if( $chk_sem==='0' )
		echo 'PHP関数sem_get()を利用できません<br>PHPからsystemV共有メモリを操作できません<br>';
	if( $chk_pcntl==='0' )
		echo 'PHP関数pcntl_setpriority()を利用できません<br>PHPのPCNTLプロセス制御機能を利用できません<br>';
	exit( 'これらのPHP関数が使えるようにしてください<br>' );
}


// config.phpの存在確認

if(! file_exists( '../config.php' ) ) {
	@copy( '../config.php.sample', '../config.php' );
	if( ! file_exists( '../config.php' ) ) {
		exit('config.phpが存在しません<br>config.php.sampleをリネームし地上デジタルチャンネルマップを編集してください<br>');
	}
}

include('../config.php');
include_once(INSTALL_PATH.'/reclib.php');

// do-record.shの存在チェック
/*
if(! file_exists( DO_RECORD ) ) {
	exit('do-record.shが存在しません<br>do-record.sh.pt1やdo-record.sh.friioを参考に作成してください<br>' );
}
*/

// パーミッションチェック

$rw_dirs = array( 
	INSTALL_PATH.'/templates_c',
	INSTALL_PATH.'/video',
	INSTALL_PATH.'/thumbs',
	INSTALL_PATH.'/settings',
	INSTALL_PATH.'/cache',
);

$gen_thumbnail = INSTALL_PATH.'/gen-thumbnail.sh';
if( defined('GEN_THUMBNAIL') )
	$gen_thumbnail = GEN_THUMBNAIL;


$exec_files = array(
	COMPLETE_CMD,
	INSTALL_PATH.'/shepherd.php',
	INSTALL_PATH.'/sheepdog.php',
	INSTALL_PATH.'/collie.php',
	INSTALL_PATH.'/airwavesSheep.php',
	INSTALL_PATH.'/trans_manager.php',
	INSTALL_PATH.'/scoutEpg.php',
	INSTALL_PATH.'/repairEpg.php',
	INSTALL_PATH.'/showEXmem.php',
	INSTALL_PATH.'/resetEXmem.php',
	INSTALL_PATH.'/epgwakealarm.php',
	$gen_thumbnail,
);

echo '<p><b>ディレクトリのパーミッションチェック（777）</b></p>';
echo '<div>';
foreach($rw_dirs as $value ) {
	echo $value;
	
	$perm = getPerm( $value );
	if( $perm != '777' ) {
		exit('<font color="red">...'.$perm.'... missing</font><br>このディレクトリを書き込み許可にしてください（ex. chmod 777 '.$value.'）</div>' );
	}
	echo '...'.$perm.'...ok<br>';
}
echo '</div>';


echo '<p><b>ファイルのパーミッションチェック（755）</b></p>';
echo '<div>';
foreach($exec_files as $value ) {
	echo $value;
	
	$perm = getPerm( $value );
	if( !($perm == '755' || $perm == '775' || $perm == '777') ) {
		exit('<font color="red">...'.$perm.'... missing</font><br>このファイルを実行可にしてください（ex. chmod 755 '.$value.'）</div>');
	}
	echo '...'.$perm.'...ok<br>';
}
echo '</div>';

if( !file_exists( '/usr/local/bin/grscan' ) ) {

echo '<p><b>地上デジタルチャンネルの設定確認</b></p>';

echo '<div>現在、config.phpでは以下のチャンネルの受信が設定されています。受信不可能なチャンネルが混ざっていると番組表が表示できません。</div>';

echo '<ul>';
foreach( $GR_CHANNEL_MAP as $key => $value ) {
	echo '<li>物理チャンネル'.$value.'</li>';
}
echo '</ul>';

echo '<p><a href="step2.php">以上を確認し次の設定に進む</a></p>';

}
else {

echo'<p><b>地上デジタルチャンネルの設定</b><p>';
echo '
<form method="post" action="grscan.php" >
<div>地上デジタルチャンネルスキャンを開始します。スキャンにはおよそ10～20分程度はかかります。ケーブルテレビをお使いの方は下のチェックボックスをオンにしてください</div>
  <div>ケーブルテレビを使用:<input type="checkbox" name="catv" value="1" /></div>

  <input type="submit" value="スキャンを開始する" />
</form>';
}
?>
