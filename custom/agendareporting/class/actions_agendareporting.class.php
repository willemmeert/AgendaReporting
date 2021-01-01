<?php
/* Copyright (C) 2020 Willem Meert <willem.meert@mema.be>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    agendareporting/class/actions_agendareporting.class.php
 * \ingroup agendareporting
 * \brief   Hook overload.
 *
 * Add filter and document template to reporting of module Agenda
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/agendareporting/core/modules/mymodule/history_pdf.php';

/**
 * Class ActionsAgendaReporting
 */
class ActionsAgendaReporting
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();


    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;


    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }


    /**
     * Execute action
     *
     * @param	array			$parameters		Array of parameters
     * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	string			$action      	'genpdf', 'builddoc', 'remove_file'
     * @return	int         					<0 if KO,
     *                           				=0 if OK but we want to process standard actions too,
     *                            				>0 if OK and we want to replace standard actions.
     */
    public function addFormElements($parameters, &$object, &$action)
    {
        global $db,$langs,$conf,$user;
        $form = new Form($db);
        $formfile = new FormFile($db);
        $formactions=new FormActions($db);
        $disabled = ! $parameters["canedit"];
        
        $langs->load("agendareporting@agendareporting");

        /*
         * Actions: 'genpdf' to generate a PDF, 'remove_file' to delete an already generated file
         */

        if ($action == 'genpdf')
        {
            $datestart = dol_mktime(0, 0, 0, GETPOST('datestartmonth','int'), GETPOST('datestartday','int'), GETPOST('datestartyear','int'));
            $dateend = dol_mktime(23, 59, 59, GETPOST('dateendmonth','int'), GETPOST('dateendday','int'), GETPOST('dateendyear','int'));
            
        	$cat = new history_pdf(
        	    $db, 
        	    $parameters["filtert"],
        	    $parameters["usergroupid"],
        	    $datestart,
        	    $dateend,
        	    GETPOST('model'),
        	    GETPOST('lang_id'),
        	    GETPOST('datenewpage'));
        	$result = $cat->write_file();
        	if ($result <= 0)
        	{
        		setEventMessages($cat->error, $cat->errors, 'errors');
        	}
        	else
        	{
                setEventMessage($cat->result['fullpath'].' generated.');        	    
        	}
        	
        }
        
        if ($action == 'remove_file')
        {
            $cat = new history_pdf($db);
            $filename = GETPOST('file');
            $result = $cat->delete_file($filename);
            if ($result <= 0)
        	{
        		setEventMessages($cat->error, $cat->errors, 'warnings');
        	}
        	else
        	{
                setEventMessage($filename.' deleted.');        	    
        	}
        }
        
        /*
         * View
         */
        
        print '<table class="table-fiche-titre"><tr><td class="col-title">';
        print '<div class="titre inline-block">'.$langs->trans('AgendaReportingFilterTitle').'</div>';
        print '</td></tr></table>';
        
        print '<div class="tabBar tabBarWithBottom">';
        print '<table class="noborderpadding">';
        // User filter
        print '<tr><td class="nowrap" style="padding-bottom: 2px; padding-right: 4px;">';
		print $langs->trans("ActionsToDoBy").' &nbsp; ';
		print '</td><td style="padding-bottom: 2px; padding-right: 4px;">';
		print $form->select_dolusers($parameters["filtert"], 'search_filtert', 1, '', $disabled, '', '', 0, 0, 0, '', 0, '', 'maxwidth300');
		if (empty($conf->dol_optimize_smallscreen)) print ' &nbsp; '.$langs->trans("or") . ' '.$langs->trans("ToUserOfGroup").' &nbsp; ';
		print $form->select_dolgroups($parameters["usergroupid"], 'usergroup', 1, '', $disabled);
		print '</td></tr>';
		
        // Type filter
		print '<tr><td class="nowrap" style="padding-bottom: 2px; padding-right: 4px;">';
		print $langs->trans("Type");
		print ' &nbsp;</td><td class="nowrap" style="padding-bottom: 2px; padding-right: 4px;">';
		$multiselect=0;
		if (! empty($conf->global->MAIN_ENABLE_MULTISELECT_TYPE))     // We use an option here because it adds bugs when used on agenda page "peruser" and "list"
		{
            $multiselect=(!empty($conf->global->AGENDA_USE_EVENT_TYPE));
		}
        print $formactions->select_type_actions($parameters["actioncode"], "search_actioncode", '', (empty($conf->global->AGENDA_USE_EVENT_TYPE)?1:-1), 0, $multiselect);
		print '</td></tr>';
		
		// Date filter
		print '<tr><td class="nowrap" style="padding-bottom: 2px; padding-right: 4px;">';
		print $langs->trans("AgendaReportingFilterDateFrom").' &nbsp; ';
		print '</td><td style="padding-bottom: 2px; padding-right: 4px;">';
		print $form->selectDate('', 'datestart', 0, 0, 2, '', 1, 0);
		print ' &nbsp;&nbsp;&nbsp;'.$langs->trans("AgendaReportingFilterDateTo").' &nbsp; ';
		print $form->selectDate('', 'dateend', 0, 0, 2, '', 1, 0);
		print '</td></tr>';

        // New page option
		print '<tr><td class="nowrap" style="padding-bottom: 2px; padding-right: 4px;">';
		print '<input type="checkbox" class="flat checkforselect" id="datenewpage" name="datenewpage" value="1"';
		if (GETPOST('datenewpage')==1) {
		    print ' checked >';
		}
		else {
		    print '>';
		}
		print '<label for="datenewpage">&nbsp;&nbsp;'.$langs->trans("AgendaReportingFilterPage").'</label>';
		print '</td><td style="padding-bottom: 2px; padding-right: 4px;">';
		print '</td></tr>';

        // Document files
        $dirpdf=DOL_DATA_ROOT.'/agenda';      // use main dir of agenda module to reuse code from showdocuments
		$genallowed = $user->rights->agendareporting->myobject->read;
		$delallowed = $user->rights->agendareporting->myobject->delete;
		$urlsource = $_SERVER["PHP_SELF"];
		print $formfile->showdocuments('actions', '', $dirpdf, $urlsource, $genallowed, $delallowed,'',1,0,0,0,1);
		print '<input type="hidden" name="action" value="genpdf">';   // change action to our 'genpdf' so we can process correctly
		print '</table>';
        print "<p>v0.0.6</p>";
        print '</div>';
        $this->resprints = '';
        return 0;
    }


    /* Add here any other hooked methods... */
}
