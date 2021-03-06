<?php
//use PhpOffice\PhpSpreadsheet\Writer\Pdf;

/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2020      Willem Meert         <willem.meert@mema.be>
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
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/custom/agendareporting/core/modules/mymodule/history.pdf.php
 *	\ingroup    crm
 *	\brief      File to build PDF with history of events using filters from our custom modules
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

/**
 *	Class to generate history report
 */
class history_pdf
{
	/**
     * @var DoliDB $db Database handler.
     */
    public $db;

	/**
	 * @var string $description
	 */
	public $description;

	/**
	 * @var int $date_edition   Timestamp of generation date
	 */
	public $date_edition;

    /**
	 * @var int $date_start   Timestamp of starting date report
	 */
	public $date_start;

	/**
	 * @var int $date_end     Timestamp of ending date report
	 */
	public $date_end;

    /**
     * @var int $userid      Id of user of actions (-1 if usergroup or all users)
     */
	public $userid;
	
	/**
     * @var int $usergroup      Id of usergroup of actions (-1 if user or all usergroups)
     */
	public $usergroup;
	
	/**
	 * @var string $model
	 */
	public $model;
	
	/**
	 * @var int $datenewpage
	 */
	public $datenewpage;
	
	/**
	 * @var string $transprefix    Translation prefix to use
	 */
	public $transprefix;
	
	/**
	 * @var Translate $doclanguage
	 */
	public $doclanguage;
	
	/**
	 * @var string $type
	 */
	public $type;
	
	/**
	 * @var array $page_format
	 *            contains width, length and unit
	 */
	public $page_format;
	
	/**
	 * @var array $page_margin
	 *            contains left, right, top and bottom margin
	 */
	public $page_margin;

	/**
	 * @var    string $error
	 */
	public $error;
	
	/**
	 * @var    array $errors
	 */
	public $errors;
	
	/**
	 * @var TCPDF $pdf
	 */
	public $pdf;
	
	/**
	 * Constructor
	 *
	 * @param 	DoliDB	$db         Database handler
	 * @param	int		$user       filter on user
	 * @param	int		$group      filter on UserGroup
	 * @param   int     $datestart  filter: start from this date
	 * @param   int     $dateend    filter: up to this date
	 * @param   string  $model      name of PDF model to use
	 * @param   string  $langid     language to use
	 */
	public function __construct($db, $user=-1, $group=-1, $datestart=0, $dateend=0, $model='history', $langid='', $datenewpage=0)
	{
	    global $conf, $langs;
	    
		$this->db = $db;
		$this->description = "Agenda Reporting - {$model}";
		$this->date_edition = time();
		$this->userid = $user;
		$this->usergroup = $group;
		$this->date_start = $datestart;
		$this->date_end = $dateend;
		$this->model = $model;
		if (empty($langid)) {
		    $this->doclanguage = $langs;
		}
		else 
		{
		    $this->doclanguage = new Translate('', $conf);
		    $this->doclanguage->setDefaultLang($langid);
		}		
		$this->datenewpage = $datenewpage;
		$this->transprefix = "AgendaReporting_".$model."_";

		// Page size for A4 format
		$this->type = 'pdf';
		$this->page_format = pdf_getFormat();
		$this->page_margin = array ( "left" => 15, "right" => 10, "top" => 10, "bottom" => 30 );
	}

	/**
     *      Get the directory where files are located
     *
     *      @return string      path
     */
	public function getDataDirectory()
	{
	    return DOL_DATA_ROOT.'/agenda/';
	}

	/**
     *      Delete a file
     *
     *      @param string      $filename
     *      @return int        1=OK, 0=KO
     */
	public function delete_file($filename)
	{
	    global $langs;
	    
	    if (!unlink($this->getDataDirectory().$filename)) {
	        $this->error = $langs->trans("ErrorFailToDeleteFile", $filename);
			return 0;
	    }
	    return 1;
	}
	
