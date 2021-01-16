<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004,2013 SoftNews Media Group
=====================================================
 ������ ��� ������� ���������� �������
=====================================================
 ����: rss.php
-----------------------------------------------------
 ����������: ������� ��������
=====================================================
*/

define( 'DATALIFEENGINE', true );
define( 'ROOT_DIR', '..' );
define( 'ENGINE_DIR', dirname( __FILE__ ) );

@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', true );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );

include ENGINE_DIR . '/data/config.php';

if( $config['http_home_url'] == "" ) {

	$config['http_home_url'] = explode( "engine/rss.php", $_SERVER['PHP_SELF'] );
	$config['http_home_url'] = reset( $config['http_home_url'] );
	$config['http_home_url'] = "http://" . $_SERVER['HTTP_HOST'] . $config['http_home_url'];

}

require_once ENGINE_DIR . '/classes/mysql.php';
include_once ENGINE_DIR . '/data/dbconfig.php';
include_once ENGINE_DIR . '/modules/functions.php';
require_once ENGINE_DIR . '/classes/templates.class.php';
include_once ROOT_DIR . '/language/' . $config['langs'] . '/website.lng';

check_xss();
$_TIME = time() + ($config['date_adjust'] * 60);

if (isset ($_REQUEST['do']) ) $do = totranslit ( $_REQUEST['do'] ); else $do = "";
if (isset ($_REQUEST['subaction']) ) $subaction = totranslit ($_REQUEST['subaction']); else $subaction = "";
if ( isset ($_REQUEST['doaction']) ) $doaction = totranslit ($_REQUEST['doaction']); else $doaction = "";
if ($do == "tags" AND !$_GET['tag']) $do = "alltags";

$dle_module = $do;
if ($do == "" and ! $subaction and $year) $dle_module = "date";
elseif ($do == "" and $catalog) $dle_module = "catalog";
elseif ($do == "") $dle_module = $subaction;
if ($subaction == '' AND $newsid) $dle_module = "showfull";
$dle_module = $dle_module ? $dle_module : "main";

$tpl = new dle_template( );
$tpl->dir = ROOT_DIR . '/templates';
define( 'TEMPLATE_DIR', $tpl->dir );

//####################################################################################################################
//                    ����������� ��������� � �� ���������
//####################################################################################################################
$cat_info = get_vars( "category" );

if( ! $cat_info ) {
	$cat_info = array ();

	$db->query( "SELECT * FROM " . PREFIX . "_category ORDER BY posi ASC" );
	while ( $row = $db->get_row() ) {

		$cat_info[$row['id']] = array ();

		foreach ( $row as $key => $value ) {
			$cat_info[$row['id']][$key] = $value;
		}

	}
	set_vars( "category", $cat_info );
	$db->free();
}
//################# ����������� ����� �������������
$user_group = get_vars( "usergroup" );

if( ! $user_group ) {
	$user_group = array ();

	$db->query( "SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC" );

	while ( $row = $db->get_row() ) {

		$user_group[$row['id']] = array ();

		foreach ( $row as $key => $value ) {
			$user_group[$row['id']][$key] = $value;
		}

	}
	set_vars( "usergroup", $user_group );
	$db->free();
}

$member_id['user_group'] = 5;

if( isset( $_GET['year'] ) ) $year = intval( $_GET['year'] ); else $year = '';
if( isset( $_GET['month'] )) $month = @$db->safesql ( sprintf("%02d", intval ( $_GET['month'] ) ) ); else $month = '';
if( isset( $_GET['day'] )) $day = @$db->safesql ( sprintf("%02d", intval ( $_GET['day'] ) ) ); else $day = '';
if( isset( $_GET['news_name'] ) ) $news_name = @$db->safesql( strip_tags( str_replace( '/', '', $_GET['news_name'] ) ) ); else $news_name = '';
if( isset( $_GET['newsid'] ) ) $newsid = intval( $_GET['newsid'] ); else $newsid = 0;
if( isset( $_GET['news_page'] ) ) $news_page = intval( $_GET['news_page'] ); else $news_page = 0;

if (isset ( $_GET['catalog'] )) {

	$catalog = @strip_tags ( str_replace ( '/', '', urldecode ( $_GET['catalog'] ) ) );

	if ( $config['charset'] == "windows-1251" AND $config['charset'] != detect_encoding($catalog) ) {
		$catalog = iconv( "UTF-8", "windows-1251//IGNORE", $catalog );
	}

	$catalog = $db->safesql ( dle_substr ( $catalog, 0, 3, $config['charset'] ) );

} else $catalog = '';

if (isset ( $_GET['user'] )) {

	$user = @strip_tags ( str_replace ( '/', '', urldecode ( $_GET['user'] ) ) );

	if ( $config['charset'] == "windows-1251" AND $config['charset'] != detect_encoding($user) ) {
		$user = iconv( "UTF-8", "windows-1251//IGNORE", $user );
	}

	$user = $db->safesql ( $user );

	if( preg_match( "/[\||\'|\<|\>|\"|\!|\?|\$|\@|\/|\\\|\&\~\*\+]/", $user ) ) $user="";

} else $user = '';

if( isset( $_GET['category'] ) ) {
	if( substr( $_GET['category'], - 1, 1 ) == '/' ) $_GET['category'] = substr( $_GET['category'], 0, - 1 );
	$category = explode( '/', $_GET['category'] );
	$category = end( $category );
	$category = $db->safesql( strip_tags( $category ) );
} else
	$category = '';

if( $category != '' ) $category_id = get_ID( $cat_info, $category );
else $category_id = false;

$view_template = "rss";

$config['allow_cache'] = true;
$config['allow_banner'] = false;
$config['rss_number'] = intval( $config['rss_number'] );
$config['rss_format'] = intval( $config['rss_format'] );
$cstart = 0;

if ( $user ) $config['allow_cache'] = false;

if( $_GET['subaction'] == 'allnews' ) $config['home_title'] = $lang['show_user_news'] . ' ' . htmlspecialchars( $user, ENT_QUOTES, $config['charset'] ) . " - " . $config['home_title'];
elseif( $_GET['do'] == 'cat' ) $config['home_title'] = stripslashes( $cat_info[$category_id]['name'] ) . " - " . $config['home_title'];

$turbo = 1;

		$rss_content = <<<XML
<?xml version="1.0" encoding="windows-1251"?>
<rss xmlns:yandex="http://news.yandex.ru" xmlns:media="http://search.yahoo.com/mrss/" xmlns:turbo="http://turbo.yandex.ru" version="2.0">
<channel>
<title>{$config['home_title']}</title>
<link>{$config['http_home_url']}</link>
<description>{$config['home_title']}</description>
XML;

		$tpl->template = <<<XML
<item  turbo="true">
<link>{rsslink}</link>
<author>{author}</author>
<category>{category}</category>
<pubDate>{rssdate}</pubDate>
<turbo:content>
                <![CDATA[
                         <header>
                             <figure>
                                 <img src="{image-1}" />
                             </figure>
                             <h1>{title}</h1>
                         </header>
                         <h2>{title}</h2>
                         <p>{short-story}</p>
                      ]]>
            </turbo:content>
            <yandex:related>
                <link url="{rsslink}" img="{image-1}">{title}</link>
            </yandex:related>
</item>
XML;


	$tpl->copy_template = $tpl->template;

	include_once ENGINE_DIR . '/engine.php';

	$rss_content .= $tpl->result['content'];


$rss_content .= '</channel></rss>';

header( 'Content-type: application/xml' );
echo $rss_content;

?>