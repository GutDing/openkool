<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2003-2015 Renzo Lauper (renzo@churchtool.org)
*  (c) 2019-2020 Daniel Lerch
*  All rights reserved
*
*  This script is part of the kOOL project. The kOOL project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  kOOL is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

header('Content-Type: text/html; charset=ISO-8859-1');

$ko_path = "./";
$ko_menu_akt = "home";

include($ko_path . "inc/ko.inc");

//Redirect to SSL if needed
ko_check_ssl();

//Handle login/logout
ko_check_login();

$_SESSION["show"] = "";

$notifier = koNotifier::Instance();


//*** Plugins einlesen:
$hooks = hook_include_main("_all");
if(sizeof($hooks) > 0) foreach($hooks as $hook) include_once($hook);


/**
	* Aktionen von Frontmodulen behandeln
	*/
if(isset($_POST["action"]) && $_POST["action"] != "") $do_action = $_POST["action"];
else if(isset($_GET["action"])) {
	if($_GET["action"] == "show_adressaenderung_fields") $do_action = "show_adressaenderung_fields";
	else if($_GET["action"] == "submit_aa") $do_action = "submit_aa";
	else if($_GET["action"] == "show_single_news") $do_action = "show_single_news";
	else $do_action = "";
}
else $do_action = "";

if(FALSE === format_userinput($do_action, "alpha+", TRUE, 50)) trigger_error("invalid action: ".$do_action, E_USER_ERROR);

switch($do_action) {
}//switch(do_action)


//HOOK: Submenus einlesen
$hooks = hook_include_sm();
if(sizeof($hooks) > 0) foreach($hooks as $hook) include($hook);
?>

<!DOCTYPE html 
  PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php print $_SESSION["lang"]; ?>" lang="<?php print $_SESSION["lang"]; ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="icon" href="<?=$ko_path?>images/kOOL_logo.ico" />
<title><?php print $HTML_TITLE; ?></title>
<?php
print ko_include_css();
print ko_include_js();
include($ko_path.'inc/js-sessiontimeout.inc');
?>
</head>

<body onload="session_time_init();<?php if(isset($onload_code)) print $onload_code; ?>">

<?php
//Smarty-Templates-Engine laden
require("$ko_path/inc/smarty.inc");

/*
 * Gibt bei erfolgreichem Login das Men� aus, sonst einfach die Loginfelder
 */
include($ko_path . "menu.php");
?>



<?php
/**
	* Aktionen von Frontmodulen behandeln
	*/