    /**
     *      Write the object to document file to disk
     *
     *      @return int             			1=OK, 0=KO
     */
	public function write_file()
	{
		global $user, $conf, $langs, $hookmanager;
		
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
//		if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';

		// Load traductions files required by page
		$this->doclanguage->loadLangs(array("main", "dict", "companies", "agendareporting@agendareporting"));

        $dir = $this->getDataDirectory();
        $date = new DateTime();
        $date->setTimestamp($this->date_start);
		$file = $dir.$this->model.$this->userid."-".$date->format("Ymd").".pdf";

		if (!file_exists($dir))
		{
			if (dol_mkdir($dir) < 0)
			{
				$this->error = $langs->trans("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}

		if (file_exists($dir))
		{
			// Add pdfgeneration hook
			if (!is_object($hookmanager))
			{
				include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
				$hookmanager = new HookManager($this->db);
			}
			$hookmanager->initHooks(array('pdfgeneration'));

			global $action;
			$object = new stdClass();

			$parameters = array('file'=>$file, 'outputlangs'=>$this->doclanguage);
			$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

            $this->pdf = pdf_getInstance($this->page_format);
            $this->pdf->SetAutoPageBreak(false);                  // we will do our own header/footer, so do not allow automatic page breaks

            if (class_exists('TCPDF'))
            {
                $this->pdf->setPrintHeader(false);
                $this->pdf->setPrintFooter(false);
            }
            
/*            // Add fonts for our report
            $this->pdf->AddFont('dejavusans','','dejavusans.php');
            $this->pdf->AddFont('dejavusansb','', 'dejavusansb.php');
            $this->pdf->AddFont('dejavuserif','','dejavuserif.php');
            $this->pdf->AddFont('dejavuserifb','', 'dejavuserifb.php');
*/            
            $this->pdf->SetFont('dejavusans','',11);

//			$this->pdf->Open();
			$this->pdf->SetDrawColor(128, 128, 128);
			$this->pdf->SetFillColor(220, 220, 220);

			$this->pdf->SetTitle($langs->convToOutputCharset($this->doclanguage->trans($this->transprefix.'Title')));
//			$this->pdf->SetSubject($outputlangs->convToOutputCharset($this->subject));
			$this->pdf->SetCreator("Dolibarr ".DOL_VERSION);
			$this->pdf->SetAuthor($langs->convToOutputCharset($user->getFullName($langs)));
//			$this->pdf->SetKeywords($outputlangs->convToOutputCharset($this->title." ".$this->subject));

			$this->pdf->SetMargins($this->page_margin["left"], $this->page_margin["top"], $this->page_margin["right"]); // Left, Top, Right

			$this->_pages(); // Write content

//			if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPages();

			$this->pdf->Close();
			$this->pdf->Output($file, 'F');

			// Add pdfgeneration hook
			if (!is_object($hookmanager))
			{
				include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
				$hookmanager = new HookManager($this->db);
			}
			$hookmanager->initHooks(array('pdfgeneration'));
			$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$this->doclanguage);
			global $action;
			$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
			if ($reshook < 0)
			{
			    $this->error = $hookmanager->error;
			    $this->errors = $hookmanager->errors;
			}

			if (!empty($conf->global->MAIN_UMASK))
			@chmod($file, octdec($conf->global->MAIN_UMASK));

			$this->result = array('fullpath'=>$file);

			return 1;
		}
	}

	/**
	 * Write content of pages
	 *
	 * @return  int							1
	 */
	private function _pages()
	{
	    $height = 3;   // for seperation
	    
		$sql = "SELECT soc.nom as nom, soc.address as address, soc.zip as zip, soc.town as town, soc.email as socemail, soc.phone as socphone,";
		$sql .= " ac.id, ac.datep as dp, ac.code as code, ac.note as note, ac.fk_element as fk_element, ac.elementtype as elementtype,";
		$sql .= " socp.lastname as lastname, socp.firstname as firstname, socp.phone_mobile as phone_mobile, socp.email as emailp,";
		$sql .= " u.firstname as ufirstname, u.lastname as ulastname, ace.actionregarding as regarding, ace.actioncategory as category";
		$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm as ac";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON ac.fk_user_action = u.rowid";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON ac.fk_soc = soc.rowid";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as socp ON ac.fk_contact = socp.rowid";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_extrafields as ace ON ac.id = ace.fk_object";
        
        if ($this->userid<>-1) {    // report only for a certain user
            $sql .= " WHERE (ac.fk_user_author = ".$this->userid." OR ac.fk_user_action = ".$this->userid.") AND";
        }
        else {
            $sql .= " WHERE";
        }
		$sql .= " ac.datep BETWEEN '".$this->db->idate($this->date_start)."'";
		$sql .= " AND '".$this->db->idate($this->date_end)."'";
		$sql .= " ORDER BY ac.datep";

		$eventstatic = new ActionComm($this->db);
//		$projectstatic = new Project($this->db);

		dol_syslog(get_class($this)."::_page", LOG_DEBUG);
//		$this->$pdf->SetSubject($sql);
		$resql = $this->db->query($sql);
		if ($resql)
		{
    		$pagenb = 0;                                          // page numbers
    		$prevdate = 0;                                        // for page breaks when date changes
    		$hname = '';                                          // name to display on header
    		$y = $this->page_margin["top"];                       // current page y
    		$datetimeprinted = dol_print_date(time(), "dayhour");
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($i < $num)
			{
				$obj = $this->db->fetch_object($resql);

				$eventstatic->id = $obj->id;
				$eventstatic->percentage = $obj->percent;
				$eventstatic->fulldayevent = $obj->fulldayevent;

    			// if this is first record, initialize some general data
				if ($i==0) {
				    if ($this->userid<>-1) {
				        $hname = $obj->ufirstname." ".$obj->ulastname;
				    }
				}
				
				// see if page break/header is needed before outputting current record
				if (($pagenb <= 0) ||
				    ( $y > ($this->pdf->getPageHeight()-$this->page_margin["bottom"])) ||
				    ( ($this->datenewpage!=0) && (dol_print_date($this->db->jdate($obj->dp), "day") != dol_print_date($this->db->jdate($prevdate), "day")))
				    ) {
				        if($pagenb>0) {   // do a page footer first, then a new page
				            $this->_pagefoot($pagenb, $datetimeprinted);
				        }
				        // always output header on a new page
				        $prevdate = $obj->dp;
				        $y = $this->_pagehead(++$pagenb, $prevdate, $hname);
			    }
			    			    
    		    $this->pdf->SetFont('dejavuserif', '', 10);  // base font to use in document

    		    // Third party data
    		    $this->pdf->SetLineWidth(0.2);    		    
    		    $this->pdf->SetXY($this->page_margin["left"]+1, $y);
    		    $this->pdf->SetFont('dejavuserifb');
    		    $this->pdf->Cell(80,0,$obj->nom);
    		    $this->pdf->SetFont('dejavuserif');
    		    $this->pdf->Cell(5,0,'');
    		    $this->pdf->Cell(60,0,$obj->zip." ".$obj->town);
    		    $this->pdf->Cell(0,0,$this->doclanguage->trans($this->transprefix."Tel").": ".$obj->socphone,0,1,'R');
    		    $this->pdf->SetX($this->page_margin["left"]+1);
    		    $this->pdf->Cell(120,0,$obj->address);
    		    $this->pdf->Cell(0,0,$obj->socemail,0,1,'R',false,"mailto:".$obj->socemail);
    		    $this->pdf->Rect($this->page_margin["left"],
    		               $y-0.2,
    		               $this->pdf->getPageWidth()-$this->page_margin["left"]-$this->page_margin["right"],
    		               $this->pdf->GetY()-$y+0.5);
    		    
    		    $y = $this->pdf->GetY() + $height*0.5;
    		        
				// LEFT first row: Date + user
				$this->pdf->SetXY($this->page_margin["left"]+1, $y);
				$textdate = dol_print_date($this->db->jdate($obj->dp), "day")." ".dol_print_date($this->db->jdate($obj->dp), "hour");
				$this->pdf->Cell(70, 0, $textdate." - ".$obj->ufirstname." ".$obj->ulastname);
				// RIGHT first row: contact name
				$this->pdf->Cell(0, 0, $obj->firstname." ".$obj->lastname, 0, 1, 'R');				
				
				// LEFT second row: 'type' of action (which is 'code' database field)
				$this->pdf->SetX($this->page_margin["left"]+1);
				$this->pdf->SetFont('', 'B');
				$this->pdf->Cell(26, 0, $this->doclanguage->trans($this->transprefix."Type").":");
				$this->pdf->SetFont('', '');
				$this->pdf->Cell(60, 0, $this->doclanguage->transnoentitiesnoconv("Action".$obj->code));
				// RIGHT second row: contact mobile
				$this->pdf->Cell(0, 0, $obj->phone_mobile, 0, 1, 'R');
				$y = $this->pdf->GetY();
				
				// check if end-of-page reached
				if ($y > ($this->pdf->getPageHeight()-$this->page_margin["bottom"])) {
				    $this->_pagefoot($pagenb, $datetimeprinted);
				    $prevdate = $obj->dp;
				    $y = $this->_pagehead(++$pagenb, $prevdate, $hname);
    		        $this->pdf->SetFont('dejavuserif', '', 10);  // base font to use in document
				}
				
				// LEFT third row: 'regarding'
				$this->pdf->SetXY($this->page_margin["left"]+1, $y);
				$this->pdf->SetFont('', 'B');
				$this->pdf->Cell(26, 0, $this->doclanguage->trans($this->transprefix."Regarding").":");
				$this->pdf->SetFont('', '');
				$this->pdf->Cell(60, 0, $this->doclanguage->transnoentitiesnoconv("ActionRegarding".$obj->regarding));
				// RIGHT third row: contact e-mail
				$this->pdf->Cell(0, 0, $obj->emailp, 0, 1, 'R');
				$y = $this->pdf->GetY();
				
				// LEFT fourth row: 'category'
				$this->pdf->SetXY($this->page_margin["left"]+1, $y);
				$this->pdf->SetFont('', 'B');
				$this->pdf->Cell(26, 0, $this->doclanguage->trans($this->transprefix."Category").":");
				$this->pdf->SetFont('', '');
				$this->pdf->Cell(60, 0, $this->doclanguage->transnoentitiesnoconv("ActionCategory".$obj->category), 0, 1);
				$y = $this->pdf->GetY();

				// check if end-of-page reached before printing note text
				if ($y > ($this->pdf->getPageHeight()-$this->page_margin["bottom"])) {
				    $this->_pagefoot($pagenb, $datetimeprinted);
				    $prevdate = $obj->dp;
				    $y = $this->_pagehead(++$pagenb, $prevdate, $hname);
    		        $this->pdf->SetFont('dejavuserif', '', 10);  // base font to use in document
				}
				
				// see if paging is required for note text
				$txt_total = dol_string_onlythesehtmltags($obj->note);                                      // strip unwanted html
				$w=$this->pdf->getPageWidth()-$this->page_margin["right"]-$this->page_margin["left"]-4;     // width of our cell
				$lines_total = dol_nboflines_bis($txt_total);
				
				$lines_out = $lines_total;
				$txt_out = $txt_total;
				do {
				    $savePage = $this->pdf->getPage();                                                      // save page number
				    $this->pdf->startTransaction();                                                         // try to output, so wrap in Transaction
	                $this->pdf->SetAutoPageBreak(true, $this->page_margin["bottom"]);
	                $this->pdf->writeHTMLCell($w, 10, $this->page_margin["left"]+2, $y, $txt_out, 0, 1);
	                if($savePage != $this->pdf->getPage()) {       // everything does not fit on the page
	                    $this->pdf->rollbackTransaction(true);     // roll back
	                    $lines_out--;                              // try with one line less
	                    if ($lines_out<1) {                        // not even 1 line fits, new page first then
        				    $this->_pagefoot($pagenb, $datetimeprinted);
		          		    $prevdate = $obj->dp;
				            $y = $this->_pagehead(++$pagenb, $prevdate, $hname);
    		                $this->pdf->SetFont('dejavuserif', '', 10);  // base font to use in document
    		                $lines_out = $lines_total;    // restart with complete text to output
    		                $txt_out = $txt_total;
	                    }
	                    else {
	                        $txt_out = dolGetFirstLineOfText($txt_total, $lines_out);
	                    }	                        
	                }
	                else {  // current output text does fit on page
	                    $this->pdf->commitTransaction();
	                    $len_out = strlen($txt_out);
	                    $len_total = strlen($txt_total);
	                    if ( $len_out == $len_total ) { // all text was output
	                        $txt_out = '';
	                    }
	                    else {  // still some text to output on next page
        				    $this->_pagefoot($pagenb, $datetimeprinted);
		          		    $prevdate = $obj->dp;
				            $y = $this->_pagehead(++$pagenb, $prevdate, $hname);
    		                $this->pdf->SetFont('dejavuserif', '', 10);             // base font to use in document

    		                $txt_total = substr($txt_total, $len_out-3);            // remove already outputted text

				            $lines_total = dol_nboflines_bis($txt_total);           // reset counters & strings
							$lines_out = $lines_total;
				            $txt_out = $txt_total;
	                    }
	                }
				} while (strlen($txt_out)>0);

				$this->pdf->SetAutoPageBreak(false);
				$y = $this->pdf->GetY() + $height;                                  // get current pointer and leave space for next record			
				$i++;
			}
			 $this->_pagefoot($pagenb, $datetimeprinted);
		}

		return 1;
	}

	/**
	 *  Show header of page: title, date and account manager
	 *
	 * 	@param	int			$pagenb			Page number
	 *  @param  int         $hdate          date to put in header          
	 *  @return	integer
	 */
	private function _pagehead($pagenb, $hdate, $hname)
	{
		$height = 3;   // for seperation
		
		$this->pdf->AddPage('','',($pagenb>1));
	    
		// Show title
		$this->pdf->SetFont('dejavusansb', '', 14);
		$y = $this->page_margin["top"];
		$this->pdf->SetXY($this->page_margin["left"], $y);
		$this->pdf->Cell(40, 0, $this->doclanguage->convToOutputCharset($this->doclanguage->trans($this->transprefix."Title")),0,0,'L',false,'',0,false,'L','T');
		$this->pdf->SetXY($this->page_margin["left"]+45, $y);
        $this->pdf->Cell(60, 0, dol_print_date($this->db->jdate($hdate), "daytextshort"),0,0,'L',false,'',0,false,'L','T');
        $this->pdf->SetXY(-60-$this->page_margin["right"], $y);
        $this->pdf->SetFontSize(12);
        $this->pdf->Cell(60, 0, $hname, 0, 0, 'R', false, '',0,false,'L','T');
        
        // horizontale line divider
        $this->pdf->Ln(1.5);
		$this->pdf->SetDrawColor(0, 0, 0);
		$y = $this->pdf->GetY();
		$this->pdf->SetLineWidth(0.8);
        $this->pdf->Line($this->pdf->GetX(), $y, $this->pdf->getPageWidth() - $this->page_margin["right"], $y);
        return $y+$height;
	}
	
    /**
	 *  Show footer of page: printed on datetime, pagenumbers
	 *
	 * 	@param	int			$pagenb			Page number
	 *  @param  int         $datep          Date to print in footer
	 *  @return	integer
	 */
	private function _pagefoot($pagenb, $datep)
	{
	    $this->pdf->SetDrawColor(0, 0, 0);
	    $this->pdf->SetLineWidth(0.4);
        $yline = $this->pdf->getPageHeight()-8;	    
	    $this->pdf->Line($this->page_margin["left"], $yline, $this->pdf->getPageWidth()-$this->page_margin["right"], $yline);
	    $this->pdf->SetXY($this->page_margin["left"], $yline+0.1);
	    $this->pdf->SetFont('dejavusans','',8);
	    $this->pdf->Cell(70,0, $this->doclanguage->convToOutputCharset($this->doclanguage->trans($this->transprefix."Made"))." ".$datep);
	    $this->pdf->SetX(-40);
        $this->pdf->Cell(40, 0, $this->doclanguage->convToOutputCharset($this->doclanguage->trans($this->transprefix."Page"))." ".$pagenb.'/ '.$this->pdf->getAliasNbPages(), 0, 'R', 0);
  	}	
}
