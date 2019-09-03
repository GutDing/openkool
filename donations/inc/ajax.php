<?php
/*******************************************************************************
*
*    OpenKool - Online church organization tool
*
*    Copyright © 2003-2015 Renzo Lauper (renzo@churchtool.org)
*    Copyright © 2019      Daniel Lerch
*
*    This program is free software; you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation; either version 2 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*******************************************************************************/

//Set session id from GET (session will be started in ko.inc.php)
if(!isset($_GET["sesid"])) exit;
if(FALSE === session_id($_GET["sesid"])) exit;

//Send headers to ensure UTF-8 charset
header('Content-Type: text/html; charset=UTF-8');
 
error_reporting(0);
$ko_menu_akt = 'donations';
$ko_path = "../../";
require($ko_path."inc/ko.inc.php");
$ko_path = "../";

ko_get_access('donations');
if($access['donations']['MAX'] < 1) exit;
ko_include_kota(array('ko_donations', 'ko_donations_accounts'));

// Plugins einlesen:
$hooks = hook_include_main("donations");
if(sizeof($hooks) > 0) foreach($hooks as $hook) include_once($hook);
 
//Smarty-Templates-Engine laden
require($BASE_PATH."inc/smarty.inc.php");
 
require($BASE_PATH."donations/inc/donations.inc.php");

//HOOK: Submenus einlesen
$hooks = hook_include_sm();
if(sizeof($hooks) > 0) foreach($hooks as $hook) include($hook);

hook_show_case_pre($_SESSION['show']);

 
if(isset($_GET) && isset($_GET["action"])) {
 	$action = format_userinput($_GET["action"], "alphanum");

	hook_ajax_pre($ko_menu_akt, $action);

 	switch($action) {
 
 		case "setstart":
			//Set list start
			if(isset($_GET['set_start'])) {
				$_SESSION['show_start'] = max(1, format_userinput($_GET['set_start'], 'uint'));
	    }
			//Set list limit
			if(isset($_GET['set_limit'])) {
				$_SESSION['show_limit'] = max(1, format_userinput($_GET['set_limit'], 'uint'));
				ko_save_userpref($_SESSION['ses_userid'], 'show_limit_donations', $_SESSION['show_limit']);
	    }

			print "main_content@@@";
			if($_SESSION["show"] == "list_donations") {
				print ko_list_donations(FALSE);
			} else {
				print ko_list_accounts(FALSE);
			}
		break;


		case "setsort":
			$_SESSION["sort_donations"] = format_userinput($_GET["sort"], "alphanum+", TRUE, 80);
			$_SESSION["sort_donations_order"] = format_userinput($_GET["sort_order"], "alpha", TRUE, 4);

			print "main_content@@@";
			if($_SESSION['show'] == 'list_donations') {
				print ko_list_donations(FALSE);
			} else if($_SESSION['show'] == 'list_reoccuring_donations') {
				print ko_list_reoccuring_donations(FALSE);
			}
		break;


		case "itemlist":
			//ID and state of the clicked field
			$id = format_userinput($_GET["id"], "js");
			$state = $_GET["state"] == "true" ? "checked" : "";

			if($state == "checked") {  //Select it
				if(!in_array($id, $_SESSION["show_accounts"])) $_SESSION["show_accounts"][] = $id;
				//Move it to the place according to the list-order
				$accounts = db_select_data("ko_donations_accounts", "", "*", "ORDER by number ASC");
				foreach($accounts as $i_i => $i) {
					if($access['donations']['ALL'] < 1 && $access['donations'][$i_i] < 1) continue;
					if(in_array($i_i, $_SESSION["show_accounts"])) $new_value[] = $i_i;
				}
				$_SESSION["show_accounts"] = $new_value;
			} else {  //deselect it
				if(in_array($id, $_SESSION["show_accounts"])) $_SESSION["show_accounts"] = array_diff($_SESSION["show_accounts"], array($id));
			}
			//Save userpref
			ko_save_userpref($_SESSION["ses_userid"], "show_donations_accounts", implode(",", $_SESSION["show_accounts"]));

			print "main_content@@@";
			switch($_SESSION["show"]) {
				case "list_donations":
					ko_list_donations(FALSE);
				break;
				case "show_stats":
					ko_donations_stats(FALSE);
				break;
			}
		break;


		case "itemlistsave":
			//Find position of submenu for redraw
			if(in_array("itemlist_accounts", explode(",", $_SESSION["submenu_left"]))) $pos = "left";
			else $pos = "right";

			//save new value
			if($_GET["name"] == "") break;
			$new_value = implode(",", $_SESSION["show_accounts"]);
			$user_id = ($access['donations']['MAX'] > 3 && $_GET['global'] == 'true') ? '-1' : $_SESSION['ses_userid'];
			ko_save_userpref($user_id, format_userinput($_GET["name"], "js", FALSE, 0, array("allquotes")), $new_value, "accounts_itemset");

			print submenu_donations("itemlist_accounts", $pos, "open", 2);
		break;


		case "itemlistopen":
			//Find position of submenu for redraw
			if(in_array("itemlist_accounts", explode(",", $_SESSION["submenu_left"]))) $pos = "left";
			else $pos = "right";

			//save new value
			$name = format_userinput($_GET['name'], 'js', FALSE, 0, array(), '@');
			if($name == "") break;

			if($name == '_all_') {
				$accounts = db_select_data('ko_donations_accounts', '');
				$_SESSION["show_accounts"] = array_keys($accounts);
			} else if($name == '_none_') {
				$_SESSION['show_accounts'] = array();
			} else {
				if(mb_substr($name, 0, 3) == '@G@') $value = ko_get_userpref('-1', mb_substr($name, 3), "accounts_itemset");
				else $value = ko_get_userpref($_SESSION['ses_userid'], $name, "accounts_itemset");
				$_SESSION["show_accounts"] = explode(",", $value[0]["value"]);
			}
			ko_save_userpref($_SESSION['ses_userid'], 'show_donations_accounts', implode(',', $_SESSION['show_accounts']));

			print "main_content@@@";
			switch($_SESSION["show"]) {
				case "list_donations":
					ko_list_donations(FALSE);
				break;
				case "show_stats":
					ko_donations_stats(FALSE);
				break;
			}
			print "@@@";
			print submenu_donations("itemlist_accounts", $pos, "open", 2);
		break;


		case "itemlistdelete":
			//Find position of submenu for redraw
			if(in_array("itemlist_accounts", explode(",", $_SESSION["submenu_left"]))) $pos = "left";
			else $pos = "right";

			//save new value
			$name = format_userinput($_GET['name'], 'js', FALSE, 0, array(), '@');
			if($name == "") break;

			if(mb_substr($name, 0, 3) == '@G@') {
				if($kg_edit) ko_delete_userpref('-1', mb_substr($name, 3), "accounts_itemset");
			} else ko_delete_userpref($_SESSION['ses_userid'], $name, "accounts_itemset");

			print submenu_donations("itemlist_accounts", $pos, "open", 2);
		break;

	}//switch(action);

	hook_ajax_post($ko_menu_akt, $action);

}//if(GET[action])
?>
