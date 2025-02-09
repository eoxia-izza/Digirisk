<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	    \file       view/ticket/ticketstats.php
 *      \ingroup    digiriskdolibarr
 *		\brief      Page with tickets statistics
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if ( ! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if ( ! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res          = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if ( ! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if ( ! $res && file_exists("../../main.inc.php")) $res    = @include "../../main.inc.php";
if ( ! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if ( ! $res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if ( ! $res) die("Include of main fails");

// Global variables definitions
global $conf, $db, $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
if (!empty($conf->category->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
}

require_once __DIR__ . '/../../lib/digiriskdolibarr_ticket.lib.php';
require_once __DIR__ . '/../../class/ticketdigiriskstats.class.php';
require_once __DIR__ . '/../../class/digiriskelement.class.php';

$WIDTH  = DolGraph::getDefaultGraphSizeForStats('width');
$HEIGHT = DolGraph::getDefaultGraphSizeForStats('height');

// Load translation files required by the page
$langs->loadLangs(array('orders', 'companies', 'other', 'tickets', 'categories'));

if (!$user->rights->ticket->read) {
	accessforbidden();
}

$action              = GETPOST('action', 'aZ09');
$object_status       = GETPOST('object_status', 'array');
$userid              = GETPOST('userid', 'int');
$userassignid        = GETPOST('userassignid', 'int');
$socid               = GETPOST('socid', 'int');
$digiriskelementid   = GETPOST('digiriskelementid', 'int');
$categticketid       = GETPOST('categticketid', 'int');
$ticketcats          = GETPOST('ticketcats', 'array');
$digiriskelementlist = GETPOST('digiriskelementlist', 'array');

// Initialize technical objects
$object          = new Ticket($db);
$digiriskelement = new DigiriskElement($db);

// Security check
if ($user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$upload_dir = $conf->digiriskdolibarr->multidir_output[$conf->entity];
$upload_dir = $upload_dir . '/ticketstats/';
dol_mkdir($upload_dir);

$nowyear   = strftime("%Y", dol_now());
$year      = GETPOST('year') > 0 ? GETPOST('year', 'int') : $nowyear;
//$startyear = $year - (empty($conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS) ? 2 : max(1, min(10, $conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS)));
$date_start = dol_mktime(0, 0, 0, GETPOST('datestartmonth', 'int'), GETPOST('datestartday', 'int'), GETPOST('datestartyear', 'int'));
$date_end   = dol_mktime(0, 0, 0, GETPOST('dateendmonth', 'int'), GETPOST('dateendday', 'int'), GETPOST('dateendyear', 'int'));
$startyear = strftime("%Y", !empty($date_start) ? $date_start : dol_now());
$endyear   = strftime("%Y", !empty($date_end) ? $date_end : dol_now() + 1);
$datestart = dol_print_date((!empty($date_start) ? $date_start : dol_now()), 'dayxcard');
$dateend   = dol_print_date((!empty($date_end) ? $date_end : dol_now()), 'dayxcard');

/*
 * Action
 */

if ($action == 'savegraph') {
	$data = json_decode(file_get_contents('php://input'), true);

	$data = $data['image'];

	list($type, $data) = explode(';', $data);
	list(, $data)      = explode(',', $data);
	$data = base64_decode($data);
	$filenamenb = $upload_dir.'/ticketdigiriskstats.png';
	file_put_contents($filenamenb, $data);
}

/*
 * View
 */

$form   = new Form($db);

$title = $langs->trans("TicketStatistics");

llxHeader('', $title);

print load_fiche_titre($title, '', 'ticket');

$head = ticketPrepareHead();
print dol_get_fiche_head($head, 'byyear', $langs->trans("TicketStatistics"), -1);

$stats = new TicketDigiriskStats($db, $socid, ($userid > 0 ? $userid: 0), ($userassignid > 0 ? $userassignid: 0), ($digiriskelementid > 0 ? $digiriskelementid : 0), ($categticketid > 0 ? $categticketid: 0));
if (is_array($object_status)) {
	if (in_array($langs->trans('All'), $object_status)) {
		unset($object_status[array_search($langs->trans('All'), $object_status)]);
	} else {
		if (!empty($object_status)) {
			$stats->where .= ' AND tk.fk_statut IN ('.$db->sanitize(implode(',', $object_status)).')';
		} else if (!empty(GETPOST('refresh', 'int'))) {
			$stats->where .= ' AND tk.fk_statut IS NULL';
		}
	}
}
if (is_array($ticketcats)) {
	if (in_array($langs->trans('All'), $ticketcats)) {
		unset($ticketcats[array_search($langs->trans('All'), $ticketcats)]);
	} else {
		$stats->from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_ticket as cattk ON (tk.rowid = cattk.fk_ticket)';
		if (!empty($ticketcats)) {
			$stats->where .= ' AND cattk.fk_categorie IN ('.$db->sanitize(implode(',', $ticketcats)).')';
		} else if (!empty(GETPOST('refresh', 'int'))) {
			$stats->where .= ' AND cattk.fk_categorie IS NULL';
		}
	}
}
if (is_array($digiriskelementlist)) {
	if (in_array($langs->trans('All'), $digiriskelementlist)) {
		unset($digiriskelementlist[array_search($langs->trans('All'), $digiriskelementlist)]);
	} else {
		$stats->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'ticket_extrafields as tkextra ON tk.rowid = tkextra.fk_object';
		$stats->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'digiriskdolibarr_digiriskelement as e ON tkextra.digiriskdolibarr_ticket_service = e.rowid';
		if (!empty($digiriskelementlist)) {
			$stats->where .= ' AND e.rowid IN ('.$db->sanitize(implode(',', $digiriskelementlist)).')';
		} else if (!empty(GETPOST('refresh', 'int'))) {
			$stats->where .= ' AND e.rowid IS NULL';
		}
	}
}

// Build graphic number of object
$data = $stats->getNbByMonthWithPrevYear($endyear, $startyear, 0, 0, $conf->global->SOCIETE_FISCAL_MONTH_START);

if (empty($user->rights->societe->client->voir) || $user->socid) {
	$filenamenb = $upload_dir.'/ticketdigiriskstats-'.$user->id.'_'.$datestart.'_'.$dateend.'.png';
	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=ticketdigiriskstats&file=ticketdigiriskstats-'.$user->id.'_'.$datestart.'_'.$dateend.'.png';
} else {
	$filenamenb = $upload_dir.'/ticketdigiriskstats_'.$datestart.'_'.$dateend.'.png';
	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=ticketdigiriskstats&file=ticketdigiriskstats_'.$datestart.'_'.$dateend.'.png';
}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (!$mesg) {
	$px1->SetData($data);
	$i = $startyear; $legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px1->SetLegend($legend);
	$px1->SetMaxValue($px1->GetCeilMaxValue());
	$px1->SetMinValue(min(0, $px1->GetFloorMinValue()));
	$px1->SetWidth($WIDTH);
	$px1->SetHeight($HEIGHT);
	$px1->SetYLabel($langs->trans("NbOfTicket"));
	$px1->SetShading(3);
	$px1->SetHorizTickIncrement(1);
	$px1->mode = 'depth';
	$px1->SetTitle($langs->trans("NumberOfTicketsByMonth"));

	$px1->draw($filenamenb, $fileurlnb);
} ?>

<script>
	var checkExist = setInterval(function() {
		if ($("#canvas_ticketdigiriskstats_<?php echo $datestart . '_' . $dateend; ?>_png").length) {
			clearInterval(checkExist);
			var canvas = document.getElementById("canvas_ticketdigiriskstats_<?php echo $datestart . '_' . $dateend; ?>_png")
			var image = canvas.toDataURL()
			let querySeparator = '?'
			document.URL.match(/\?/) ? querySeparator = '&' : 1

			let token = $('.ticketstats').find('input[name="token"]').val();

			$.ajax({
				url: document.URL + querySeparator + "action=savegraph&token=" + token,
				type: "POST",
				processData: false,
				contentType: 'application/octet-stream',
				data: JSON.stringify({
					image: image,
				}),
				success: function (resp) {
				},
				error: function (resp) {
				}
			});
		}
	}, 500)
</script>

<?php
// Show array
$data = $stats->getAllByYear();
$arrayyears = array();
foreach ($data as $val) {
	if (!empty($val['year'])) {
		$arrayyears[$val['year']] = $val['year'];
	}
}
if (!count($arrayyears)) {
	$arrayyears[$nowyear] = $nowyear;
}

print '<div class="fichecenter"><div class="fichethirdleft">';

// Show filter box
print '<form class="ticketstats" name="stats" method="POST" action="'.$_SERVER["PHP_SELF"].'?refresh=1">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="liste_titre" colspan="2">'.$langs->trans("Filter").'</td></tr>';
// Company
print '<tr><td class="left">'.$form->textwithpicto($langs->trans("ThirdParty"), $langs->trans("ThirdPartyHelp")).'</td><td class="left">';
print img_picto('', 'company', 'class="pictofixedwidth"');
print $form->select_company($socid, 'socid', '', 1, 0, 0, array(), 0, 'widthcentpercentminusx maxwidth300');
print '</td></tr>';
// DigiriskElement
print '<tr><td class="left">'.$form->textwithpicto($langs->trans("GP/UT"), $langs->trans("GP/UTHelp")).'</td><td class="left">';
$digiriskelementlist = $digiriskelement->select_digiriskelement_list($digiriskelementid, 'digiriskelementid', '', 1, 0, array(), 1);
$digiriskelementlist[$langs->trans('All')] = $langs->trans('All');
print $form->multiselectarray('digiriskelementlist', $digiriskelementlist, ((!empty(GETPOST('refresh', 'int'))) ? GETPOST('digiriskelementlist', 'array') : $digiriskelementlist), 0, 0, 'widthcentpercentminusx maxwidth300');
print '</td></tr>';
// Category
if (!empty($conf->category->enabled)) {
	$cat_type = Categorie::TYPE_TICKET;
	$cat_label = $langs->trans("Category") . ' ' .lcfirst($langs->trans("Ticket"));
	print '<tr><td>'.$form->textwithpicto($cat_label, $langs->trans("CategoryTicketHelp")).'</td><td>';
	$cate_arbo = $form->select_all_categories($cat_type, null, 'parent', null, null, 1);
	print img_picto('', 'category', 'class="pictofixedwidth"');
	$cate_arbo[$langs->trans('All')] = $langs->trans('All');
	print $form->multiselectarray('ticketcats', $cate_arbo, ((!empty(GETPOST('refresh', 'int'))) ? GETPOST('ticketcats', 'array') : $cate_arbo), 0, 0, 'widthcentpercentminusx maxwidth300');
	print '</td></tr>';
}
// User
print '<tr><td class="left">'.$form->textwithpicto($langs->trans("CreatedBy"), $langs->trans("CreatedByHelp")) .'</td><td class="left">';
print img_picto('', 'user', 'class="pictofixedwidth"');
print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', $conf->entity, 0, 0, '', 0, '', 'widthcentpercentminusx maxwidth300');
// Assign at user
print '<tr><td class="left">'.$form->textwithpicto($langs->trans("AssignedTo"), $langs->trans("AssignedToHelp")).'</td><td class="left">';
print img_picto('', 'user', 'class="pictofixedwidth"');
print $form->select_dolusers($userassignid, 'userassignid', 1, '', 0, '', '', $conf->entity, 0, 0, '', 0, '', 'widthcentpercentminusx maxwidth300');
// Status
print '<tr><td class="left">'.$form->textwithpicto($langs->trans("Status"), $langs->trans("StatusHelp")).'</td><td class="left">';
$liststatus = $object->statuts_short;
$liststatus[$langs->trans('All')] = $langs->trans('All');
print $form->multiselectarray('object_status', $liststatus, ((!empty(GETPOST('refresh', 'int'))) ? GETPOST('object_status', 'array') : $liststatus), 0, 0, 'widthcentpercentminusx maxwidth300', 1);
print '</td></tr>';
//DateRange -- Plage de date
if (!empty($conf->global->SOCIETE_FISCAL_MONTH_START)) {
	$startday = dol_mktime(0, 0, 0, $conf->global->SOCIETE_FISCAL_MONTH_START, 1, strftime("%Y", dol_now()));
} else {
	$startday = dol_now();
}
print '<tr><td class="left">' . $langs->trans("DateRange") . '</td><td class="left">';
print $langs->trans('From') . $form->selectDate((!empty($date_start) ? $date_start : $startday), 'datestart', 0, 0, 0, '', 1);
print $langs->trans('At') . $form->selectDate((!empty($date_end) ? $date_end : dol_time_plus_duree($startday, 1, 'y')), 'dateend', 0, 0, 0, '', 1);
print '</td></tr>';
print '<tr><td class="center" colspan="2"><input type="submit" name="submit" class="button small" value="'.$langs->trans("Refresh").'"></td></tr>';
print '</table>';
print '</form>';

print '<br><br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre" height="24">';
print '<td class="center">'.$langs->trans("Year").'</td>';
print '<td class="right">'.$langs->trans("NbOfTickets").'</td>';
print '<td class="right">%</td>';
print '</tr>';

$oldyear = 0;
foreach ($data as $val) {
	$year = $val['year'];
	while (!empty($year) && $oldyear > $year + 1) { // If we have empty year
		$oldyear--;

		print '<tr class="oddeven" height="24">';
		print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?year='.$oldyear.($socid > 0 ? '&socid='.$socid : '').($userid > 0 ? '&userid='.$userid : '').'">'.$oldyear.'</a></td>';
		print '<td class="right">0</td>';
		print '<td class="right"></td>';
		print '</tr>';
	}

	print '<tr class="oddeven" height="24">';
	print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?year='.$year.($socid > 0 ? '&socid='.$socid : '').($userid > 0 ? '&userid='.$userid : '').'">'.$year.'</a></td>';
	print '<td class="right">'.$val['nb'].'</td>';
	print '<td class="right" style="'.(($val['nb_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.round($val['nb_diff']).'</td>';
	print '</tr>';
	$oldyear = $year;
}

print '</table>';
print '</div>';

// Get list of files
if ( ! empty($upload_dir)) {
	$file_list = dol_dir_list($upload_dir, 'files', 0, '(\.png)', '', 'date', SORT_DESC, 1);
}

print load_fiche_titre($langs->trans("Documents"), '', 'digiriskdolibarr@digiriskdolibarr');

// Show table
print '<div class="div-table-responsive-no-min">';
print '<table class="liste noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="liste_titre center" colspan="3"></th>';
print '</tr>';

// Get list of files
if ( ! empty($upload_dir)) {
	// Loop on each file found
	if (is_array($file_list)) {
		foreach ($file_list as $file) {
			// Show file name with link to download
			print '<tr class="oddeven">';
			print '<td class="minwidth200">';
			print '<a class="documentdownload paddingright" href="' . DOL_URL_ROOT . '/document.php?modulepart=digiriskdolibarr&file=' . urlencode('ticketstats/'.$file['name']) . '&entiy='.$conf->entity . '">';
			print  img_mime($file["name"], $langs->trans("File") . ': ' . $file["name"]);
			print  dol_trunc($file["name"], 150);
			print  '</a>';
			print  '</td>';

			// Show file size
			$size = (!empty($file['size']) ? $file['size'] : dol_filesize($upload_dir . "/" . $file["name"]));
			print '<td class="nowrap right">' . dol_print_size($size, 1, 1) . '</td>';

			// Show file date
			$date = (!empty($file['date']) ? $file['date'] : dol_filemtime($upload_dir . "/" . $file["name"]));
			print '<td class="nowrap right">' . dol_print_date($date, 'dayhour', 'tzuser') . '</td>';
			print '</tr>';
		}
	}
}

print '</table>';
print '</div>';

print '</div><div class="fichetwothirdright">';

// Show graphs
print '<table class="border centpercent"><tr class="pair nohover"><td class="center">';
if ($mesg) {
	print $mesg;
} else {
	print $px1->show();
}
print '</td></tr></table>';

print '</div></div>';
print '<div style="clear:both"></div>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
