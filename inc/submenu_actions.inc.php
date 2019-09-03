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

function ko_submenu_actions($sm_module, $action) {

	if($_SESSION["ses_userid"] == ko_get_guest_id()) return FALSE;

	//For the SM-Movements
	$pos = format_userinput($_GET["pos"], "alpha", FALSE, 5);
	$id = format_userinput($_GET["id"], "alphanum+");


	switch($action) {
		/**
			* Allgemeine Submenu-Actions
			* Move, Shade, ...
			*/
		case "move_sm_left":
			$open_left = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left"));
			$closed_left = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left_closed"));
			if(in_array($id, $closed_left) || in_array($id, $open_left)) break;

			$open_right = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right"));
			$closed_right = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right_closed"));
		
			//Zustand finden
			if(in_array($id, $closed_right)) {  //Falls bisher geschlossen
				$state = "_closed";
			}
			if(in_array($id, $open_right)) {  //Falls bisher offen
				$state = "";
			}
			//Nach links schieben
			$new_left = ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left".$state);
			$new_left .= ($new_left == "") ? $id : ",".$id;
			ko_save_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left".$state, $new_left);
			//Und rechts löschen
			$right = ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right".$state);
			$new_right = "";
			foreach(explode(",", $right) as $c) {
				if($c != $id) {
					$new_right .= $c.",";
				}
			}
			ko_save_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right".$state, mb_substr($new_right,0,-1));
		break;

		case "move_sm_right":
			$open_right = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right"));
			$closed_right = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right_closed"));
			if(in_array($id, $closed_right) || in_array($id, $open_right)) break;

			$open_left = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left"));
			$closed_left = explode(",", ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left_closed"));
		
			//Zustand finden
			if(in_array($id, $closed_left)) {  //Falls bisher geschlossen
				$state = "_closed";
			}
			if(in_array($id, $open_left)) {  //Falls bisher offen
				$state = "";
			}
			//Nach links schieben
			$new_right = ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right".$state);
			$new_right .= ($new_right == "") ? $id : ",".$id;
			ko_save_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_right".$state, $new_right);
			//Und rechts löschen
			$left = ko_get_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left".$state);
			$new_left = "";
			foreach(explode(",", $left) as $c) {
				if($c != $id) {
					$new_left .= $c.",";
				}
			}
			ko_save_userpref($_SESSION["ses_userid"], "submenu_".$sm_module."_left".$state, mb_substr($new_left,0,-1));
		break;
	}//switch(action)
}//ko_submenu_actions
?>
