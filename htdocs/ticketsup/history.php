<?php
/* Copyright (C) - 2013-2016 Jean-François FERRY    <hello@librethic.io>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *    History of ticket
 *
 *    @package ticketsup
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/ticketsup/class/actions_ticketsup.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formticketsup.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/ticketsup.lib.php';
require_once DOL_DOCUMENT_ROOT . "/core/lib/company.lib.php";
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

if (!class_exists('Contact')) {
    include DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
}

// Load traductions files requiredby by page
$langs->load("companies");
$langs->load("other");
$langs->load("ticketsup@ticketsup");

// Get parameters
$id = GETPOST('id', 'int');
$track_id = GETPOST('track_id', 'alpha', 3);
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'alpha', 3);

// Security check
if (!$user->rights->ticketsup->read) {
    accessforbidden();
}

$object = new ActionsTicketsup($db);

$object->doActions($action);

$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label($object->dao->table_element);

if (!$action) {
    $action = 'view';
}

/***************************************************
 * PAGE
 *
 * Put here all code to build page
 ****************************************************/

$help_url = 'FR:DocumentationModuleTicket';
$page_title = $object->getTitle($action);
llxHeader('', $page_title, $help_url);

$userstat = new User($db);
$form = new Form($db);
$formticket = new FormTicketsup($db);

if ($action == 'view') {
    $res = $object->fetch($id, $track_id, $ref);

    if ($res > 0) {
        // restrict access for externals users
        if ($user->societe_id > 0 && ($object->dao->fk_soc != $user->societe_id)
        ) {
            accessforbidden('', 0);
        }
        // or for unauthorized internals users
        if (!$user->societe_id && ($conf->global->TICKETS_LIMIT_VIEW_ASSIGNED_ONLY && $object->dao->fk_user_assign != $user->id) && !$user->rights->ticketsup->manage) {
            accessforbidden('', 0);
        }

        if ($object->dao->fk_soc > 0) {
            $object->dao->fetch_thirdparty();
            $head = societe_prepare_head($object->dao->thirdparty);
            dol_fiche_head($head, 'ticketsup', $langs->trans("ThirdParty"), 0, 'company');
            dol_banner_tab($object->dao->thirdparty, 'socid', '', ($user->societe_id ? 0 : 1), 'rowid', 'nom');
            dol_fiche_end();
        }

        if (!$user->societe_id && $conf->global->TICKETS_LIMIT_VIEW_ASSIGNED_ONLY) {
            $object->next_prev_filter = "te.fk_user_assign = '" . $user->id . "'";
        } elseif ($user->societe_id > 0) {
            $object->next_prev_filter = "te.fk_soc = '" . $user->societe_id . "'";
        }
        $head = ticketsup_prepare_head($object->dao);
        dol_fiche_head($head, 'tabTicketLogs', $langs->trans("Ticket"), 0, 'ticketsup@ticketsup');
        $object->dao->label = $object->dao->ref;
        // Author
        if ($object->dao->fk_user_create > 0) {
            $object->dao->label .= ' - ' . $langs->trans("CreatedBy") . '  ';
            $langs->load("users");
            $fuser = new User($db);
            $fuser->fetch($object->dao->fk_user_create);
            $object->dao->label .= $fuser->getNomUrl(0);
        }
        $linkback = '<a href="' . dol_buildpath('/ticketsup/list.php', 1) . '"><strong>' . $langs->trans("BackToList") . '</strong></a> ';
        $object->dao->ticketsup_banner_tab('ref', '', ($user->societe_id ? 0 : 1), 'ref', 'subject', '', '', '', $morehtmlleft, $linkback);

        dol_fiche_end();

        print '<div class="fichecenter">';
        // Logs list
        print load_fiche_titre($langs->trans('TicketHistory'), '', 'history@ticketsup');
        $object->viewTimelineTicketLogs();
        print '</div><!-- fichecenter -->';
        print '<br style="clear: both">';
    }
} // End action view

// End of page
llxFooter('');
$db->close();