switch($do_action) {



	/**
		* Ein Adress-�nderungsantrag wurde abgeschickt
		*/
	case "submit_aa":
		if(!$_POST["aa_id"]) continue;

		if(FALSE === ($aa_id = format_userinput($_POST["aa_id"], "int", TRUE))) {
			trigger_error("invalid aa_id: ".$_POST["aa_id"], E_USER_ERROR);
			exit;
		}

		//Personendaten-Array aufbauen. Aus DB oder Pseudo
		if($aa_id == -1) {
			$p = array("vorname" => format_userinput($_POST["aa_input_vorname"], "text"), "nachname" => format_userinput($_POST["aa_input_nachname"], "text"));
		} else {
			ko_get_person_by_id($aa_id, $p);
		}

		//Spalten auswerten
		$cols = db_get_columns("ko_leute_mod");
		foreach($cols as $c) {
			if(substr($c["Field"], 0, 1) != "_") {
				if($c["Type"] == "date") {  //Datum-Eingaben wieder in SQL-Format konvertieren.
					$data[$c["Field"]] = sql_datum($_POST["aa_input_".$c["Field"]]);
				} else {
					$data[$c["Field"]] = format_userinput($_POST["aa_input_".$c["Field"]], "text");
				}
			}
		}//foreach(cols as c)
	
		//In DB eintragen
		$data["_leute_id"] = $aa_id;
		$data["_bemerkung"] = format_userinput($_POST["txt_bemerkung"], "text");
		$data["_crdate"] = strftime("%Y-%m-%d %T", time());
		$data['_cruserid'] = $_SESSION['ses_userid'];
		db_insert_data("ko_leute_mod", $data);

		//Log
		$data["vorname"] = $p["vorname"];
		$data["nachname"] = $p["nachname"];
		ko_log_diff("aa_antrag", $data, $aa_id == -1 ? "" : $p);
		$notifier->addInfo(1, $do_action);
	break;  //submit_aa



	/**
		* Adress-�nderungs-Felder (oder Liste bei mehreren gleichen Namen) sollen angezeigt werden
		*/
	case "show_adressaenderung_fields":
		$aa_display = FALSE;
		if($_GET["aa_id"]) $aa_use_id = format_userinput($_GET["aa_id"], "int"); else $aa_use_id = 0;
		if($_GET["aa_nachname"]) $aa_use_nachname = format_userinput($_GET["aa_nachname"], "text"); else $aa_use_nachname = "";
		if($_GET["aa_vorname"]) $aa_use_vorname = format_userinput($_GET["aa_vorname"], "text"); else $aa_use_vorname = "";

		//Name aus Textfeldern auslesen, falls keine �bergeben wurden.
		if(!$aa_use_vorname && $_POST["submit_fm_aa"]) $aa_use_vorname = format_userinput($_POST["txt_fm_aa_vorname"], "text");
		if(!$aa_use_nachname && $_POST["submit_fm_aa"]) $aa_use_nachname = format_userinput($_POST["txt_fm_aa_nachname"], "text");

		//Vorname und Nachname m�ssen angegeben werden, denn sonst k�nnte Datenbank nach bestimmten Namen durchsucht werden...
		if((!$_POST["txt_fm_aa_nachname"] || !$_POST["txt_fm_aa_vorname"]) && !$aa_use_id) continue;

		//Sicherheitscheck: (Felder nur anzeigen, wenn ID mit Namen und Vornamen �bereinstimmen
		//(so m�ssen ID, Name und Nachname bekannt sein, um die Felder manuell anzuzeigen)
		if($aa_use_id > 0) {
			ko_get_person_by_id($aa_use_id, $p);
			if($p["vorname"] != $aa_use_vorname || $p["nachname"] != $aa_use_nachname) continue;
			unset($p);
		}

		//Auf vorhandenen Eintrag pr�fen und ID(s) merken
		if(!$aa_use_id) {
			$ids = ko_fuzzy_search(array("vorname" => $aa_use_vorname, "nachname" => $aa_use_nachname), "ko_leute", 1, FALSE, 3);
			if(is_array($ids)) $fm_aa_ids = $ids;
		} else {
			$fm_aa_ids = array($aa_use_id);
		}

		//Show form for new if no entry was found in db
		if(sizeof($fm_aa_ids) == 0) {
			$fm_aa_ids[] = -1;
			$aa_display = TRUE;
		}

		//Falls mehrere IDs gefunden: Liste anzeigen und einen ausw�hlen lassen. (Adresse und Geburtsdatum)
		//Dies ist auch der Fall, wenn eine neue Person schon vorhanden ist.
		//Nur 5 identische Namen erlauben, dar�ber wird es verd�chtig... (z.B. SQL-Injection OR 1=1...)
		if(sizeof($fm_aa_ids) > 1 && sizeof($fm_aa_ids < 5)) {
			$c = 0;
			foreach ($fm_aa_ids as $i) {
				if($i == -1) {  //Neue Person
					$fm_aa_list[$c]["id"] = $i;
					$fm_aa_list[$c]["vorname"] = $aa_use_vorname;
					$fm_aa_list[$c]["nachname"] = $aa_use_nachname;
				} else {
					ko_get_person_by_id($i, $p);
					$fm_aa_list[$c]["id"] = $i;
					$fm_aa_list[$c]["vorname"] = $p["vorname"];
					$fm_aa_list[$c]["nachname"] = $p["nachname"];
					$fm_aa_list[$c]["adresse"] = getLL("from")." ".$p["ort"]." (".sql2datum($p["geburtsdatum"]).")";
				}
				$c++;
			}
			$aa_info = getLL("aa_double_choose");
			$smarty->assign("tpl_aa_info", $aa_info);
			$smarty->assign("tpl_aa_show", "list");
			$smarty->assign("tpl_label_new", getLL("new"));
			$smarty->assign("tpl_aa_list", $fm_aa_list);
			$aa_display = TRUE;
		}


		//Falls genau eine ID gefunden (auch -1 f�r neu...), dann diese zum Bearbeiten ausgeben
		if(sizeof($fm_aa_ids) == 1) {
			$smarty->assign("tpl_aa_show", "fields");
			if($fm_aa_ids[0] > 0) ko_get_person_by_id($fm_aa_ids[0], $p);
			$cols = db_get_columns("ko_leute_mod");
			
			//Only fill in values if ALL rights for people module
			$do_fillout = ko_module_installed('leute') && ko_get_access_all('leute') > 0;

			$tpl_input = array();
			$counter = 0;
			$col_namen = ko_get_leute_col_name();
			foreach($cols as $c) {
				if(substr($c["Field"], 0, 1) != "_") {  //Alle Spalten, die mit "_" beginnen, ignorieren
					$tpl_input[$counter]["name"] = "aa_input_".$c["Field"];
					$tpl_input[$counter]["desc"] = $col_namen[$c["Field"]];
					//Vor- und Nachname immer ausgeben, denn diese d�rfen immer angezeigt werden, da diese ja vorher selber eingegeben wurden.
					if($do_fillout || (!$do_fillout && ($c["Field"]=="vorname" || $c["Field"]=="nachname")))
						$tpl_input[$counter]["value"] = ($fm_aa_ids[0] == -1) ? ${"aa_use_".$c["Field"]} : $p[$c["Field"]];
					else
						$tpl_input[$counter]["value"] = "";

					if(substr($c["Type"], 0, 7) == "varchar" || substr($c["Type"], 0, 4) == "date") {
						$tpl_input[$counter]["type"] = "text";
					}
					if(substr($c["Type"], 0, 4) == "date") {
						$tpl_input[$counter]["value"] = ($do_fillout) ? sql2datum($tpl_input[$counter]["value"]) : "";
					}
					if(substr($c["Type"], 0, 4) == "enum") {
						$tpl_input[$counter]["type"] = "select";
						$tpl_input[$counter]["values"] = db_get_enums("ko_leute_mod", $c["Field"]);
						$tpl_input[$counter]["descs"] = db_get_enums_ll("ko_leute_mod", $c["Field"]);
					}
					$counter++;
				}
			}//foreach(cols as c)

			//Show info about the found entry to be edited
			if($fm_aa_ids[0] == -1) {  //new
				$smarty->assign("tpl_aa_info", getLL("fm_aa_info_new"));
				$smarty->assign("title_new", getLL("fm_aa_comment_new"));
			} else {
				$smarty->assign("tpl_aa_info", $p["vorname"]." ".$p["nachname"]);
				$smarty->assign("title_edit", getLL("fm_aa_comment_edit"));
			}
			$smarty->assign("tpl_input", $tpl_input);
			$smarty->assign("tpl_aa_id", $fm_aa_ids[0]);
			$smarty->assign("label_comment", getLL("fm_aa_comment"));
			$smarty->assign("label_ok", getLL("OK"));
			$aa_display = TRUE;
		}//if(sizeof(fm_aa_ids) == 1)

		$smarty->assign("tpl_fm_title", getLL("fm_aa_title"));
		if($aa_display) $aa_content = $smarty->fetch("ko_fm_adressaenderung.tpl");
	break;


	//Default:
	default:
		$abuse = true;
	break;
}//switch(do_action)


print '<main class="main">';

//Infos ausgeben
if($notifier->hasNotifications(koNotifier::ALL)) {
	$notifier->notify();
}


if ($abuse && $do_action) {
	include($ko_path."inc/abuse.inc");
}
else {
	$linearFrontModules = array();
	foreach ($FRONTMODULES_LAYOUT as $frontModule) {
		$linearFrontModules = array_merge($linearFrontModules, $frontModule);
	}
	$frontModulesUPOld = explode(',', ko_get_userpref($_SESSION['ses_userid'], 'front_modules'));
	$frontModulesUP = array();
	foreach ($frontModulesUPOld as $name) {
		if (!$name) continue;
		if (in_array($name, $linearFrontModules)) {
			$frontModulesUP[] = $name;
		}
	}
	ko_save_userpref($_SESSION['ses_userid'], 'front_modules', implode(',', $frontModulesUP));

	print '<div class="row" id="front-modules-container">';
	if ($aa_display) {
		print '<div class="col col-xs-12 col-sm-6 col-sm-offset-3 col-md-4 col-md-offset-4 col-lg-4 col-lg-offset-4">';
		print $aa_content;
		print '</div>';
	}
	else {
		$i = 0;
		foreach ($FRONTMODULES_LAYOUT as $fmCol) {
			$code = '';
			foreach ($fmCol as $fm) {
				if (!ko_check_fm_for_user($fm, $_SESSION['ses_userid'])) continue;
				$cont = FALSE;
				switch ($fm) {
					case 'adressaenderung':
						if (!is_array($access['leute'])) ko_get_access('leute');
						if ($access['leute']['ALL'] >= 2) $cont = TRUE;
					break;
					case 'daten_cal':
						if (!is_array($access['daten'])) ko_get_access('daten');
						if ($access['daten']['MAX'] < 1) $cont = TRUE;
					break;
				}
				$state = in_array($fm, $frontModulesUP) ? 'open' : 'closed';
				if ($state == 'open' || $_SESSION['ses_userid'] != ko_get_guest_id()) {
					$fmContent = ko_front_module($_SESSION["ses_userid"], $fm, $i, 0, $state);
					if ($fmContent !== False) {
						$code .= $fmContent;
					}
				}
			}
			if ($code != '') {
				switch ($i) {
					case 0:
						print '<div class="col col-xs-12 col-sm-3 col-md-2">';
					break;
					case 1:
						print '<div class="col col-xs-12 col-sm-6 col-md-8">';
						print '<div class="col col-sm-12 col-md-6">';
					break;
					case 2:
						print '<div class="col col-sm-12 col-md-6">';
					break;
					case 3:
						print '<div class="col col-xs-12 col-sm-3 col-md-2">';
					break;
				}
				print $code;
				print '</div>';
				if ($i == 2) print '</div>';
			}
			$i ++;
		}
	}
	print '</div>';

	print '</div>';
}


print '</main>';





//--- copyright notice on frontpage:
//--- Obstructing the appearance of this notice is prohibited by law.
print '<div id="footer" style="text-align:center;">';
$copyrightNotice = sprintf(getLL("copyright_notice"), VERSION, '<a href="https://github.com/daniel-lerch/openkool/graphs/contributors">', '</a>');
print '<a href="https://www.churchtool.org"><b>'.getLL("kool")."</b></a> $copyrightNotice<br />";
if(defined("WARRANTY_GIVER") && WARRANTY_GIVER != '') {
	print sprintf(getLL("copyright_warranty"), '<a href="'.WARRANTY_URL.'">'.WARRANTY_GIVER.'</a> ');
} else {
	print getLL("copyright_no_warranty")." ";
}
print sprintf(getLL("copyright_free_software"), '<a href="http://www.fsf.org/licensing/licenses/gpl.html">', '</a>')."<br />";
print getLL("copyright_obstruction");
print '</div>';
//--- end of copyright notice
?>

</body>

</html>
