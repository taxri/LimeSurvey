<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

/**
* survey
*
* @package LimeSurvey
* @author  The LimeSurvey Project team
* @copyright 2011
* @version $Id: surveyaction.php 12301 2012-02-02 08:51:43Z c_schmitz $
* @access public
*/
class SurveyAdmin extends Survey_Common_Action
{
    /**
    * Initiates the survey action, checks for superadmin permission
    *
    * @access public
    * @param CController $controller
    * @param string $id
    */
    public function __construct($controller, $id)
    {
        parent::__construct($controller, $id);
    }

    /**
     * Loads list of surveys and its few quick properties.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->getController()->redirect(array('admin/survey/sa/listsurveys'));
    }

    /**
     * Delete multiple survey
     */
    public function deleteMultiple()
    {
        $aSurveys = json_decode(Yii::app()->request->getPost('sItems'));
        $aResults = array();
        foreach($aSurveys as $iSurveyID)
        {
            $oSurvey                        = Survey::model()->findByPk($iSurveyID);
            $aResults[$iSurveyID]['title']  = $oSurvey->correct_relation_defaultlanguage->surveyls_title;
            $aResults[$iSurveyID]['result'] = $oSurvey->deleteSurvey($iSurveyID, $recursive=true);
        }
        Yii::app()->getController()->renderPartial('/admin/survey/massive_actions/_delete_results', array('aResults'=>$aResults));
    }

    public function listsurveys()
    {
        Yii::app()->loadHelper('surveytranslator');
        $aData = array();
        $aData['issuperadmin'] = false;

        if (Permission::model()->hasGlobalPermission('superadmin','read'))
        {
            $aData['issuperadmin'] = true;
        }

        $aData['model'] = $model =  new Survey('search');

        // Search
        if (isset($_GET['Survey']['searched_value']))
        {
            $model->searched_value = $_GET['Survey']['searched_value'];
        }

        $model->active = null;

        // Filter state
        if (isset($_GET['active']) && !empty($_GET['active']))
        {
            $model->active = $_GET['active'];
        }

        // Set number of page
        if (isset($_GET['pageSize']))
        {
            Yii::app()->user->setState('pageSize',(int)$_GET['pageSize']);
        }

        $aData['fullpagebar']['button']['newsurvey'] = true;
        $aData['massiveAction'] = App()->getController()->renderPartial('/admin/survey/massive_actions/_selector', array(), true, false);
        $this->_renderWrappedTemplate('survey', 'listSurveys_view', $aData);
    }


    public function regenquestioncodes($iSurveyID, $sSubAction )
    {
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'update'))
        {
            Yii::app()->setFlashMessage(gT("You do not have permission to access this page."),'error');
            $this->getController()->redirect(array('admin/survey','sa'=>'view','surveyid'=>$iSurveyID));
        }
        $oSurvey=Survey::model()->findByPk($iSurveyID);
        if ($oSurvey->active=='Y')
        {
            Yii::app()->setFlashMessage(gT("You can't update question code for an active survey."),'error');
            $this->getController()->redirect(array('admin/survey','sa'=>'view','surveyid'=>$iSurveyID));
        }
        //Automatically renumbers the "question codes" so that they follow
        //a methodical numbering method
        $iQuestionNumber = 1;
        $iGroupNumber    = 0;
        $iGroupSequence  = 0;
        $oQuestions      = Question::model()->with('groups')->findAll(array('select'=>'t.qid,t.gid','condition'=>"t.sid=:sid and t.language=:language and parent_qid=0",'order'=>'groups.group_order, question_order','params'=>array(':sid'=>$iSurveyID,':language'=>$oSurvey->language)));

        foreach ($oQuestions as $oQuestion)
        {
            if ($sSubAction == 'bygroup' && $iGroupNumber != $oQuestion->gid)
            { //If we're doing this by group, restart the numbering when the group number changes
                $iQuestionNumber =1;
                $iGroupNumber    = $oQuestion->gid;
                $iGroupSequence++;
            }
            $sNewTitle=(($sSubAction == 'bygroup') ? ('G' . $iGroupSequence ) : '')."Q".str_pad($iQuestionNumber, 5, "0", STR_PAD_LEFT);
            Question::model()->updateAll(array('title'=>$sNewTitle),'qid=:qid',array(':qid'=>$oQuestion->qid));
            $iQuestionNumber++;
            $iGroupNumber = $oQuestion->gid;
        }
        Yii::app()->setFlashMessage(gT("Question codes were successfully regenerated."));
        LimeExpressionManager::SetDirtyFlag(); // so refreshes syntax highlighting
        $this->getController()->redirect(array('admin/survey/sa/view/surveyid/' . $iSurveyID));
    }


    /**
    * This function prepares the view for a new survey
    *
    */
    public function newsurvey()
    {
        App()->getClientScript()->registerPackage('jqgrid');
        if (!Permission::model()->hasGlobalPermission('surveys','create'))
        {
            Yii::app()->user->setFlash('error', gT("Access denied"));
            $this->getController()->redirect(Yii::app()->request->urlReferrer);
        }

        $this->_registerScriptFiles();
        Yii::app()->loadHelper('surveytranslator');
        $esrow = $this->_fetchSurveyInfo('newsurvey');
        // Default setting is to use the global Google Analytics key If one exists
        $globalKey = getGlobalSetting('googleanalyticsapikey');
        if($globalKey != ""){
            $esrow['googleanalyticsapikey'] = "9999useGlobal9999";
            $esrow['googleanalyticsapikeysetting'] = "G";
        }

        Yii::app()->loadHelper('admin/htmleditor');

        $aViewUrls['output']  = PrepareEditorScript(false, $this->getController());
        $aData                = $this->_generalTabNewSurvey();
        $aData['esrow']       = $esrow;
        $aData                = array_merge($aData, $this->_tabPresentationNavigation($esrow));
        $aData                = array_merge($aData, $this->_tabPublicationAccess($esrow));
        $aData                = array_merge($aData, $this->_tabNotificationDataManagement($esrow));
        $aData                = array_merge($aData, $this->_tabTokens($esrow));

        $aViewUrls[]          = 'newSurvey_view';

        $arrayed_data                                              = array();
        $arrayed_data['data']                                      = $aData;
        $arrayed_data['title_bar']['title']                        = gT('New survey');
        $arrayed_data['fullpagebar']['savebutton']['form']         = 'addnewsurvey';
        $arrayed_data['fullpagebar']['saveandclosebutton']['form'] = 'addnewsurvey';
        $arrayed_data['fullpagebar']['closebutton']['url']         = 'admin/index';  // Close button

        $this->_renderWrappedTemplate('survey', $aViewUrls, $arrayed_data);
    }

    public function fakebrowser()
    {
        Yii::app()->getController()->renderPartial('/admin/survey/newSurveyBrowserMessage', array());
    }

    /**
    * This function prepares the view for editing a survey
    *
    */
    public function editsurveysettings($iSurveyID)
    {
        $iSurveyID = (int) $iSurveyID;
        if (is_null($iSurveyID) || !$iSurveyID)
            $this->getController()->error('Invalid survey ID');

        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'read') && !Permission::model()->hasGlobalPermission('surveys','read'))
        {
            Yii::app()->user->setFlash('error', gT("Access denied"));
            $this->getController()->redirect(Yii::app()->request->urlReferrer);
        }

        if(Yii::app()->request->isPostRequest)
            $this->update($iSurveyID);
        $this->_registerScriptFiles();

        //Yii::app()->loadHelper('text');
        Yii::app()->loadHelper('surveytranslator');

        Yii::app()->session['FileManagerContext'] = "edit:survey:{$iSurveyID}";

        Yii::app()->loadHelper('/admin/htmleditor');
        initKcfinder();

        $esrow = self::_fetchSurveyInfo('editsurvey', $iSurveyID);

        $aData          = array();
        $aData['esrow'] = $esrow;
        $aData          = array_merge($aData, $this->_generalTabEditSurvey($iSurveyID, $esrow));
        $aData          = array_merge($aData, $this->_tabPresentationNavigation($esrow));
        $aData          = array_merge($aData, $this->_tabPublicationAccess($esrow));
        $aData          = array_merge($aData, $this->_tabNotificationDataManagement($esrow));
        $aData          = array_merge($aData, $this->_tabTokens($esrow));
        $aData          = array_merge($aData, $this->_tabPanelIntegration($esrow));
        $aData          = array_merge($aData, $this->_tabResourceManagement($iSurveyID));

        $oResult = Question::model()->getQuestionsWithSubQuestions($iSurveyID, $esrow['language'], "({{questions}}.type = 'T'  OR  {{questions}}.type = 'Q'  OR  {{questions}}.type = 'T' OR {{questions}}.type = 'S')");

        $aData['questions']                             = $oResult;
        $aData['display']['menu_bars']['surveysummary'] = "editsurveysettings";
        $tempData                                       = $aData;
        $aData['data']                                  = $tempData;


        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";
        $aData['sidemenu']['state'] = false;
        $aData['surveybar']['savebutton']['form'] = 'frmeditgroup';
        $aData['surveybar']['closebutton']['url'] = 'admin/survey/sa/view/surveyid/'.$iSurveyID;  // Close button

    }

    /**
    * Function responsible to import survey resources from a '.zip' file.
    *
    * @access public
    * @return void
    */
    public function importsurveyresources()
    {
        $iSurveyID = Yii::app()->request->getPost('surveyid');

        if (!empty($iSurveyID))
        {


            if (Yii::app()->getConfig('demoMode'))
            {
                Yii::app()->user->setFlash('error',gT("Demo mode only: Uploading files is disabled in this system."));
                $this->getController()->redirect(array('admin/survey/sa/editlocalsettings/surveyid/' . $iSurveyID));
            }

            // Create temporary directory
            // If dangerous content is unzipped
            // then no one will know the path
            $extractdir  = $this->_tempdir(Yii::app()->getConfig('tempdir'));
            $zipfilename = $_FILES['the_file']['tmp_name'];
            $basedestdir = Yii::app()->getConfig('uploaddir') . "/surveys";
            $destdir     = $basedestdir . "/$iSurveyID/";

            Yii::app()->loadLibrary('admin.pclzip');
            $zip = new PclZip($zipfilename);

            if (!is_writeable($basedestdir))
            {
                Yii::app()->user->setFlash('error',sprintf(gT("Incorrect permissions in your %s folder."), $basedestdir));
                $this->getController()->redirect(array('admin/survey/sa/editlocalsettings/surveyid/' . $iSurveyID));
            }


            if (!is_dir($destdir))
                mkdir($destdir);

            $aImportedFilesInfo = array();
            $aErrorFilesInfo = array();

            if (is_file($zipfilename))
            {
                if ($zip->extract($extractdir) <= 0)
                {
                    Yii::app()->user->setFlash('error',gT("This file is not a valid ZIP file archive. Import failed. "). $zip->errorInfo(true) );
                    $this->getController()->redirect(array('admin/survey/sa/editlocalsettings/surveyid/' . $iSurveyID));
                }
                // now read tempdir and copy authorized files only
                $folders = array('flash', 'files', 'images');
                foreach ($folders as $folder)
                {
                    list($_aImportedFilesInfo, $_aErrorFilesInfo) = $this->_filterImportedResources($extractdir . "/" . $folder, $destdir . $folder);
                    $aImportedFilesInfo = array_merge($aImportedFilesInfo, $_aImportedFilesInfo);
                    $aErrorFilesInfo = array_merge($aErrorFilesInfo, $_aErrorFilesInfo);
                }

                // Deletes the temp directory
                rmdirr($extractdir);

                // Delete the temporary file
                unlink($zipfilename);

                if (is_null($aErrorFilesInfo) && is_null($aImportedFilesInfo))
                {
                    Yii::app()->user->setFlash('error',gT("This ZIP archive contains no valid Resources files. Import failed."));
                    $this->getController()->redirect(array('admin/survey/sa/editlocalsettings/surveyid/' . $iSurveyID));
                }
            }
            else
            {
                Yii::app()->setFlashMessage(gT("An error occurred uploading your file. This may be caused by incorrect permissions for the application /tmp folder."),'error');
                $this->getController()->redirect(array('admin/survey/sa/editlocalsettings/surveyid/' . $iSurveyID));
            }
            $aData = array(
            'aErrorFilesInfo' => $aErrorFilesInfo,
            'aImportedFilesInfo' => $aImportedFilesInfo,
            'surveyid' => $iSurveyID
            );
            $aData['display']['menu_bars']['surveysummary'] = true;
            $this->_renderWrappedTemplate('survey', 'importSurveyResources_view', $aData);
        }
    }

    public function changetemplate($iSurveyID, $template)
    {
        $iSurveyID  = sanitize_int($iSurveyID);
        $sTemplate  = sanitize_paranoid_string($template);

        $survey           = Survey::model()->findByPk($iSurveyID);
        $survey->template = $sTemplate;
        $survey->save();
    }

    public function togglequickaction()
    {
        $setting_entry = 'quickaction_'.Yii::app()->user->getId();
        $quickactionstate = getGlobalSetting($setting_entry);

        switch ($quickactionstate)
        {
            // if the quickaction state is not set, then it's the first time user click on the chevron, then the state must be set to 0
            case null:
                setGlobalSetting($setting_entry, 0);
                break;

            case 0:
                setGlobalSetting($setting_entry, 1);
                break;

            case 1:
                setGlobalSetting($setting_entry, 0);
                break;
        }
    }

    /**
    * Load complete view of survey properties and actions specified by $iSurveyID
    *
    * @access public
    * @param mixed $iSurveyID
    * @param mixed $gid
    * @param mixed $qid
    * @return void
    */
    public function view($iSurveyID, $gid = null, $qid = null)
    {
        $beforeSurveyAdminView = new PluginEvent('beforeSurveyAdminView');
        $beforeSurveyAdminView->set('surveyId', $iSurveyID);
        App()->getPluginManager()->dispatchEvent($beforeSurveyAdminView);

        // We load the panel packages for quick actions
        $iSurveyID = sanitize_int($iSurveyID);
        $survey    = Survey::model()->findByPk($iSurveyID);
        $baselang  = $survey->language;

        $aData = array('aAdditionalLanguages' => $survey->additionalLanguages);

        // Reinit LEMlang and LEMsid: ensure LEMlang are set to default lang, surveyid are set to this survey id
        // Ensure Last GetLastPrettyPrintExpression get info from this sid and default lang
        LimeExpressionManager::SetEMLanguage($baselang);
        LimeExpressionManager::SetSurveyId($iSurveyID);
        LimeExpressionManager::StartProcessingPage(false,true);

        $surveyinfo = $survey->surveyinfo;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";
        $aData["surveyinfo"] = $surveyinfo;
        $aData['surveyid'] = $iSurveyID;
        $aData['display']['surveysummary'] = true;

        // Last survey visited
        $setting_entry = 'last_survey_'.Yii::app()->user->getId();
        setGlobalSetting($setting_entry, $iSurveyID);

        $aData['surveybar']['buttons']['view']= true;
        $aData['surveybar']['returnbutton']['url'] = $this->getController()->createUrl("admin/survey/sa/listsurveys");
        $aData['surveybar']['returnbutton']['text'] = gT('Return to survey list');
        $aData['sidemenu']["survey_menu"]=TRUE;

        // We get the last question visited by user for this survey
        $setting_entry = 'last_question_'.Yii::app()->user->getId().'_'.$iSurveyID;
        $lastquestion = getGlobalSetting($setting_entry);
        $setting_entry = 'last_question_'.Yii::app()->user->getId().'_'.$iSurveyID.'_gid';

        // We get the list of templates


        //$setting_entry = 'last_question_gid'.Yii::app()->user->getId().'_'.$iSurveyID;
        $lastquestiongroup = getGlobalSetting($setting_entry);

        if( $lastquestion != null && $lastquestiongroup != null)
        {
            $aData['showLastQuestion'] = true;
            $iQid = $lastquestion;
            $iGid = $lastquestiongroup;
            $qrrow = Question::model()->findByAttributes(array('qid' => $iQid, 'gid' => $iGid, 'sid' => $iSurveyID, 'language' => $baselang));

            $aData['last_question_name'] = $qrrow['title'];
            if($qrrow['question'])
                $aData['last_question_name'] .= ' : '.$qrrow['question'];

            $aData['last_question_link'] = $this->getController()->createUrl("admin/questions/sa/view/surveyid/$iSurveyID/gid/$iGid/qid/$iQid");
        }
        else
        {
           $aData['showLastQuestion'] = false;
        }

        $this->_renderWrappedTemplate('survey', array(), $aData);
    }



    /**
    * Load list question groups view for a specified by $iSurveyID
    *
    * @access public
    * @param mixed $surveyid The survey ID
    * @return void
    */
    public function listquestiongroups($surveyid)
    {
        $iSurveyID = sanitize_int($surveyid);
        // Reinit LEMlang and LEMsid: ensure LEMlang are set to default lang, surveyid are set to this survey id
        // Ensure Last GetLastPrettyPrintExpression get info from this sid and default lang
        LimeExpressionManager::SetEMLanguage(Survey::model()->findByPk($iSurveyID)->language);
        LimeExpressionManager::SetSurveyId($iSurveyID);
        LimeExpressionManager::StartProcessingPage(false,true);

        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $aData      = array();

        $aData['surveyid']                                   = $iSurveyID;
        $aData['display']['menu_bars']['listquestiongroups'] = true;
        $aData['sidemenu']['questiongroups']                 = true;
        $aData['sidemenu']['listquestiongroups']             = true;
        $aData['surveybar']['buttons']['newgroup']           = true;
        $aData["surveyinfo"]                                 = $surveyinfo;
        $aData['title_bar']['title']                         = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";

        $baselang = Survey::model()->findByPk($iSurveyID)->language;
        $model    = new QuestionGroup('search');

        if (isset($_GET['QuestionGroup']))
        {
            $model->attributes = $_GET['QuestionGroup'];
        }

        if (isset($_GET['pageSize']))
        {
            Yii::app()->user->setState('pageSize',(int)$_GET['pageSize']);
        }

        $model['sid']      = $iSurveyID;
        $model['language'] = $baselang;
        $aData['model']    = $model;

        $this->_renderWrappedTemplate('survey', array(), $aData);
    }


    /**
    * Load list questions view for a specified survey by $surveyid
    *
    * @access public
    * @param mixed $surveyid
    * @return html
    */
    public function listquestions($surveyid)
    {
        $iSurveyID = sanitize_int($surveyid);
        // Reinit LEMlang and LEMsid: ensure LEMlang are set to default lang, surveyid are set to this survey id
        // Ensure Last GetLastPrettyPrintExpression get info from this sid and default lang
        LimeExpressionManager::SetEMLanguage(Survey::model()->findByPk($iSurveyID)->language);
        LimeExpressionManager::SetSurveyId($iSurveyID);
        LimeExpressionManager::StartProcessingPage(false,true);

        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $aData   = array();

        $aData['oSurvey']                               = $oSurvey;
        $aData['surveyid']                              = $iSurveyID;
        $aData['display']['menu_bars']['listquestions'] = true;
        $aData['sidemenu']['listquestions']             = true;
        $aData['surveybar']['returnbutton']['url']      = $this->getController()->createUrl("admin/survey/sa/listsurveys");
        $aData['surveybar']['returnbutton']['text']     = gT('Return to survey list');
        $aData['surveybar']['buttons']['newquestion']   = true;

        $surveyinfo = $oSurvey->surveyinfo;
        $aData["surveyinfo"]         = $surveyinfo;
        $aData["surveyHasGroup"]     = $oSurvey->groups;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";

        $this->_renderWrappedTemplate('survey', array(), $aData);
    }


    /**
    * Function responsible to deactivate a survey.
    *
    * @access public
    * @param int $iSurveyID
    * @return void
    */
    public function deactivate($iSurveyID = null)
    {
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveyactivation', 'update')) die('No permission');

        $iSurveyID  = Yii::app()->request->getPost('sid', $iSurveyID);
        $iSurveyID  = sanitize_int($iSurveyID);
        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $date       = date('YmdHis'); //'His' adds 24hours+minutes to name to allow multiple deactiviations in a day
        $aData      = array();

        $aData['aSurveysettings']                 = getSurveyInfo($iSurveyID);
        $aData['surveyid']                        = $iSurveyID;
        $aData['title_bar']['title']              = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";
        $aData['surveybar']['closebutton']['url'] = 'admin/survey/sa/view/surveyid/'.$iSurveyID;  // Close button

        if (Yii::app()->request->getPost('ok')=='')
        {
            if (!tableExists('survey_'.$iSurveyID))
            {
                $_SESSION['flashmessage'] = gT("Error: Response table does not exist. Survey cannot be deactivated.");
                $this->getController()->redirect($this->getController()->createUrl("admin/survey/sa/view/surveyid/{$iSurveyID}"));
            }
            $aData['surveyid'] = $iSurveyID;
            $aData['date']     = $date;
            $aData['dbprefix'] = Yii::app()->db->tablePrefix;
            $aData['step1']    = true;
        }
        else
        {
            //See if there is a tokens table for this survey
            if (tableExists("{{tokens_{$iSurveyID}}}"))
            {
                $toldtable = Yii::app()->db->tablePrefix."tokens_{$iSurveyID}";
                $tnewtable = Yii::app()->db->tablePrefix."old_tokens_{$iSurveyID}_{$date}";
                if (Yii::app()->db->getDriverName() == 'pgsql')
                {
                    $tidDefault = Yii::app()->db->createCommand("SELECT pg_attrdef.adsrc FROM pg_attribute JOIN pg_class ON (pg_attribute.attrelid=pg_class.oid) JOIN pg_attrdef ON(pg_attribute.attrelid=pg_attrdef.adrelid AND pg_attribute.attnum=pg_attrdef.adnum) WHERE pg_class.relname='$toldtable' and pg_attribute.attname='tid'")->queryScalar();
                    if(preg_match("/nextval\('(tokens_\d+_tid_seq\d*)'::regclass\)/", $tidDefault, $matches)){
                        $oldSeq = $matches[1];
                        Yii::app()->db->createCommand()->renameTable($oldSeq, $tnewtable . '_tid_seq');
                        $setsequence = "ALTER TABLE ".Yii::app()->db->quoteTableName($toldtable)." ALTER COLUMN tid SET DEFAULT nextval('{$tnewtable}_tid_seq'::regclass);";
                        Yii::app()->db->createCommand($setsequence)->query();
                    }
                }

                Yii::app()->db->createCommand()->renameTable($toldtable, $tnewtable);

                $aData['tnewtable'] = $tnewtable;
                $aData['toldtable'] = $toldtable;

                // Reset the session of the survey when deactivating it
                killSurveySession($iSurveyID);
            }

            //Remove any survey_links to the CPDB
            SurveyLink::model()->deleteLinksBySurvey($iSurveyID);


            // IF there are any records in the saved_control table related to this survey, they have to be deleted
            SavedControl::model()->deleteSomeRecords(array('sid' => $iSurveyID)); //Yii::app()->db->createCommand($query)->query();
            $sOldSurveyTableName = Yii::app()->db->tablePrefix."survey_{$iSurveyID}";
            $sNewSurveyTableName = Yii::app()->db->tablePrefix."old_survey_{$iSurveyID}_{$date}";
            $aData['sNewSurveyTableName']=$sNewSurveyTableName;

            $query = "SELECT id FROM ".Yii::app()->db->quoteTableName($sOldSurveyTableName)." ORDER BY id desc";
            $sLastID = Yii::app()->db->createCommand($query)->limit(1)->queryScalar();
            //Update the autonumber_start in the survey properties
            $new_autonumber_start = $sLastID + 1;
            $survey = Survey::model()->findByAttributes(array('sid' => $iSurveyID));
            $survey->autonumber_start = $new_autonumber_start;
            $survey->save();
            if (Yii::app()->db->getDriverName() == 'pgsql')
            {
                $idDefault = Yii::app()->db->createCommand("SELECT pg_attrdef.adsrc FROM pg_attribute JOIN pg_class ON (pg_attribute.attrelid=pg_class.oid) JOIN pg_attrdef ON(pg_attribute.attrelid=pg_attrdef.adrelid AND pg_attribute.attnum=pg_attrdef.adnum) WHERE pg_class.relname='$sOldSurveyTableName' and pg_attribute.attname='id'")->queryScalar();
                if(preg_match("/nextval\('(survey_\d+_id_seq\d*)'::regclass\)/", $idDefault, $matches)){
                    $oldSeq = $matches[1];
                    Yii::app()->db->createCommand()->renameTable($oldSeq, $sNewSurveyTableName . '_id_seq');
                    $setsequence = "ALTER TABLE ".Yii::app()->db->quoteTableName($sOldSurveyTableName)." ALTER COLUMN id SET DEFAULT nextval('{{{$sNewSurveyTableName}}}_id_seq'::regclass);";
                    Yii::app()->db->createCommand($setsequence)->query();
                }
            }

            Yii::app()->db->createCommand()->renameTable($sOldSurveyTableName, $sNewSurveyTableName);

            $survey->active = 'N';
            $survey->save();

            $prow = Survey::model()->find('sid = :sid', array(':sid' => $iSurveyID));
            if ($prow->savetimings == "Y")
            {
                $sOldTimingsTableName = Yii::app()->db->tablePrefix."survey_{$iSurveyID}_timings";
                $sNewTimingsTableName = Yii::app()->db->tablePrefix."old_survey_{$iSurveyID}_timings_{$date}";
                Yii::app()->db->createCommand()->renameTable($sOldTimingsTableName, $sNewTimingsTableName);
                $aData['sNewTimingsTableName'] = $sNewTimingsTableName;
            }

            $aData['surveyid'] = $iSurveyID;
            Yii::app()->db->schema->refresh();
        }

        $aData['sidemenu']['state'] = false;
        $aData['surveybar']['closebutton'] = false;
        $this->_renderWrappedTemplate('survey', 'deactivateSurvey_view', $aData);
    }

    /**
    * Function responsible to activate survey.
    *
    * @access public
    * @param int $iSurveyID
    * @return void
    */
    public function activate($iSurveyID)
    {
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveyactivation', 'update')) die();

        $iSurveyID = (int) $iSurveyID;

        $aData = array();
        $aData['sidemenu']['state'] = false;
        $aData['aSurveysettings'] = getSurveyInfo($iSurveyID);
        $aData['surveyid'] = $iSurveyID;
        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";
        // Die if this is not possible
        if (!isset($aData['aSurveysettings']['active']) || $aData['aSurveysettings']['active'] == 'Y')
            $this->getController()->error('Survey not active');

        $qtypes = getQuestionTypeList('', 'array');
        Yii::app()->loadHelper("admin/activate");

        if (Yii::app()->request->getPost('ok')=='')
        {
            if (isset($_GET['fixnumbering']) && $_GET['fixnumbering'])
            {
                fixNumbering($_GET['fixnumbering'], $iSurveyID);
            }

            // Check consistency for groups and questions
            $failedgroupcheck = checkGroup($iSurveyID);
            $failedcheck = checkQuestions($iSurveyID, $iSurveyID, $qtypes);

            $aData['failedcheck'] = $failedcheck;
            $aData['failedgroupcheck'] = $failedgroupcheck;
            $aData['aSurveysettings'] = getSurveyInfo($iSurveyID);

            $this->_renderWrappedTemplate('survey', 'activateSurvey_view', $aData);
        }
        else
        {
            $survey = Survey::model()->findByAttributes(array('sid' => $iSurveyID));
            if (!is_null($survey))
            {
                $survey->anonymized = Yii::app()->request->getPost('anonymized');
                $survey->datestamp = Yii::app()->request->getPost('datestamp');
                $survey->ipaddr = Yii::app()->request->getPost('ipaddr');
                $survey->refurl = Yii::app()->request->getPost('refurl');
                $survey->savetimings = Yii::app()->request->getPost('savetimings');
                $survey->save();
                Survey::model()->resetCache();  // Make sure the saved values will be picked up
            }

            $aResult=activateSurvey($iSurveyID);
            $aViewUrls = array();
            if (isset($aResult['error']))
            {
                $aViewUrls['output']= "<br />\n<div class='messagebox ui-corner-all'>\n";
                if ($aResult['error']=='surveytablecreation')
                {
                    $aViewUrls['output'].="<div class='alert alert-warning' role='alert'>".gT("The survey response table could not be created.")." ".gT("Usually this is caused by having too many (sub-)questions in your survey. Please try removing questions from your survey.")."</div>\n";
                }
                else
                {
                    $aViewUrls['output'].="<div class='alert alert-success' role='alert'>".gT("Timings table could not be created.")."</div>\n";
                }
                if(App()->getConfig('debug')) {
                    $aViewUrls['output'].="<strong class='text-warning'>" .
                    gT("Database error!!")."\n " ."\n" .
                    "<pre>".var_export ($aResult['error'],true)."</pre>\n";
                }

                $aViewUrls['output'].="<a href='".Yii::app()->getController()->createUrl("admin/survey/sa/view/surveyid/".$iSurveyID)."'>".gT("Main Admin Screen")."</a>\n</strong><br/>" ;
            }
            else
            {
                $warning = (isset($aResult['warning']))?true:false;
                $allowregister = ($survey->allowregister=='Y')?true:false;
                $onclickAction = convertGETtoPOST(Yii::app()->getController()->createUrl("admin/tokens/sa/index/surveyid/".$iSurveyID));
                $closedOnclickAction = convertGETtoPOST(Yii::app()->getController()->createUrl("admin/tokens/sa/index/surveyid/".$iSurveyID));
                $noOnclickAction = convertGETtoPOST(Yii::app()->getController()->createUrl("admin/survey/sa/view/surveyid/".$iSurveyID));

                $activationData = array(
                    'iSurveyID'=>$iSurveyID,
                    'warning'=>$warning,
                    'allowregister'=>$allowregister,
                    'onclickAction'=>$onclickAction,
                    'closedOnclickAction'=>$closedOnclickAction,
                    'noOnclickAction'=>$noOnclickAction,
                );
                $aViewUrls['output'] = $this->getController()->renderPartial('/admin/survey/_activation_feedback', $activationData, true);

            }
            $this->_renderWrappedTemplate('survey', $aViewUrls, $aData);
        }
    }

    /**
    * Function responsible to delete a survey.
    *
    * @access public
    * @param int $iSurveyID
    * @return html
    */
    public function delete($iSurveyID)
    {
        $aData = $aViewUrls = array();
        $aData['surveyid'] = $iSurveyID = (int) $iSurveyID;
        $aData['sidemenu']['state'] = false;
        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";
        $aData['sidemenu']['state'] = false;


        if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'delete'))
        {
            if (Yii::app()->request->getPost("delete") == 'yes')
            {
                $aData['issuperadmin'] = Permission::model()->hasGlobalPermission('superadmin','read');
                $this->_deleteSurvey($iSurveyID);
                Yii::app()->session['flashmessage'] = gT("Survey deleted.");
                $this->getController()->redirect(array("admin/index"));
            }
            else
            {
                $aViewUrls[] = 'deleteSurvey_view';
            }
        }
        else
        {
            Yii::app()->user->setFlash('error', gT("Access denied"));
            $this->getController()->redirect(Yii::app()->request->urlReferrer);
        }


        $this->_renderWrappedTemplate('survey', $aViewUrls, $aData);
    }

    /**
    * Takes the edit call from the detailed survey view, which either deletes the survey information
    */
    public function editSurvey_json()
    {
        $operation = Yii::app()->request->getPost('oper');
        $iSurveyIDs = Yii::app()->request->getPost('id');
        if ($operation == 'del') // If operation is delete , it will delete, otherwise edit it
        {
            foreach(explode(',',$iSurveyIDs) as $iSurveyID)
            {
                if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'delete'))
                {
                    $this->_deleteSurvey($iSurveyID);
                }
            }
        }
    }
    /**
    * Load editing of local settings of a survey screen.
    *
    * @access public
    * @param int $iSurveyID
    * @return void
    */
    public function editlocalsettings($iSurveyID)
    {
        $aViewUrls = $aData = array();

        $aData['surveyid'] = $iSurveyID = sanitize_int($iSurveyID);

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'read') || Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'read'))
        {
            $this->_registerScriptFiles();

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'update'))
            {
                Yii::app()->session['FileManagerContext'] = "edit:survey:{$iSurveyID}";
            }
            $grplangs = Survey::model()->findByPk($iSurveyID)->additionalLanguages;
            $baselang = Survey::model()->findByPk($iSurveyID)->language;
            array_unshift($grplangs, $baselang);

            Yii::app()->loadHelper("admin/htmleditor");

            $aData['scripts'] = PrepareEditorScript(false, $this->getController());
            $aTabTitles = $aTabContents=  array();

            foreach ($grplangs as $i => $sLang)
            {
                // this one is created to get the right default texts fo each language
                Yii::app()->loadHelper('database');
                Yii::app()->loadHelper('surveytranslator');

                $esrow = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id' => $iSurveyID, 'surveyls_language' => $sLang))->getAttributes();
                $aTabTitles[$sLang] = getLanguageNameFromCode($esrow['surveyls_language'], false);

                if ($esrow['surveyls_language'] == Survey::model()->findByPk($iSurveyID)->language)
                {
                    $aTabTitles[$sLang] .= '(' . gT("Base language") . ')';
                }

                $aData['esrow'] = $esrow;
                $aData['action'] = "editsurveylocalesettings";
                $aData['i'] = $i;
                $aData['dateformatdetails'] = getDateFormatData(Yii::app()->session['dateformat']);
                $aTabContents[$sLang] = $this->getController()->renderPartial('/admin/survey/editLocalSettings_view', $aData, true);
            }

            unset($aData['i']);

            $aData['has_permissions'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'update');
            $aData['surveyls_language'] = $esrow["surveyls_language"];
            $aData['aTabContents'] = $aTabContents;
            $aData['aTabTitles'] = $aTabTitles;

            $esrow = self::_fetchSurveyInfo('editsurvey', $iSurveyID);
            $aData['esrow'] = $esrow;

            $aData = array_merge($aData, $this->_generalTabEditSurvey($iSurveyID, $esrow));
            $aData = array_merge($aData, $this->_tabPresentationNavigation($esrow));
            $aData = array_merge($aData, $this->_tabPublicationAccess($esrow));
            $aData = array_merge($aData, $this->_tabNotificationDataManagement($esrow));
            $aData = array_merge($aData, $this->_tabTokens($esrow));
            $aData = array_merge($aData, $this->_tabPanelIntegration($esrow));
            $aData = array_merge($aData, $this->_tabResourceManagement($iSurveyID));

            $oResult = Question::model()->getQuestionsWithSubQuestions($iSurveyID, $esrow['language'], "({{questions}}.type = 'T'  OR  {{questions}}.type = 'Q'  OR  {{questions}}.type = 'T' OR {{questions}}.type = 'S')");

            $aData['questions'] = $oResult;
            $aData['display']['menu_bars']['surveysummary'] = "editsurveysettings";
            $tempData = $aData;
            $aData['settings_data'] = $tempData;

            $aData['sidemenu']['state'] = false;
            $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
            $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";

            $aData['surveybar']['savebutton']['form'] = 'globalsetting';
            $aData['surveybar']['savebutton']['useformid'] = 'true';
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update') || Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'update'))
            {
                $aData['surveybar']['saveandclosebutton']['form'] = true;
            }
            else
            {
                unset($aData['surveybar']['savebutton']['form']);
            }

            $aData['surveybar']['closebutton']['url'] = 'admin/survey/sa/view/surveyid/'.$iSurveyID;  // Close button

            $aViewUrls[] = 'editLocalSettings_main_view';
        }
        else
        {
            Yii::app()->setFlashMessage(gT("You do not have permission to access this page."),'error');
            $this->getController()->redirect(array('admin/survey','sa'=>'view','surveyid'=>$iSurveyID));
        }

        $this->_renderWrappedTemplate('survey', $aViewUrls, $aData);
    }

    /**
    * Function responsible to import/copy a survey based on $action.
    *
    * @access public
    * @return void
    */
    public function copy()
    {
        $action = Yii::app()->request->getParam('action');
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('sid'));

        if ($action == "importsurvey" || $action == "copysurvey")
        {
            // Start the HTML
            $sExtension = "";

            if ($action == 'importsurvey')
            {
                $aData                    = array();
                $aData['sHeader']         = gT("Import survey data");
                $aData['sSummaryHeader'] = gT("Survey structure import summary");
                $aPathInfo = pathinfo($_FILES['the_file']['name']);

                if (isset($aPathInfo['extension']))
                {
                    $sExtension = $aPathInfo['extension'];
                }

            }
            elseif ($action == 'copysurvey')
            {
                $aData                    = array();
                $aData['sHeader'] = gT("Copy survey");
                $aData['sSummaryHeader'] = gT("Survey copy summary");
            }
            // Start traitment and messagebox
            $aData['bFailed'] = false; // Put a var for continue
            $sFullFilepath = '';
            if ($action == 'importsurvey')
            {

                $sFullFilepath = Yii::app()->getConfig('tempdir') . DIRECTORY_SEPARATOR . randomChars(30).'.'.$sExtension;
                if ($_FILES['the_file']['error']==1 || $_FILES['the_file']['error']==2)
                {
                    $aData['sErrorMessage']=sprintf(gT("Sorry, this file is too large. Only files up to %01.2f MB are allowed."), getMaximumFileUploadSize()/1024/1024).'<br>';
                    $aData['bFailed'] = true;
                }
                elseif (!in_array(strtolower($sExtension),array('lss','txt','tsv','lsa')))
                {
                    $aData['sErrorMessage'] = sprintf(gT("Import failed. You specified an invalid file type '%s'."), $sExtension);
                    $aData['bFailed'] = true;
                }
                elseif ($aData['bFailed'] || !@move_uploaded_file($_FILES['the_file']['tmp_name'], $sFullFilepath))
                {
                    $aData['sErrorMessage'] = gT("An error occurred uploading your file. This may be caused by incorrect permissions for the application /tmp folder.");
                    $aData['bFailed'] = true;
                }
            }
            elseif ($action == 'copysurvey')
            {
                $iSurveyID = sanitize_int(Yii::app()->request->getParam('copysurveylist'));
                $aExcludes = array();

                $sNewSurveyName = Yii::app()->request->getPost('copysurveyname');

                if (Yii::app()->request->getPost('copysurveyexcludequotas') == "1")
                {
                    $aExcludes['quotas'] = true;
                }
                if (Yii::app()->request->getPost('copysurveyexcludepermissions') == "1")
                {
                    $aExcludes['permissions'] = true;
                }
                if (Yii::app()->request->getPost('copysurveyexcludeanswers') == "1")
                {
                    $aExcludes['answers'] = true;
                }
                if (Yii::app()->request->getPost('copysurveyresetconditions') == "1")
                {
                    $aExcludes['conditions'] = true;
                }
                if (Yii::app()->request->getPost('copysurveyresetstartenddate') == "1")
                {
                    $aExcludes['dates'] = true;
                }
                if (!$iSurveyID)
                {
                    $aData['sErrorMessage'] = gT("No survey ID has been provided. Cannot copy survey");
                    $aData['bFailed'] = true;
                }
                elseif(!Survey::model()->findByPk($iSurveyID))
                {
                    $aData['sErrorMessage'] = gT("Invalid survey ID");
                    $aData['bFailed'] = true;
                }
                elseif (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'export') && !Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'export'))
                {
                    $aData['sErrorMessage'] = gT("We are sorry but you don't have permissions to do this.");
                    $aData['bFailed'] = true;
                }
                else
                {
                    Yii::app()->loadHelper('export');
                    $copysurveydata = surveyGetXMLData($iSurveyID, $aExcludes);
                }
            }

            // Now, we have the survey : start importing
            Yii::app()->loadHelper('admin/import');

            if ($action == 'importsurvey' && !$aData['bFailed'])
            {
                $aImportResults=importSurveyFile($sFullFilepath,(Yii::app()->request->getPost('translinksfields')=='1'));
                if (is_null($aImportResults))
                {
                    $aImportResults=array(
                        'error'=>gT("Unknown error while reading the file, no survey created.")
                    );
                }
            }
            elseif ($action == 'copysurvey' && !$aData['bFailed'])
            {
                $aImportResults = XMLImportSurvey('', $copysurveydata, $sNewSurveyName, sanitize_int(App()->request->getParam('copysurveyid')), (Yii::app()->request->getPost('copysurveytranslinksfields')=='1'));
                if (isset($aExcludes['conditions']))
                {
                    Question::model()->updateAll(array('relevance'=>'1'),'sid='.$aImportResults['newsid']);
                    QuestionGroup::model()->updateAll(array('grelevance'=>'1'),'sid='.$aImportResults['newsid']);
                }
                if (!isset($aExcludes['permissions']))
                {
                    Permission::model()->copySurveyPermissions($iSurveyID,$aImportResults['newsid']);
                }
            }
            else
            {
                $aData['bFailed'] = true;
            }
            if ($action == 'importsurvey' && isset($sFullFilepath) && file_exists($sFullFilepath))
            {
                unlink($sFullFilepath);
            }
            if (!$aData['bFailed'])
            {
                $aData['aImportResults'] = $aImportResults;
                $aData['action'] = $action;
                if(isset($aImportResults['newsid']))
                {
                    $aData['sLink'] = $this->getController()->createUrl('admin/survey/sa/view/surveyid/' . $aImportResults['newsid']);
                }
            }

        }

        $this->_renderWrappedTemplate('survey', 'importSurvey_view', $aData);
    }

    /**
    * questiongroup::organize()
    * Load ordering of question group screen.
    *
    * @param int $iSurveyID
    * @return void
    */
    public function organize($iSurveyID)
    {
        $request = Yii::app()->request;

        $iSurveyID = (int) $iSurveyID;

        $thereIsPostData = $request->getPost('orgdata') !== null;
        $userHasPermissionToUpdate = Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'update');

        if (!$userHasPermissionToUpdate)
        {
            Yii::app()->user->setFlash('error', gT("Access denied"));
            $this->getController()->redirect(Yii::app()->request->urlReferrer);
        }

        if ($thereIsPostData)
        {
            // Save the new ordering
            $this->_reorderGroup($iSurveyID);

            $closeAfterSave = $request->getPost('close-after-save') === 'true';
            if ($closeAfterSave)
            {
                $this->getController()->redirect(array('admin/survey/sa/view/surveyid/' . $iSurveyID));
            }
            else
            {
                $this->_showReorderForm($iSurveyID);
            }
        }
        else
        {
            $this->_showReorderForm($iSurveyID);
        }
    }


    /**
     * Called via ajax request from survey summary quick action "Show questions group by group"
     *
     */
    public function changeFormat($iSurveyID, $format)
    {
        if ( Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'update') )
        {
            if(in_array($format, array('S','G','A')))
            {
                $survey = Survey::model()->findByPk($iSurveyID);
                $survey->format = $format;
                $survey->save();
                echo $survey->format;
            }
        }
    }

    /**
     * Show the form for Organize question groups/questions
     *
     * @todo Change function name to _showOrganizeGroupsAndQuestions?
     * @param int $iSurveyID
     * @return void
     */
    private function _showReorderForm($iSurveyID)
    {
        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']." (".gT("ID").":".$iSurveyID.")";

        // Prepare data for the view
        $sBaseLanguage = Survey::model()->findByPk($iSurveyID)->language;
        LimeExpressionManager::StartSurvey($iSurveyID, 'survey');
        LimeExpressionManager::StartProcessingPage(true, Yii::app()->baseUrl);

        $aGrouplist = QuestionGroup::model()->getGroups($iSurveyID);
        $initializedReplacementFields = false;

        $aData['organizebar']['savebuttonright'] = true;
        $aData['organizebar']['closebuttonright']['url'] = $this->getController()->createUrl("admin/survey/sa/view/", array('surveyid' => $iSurveyID));
        $aData['organizebar']['saveandclosebuttonright']['url'] = true;

        foreach ($aGrouplist as $iGID => $aGroup)
        {
            LimeExpressionManager::StartProcessingGroup($aGroup['gid'], false, $iSurveyID);
            if (!$initializedReplacementFields) {
                templatereplace("{SITENAME}"); // Hack to ensure the EM sets values of LimeReplacementFields
                $initializedReplacementFields = true;
            }

            $oQuestionData = Question::model()->getQuestions($iSurveyID, $aGroup['gid'], $sBaseLanguage);

            $qs = array();
            $junk = array();

            foreach ($oQuestionData->readAll() as $q)
            {
                $relevance = ($q['relevance'] == '') ? 1 : $q['relevance'];
                $question = '[{' . $relevance . '}] ' . $q['question'];
                LimeExpressionManager::ProcessString($question, $q['qid']);
                $q['question'] = viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
                $q['gid'] = $aGroup['gid'];
                $qs[] = $q;
            }
            $aGrouplist[$iGID]['questions'] = $qs;
            LimeExpressionManager::FinishProcessingGroup();
        }
        LimeExpressionManager::FinishProcessingPage();

        $aData['aGroupsAndQuestions'] = $aGrouplist;
        $aData['surveyid'] = $iSurveyID;

        $this->_renderWrappedTemplate('survey', 'organizeGroupsAndQuestions_view', $aData);
    }

    private function _reorderGroup($iSurveyID)
    {
        $AOrgData = array();
        parse_str(Yii::app()->request->getPost('orgdata'), $AOrgData);

        $grouporder = 0;
        foreach ($AOrgData['list'] as $ID => $parent)
        {
            if ($parent == 'root' && $ID[0] == 'g') {
                QuestionGroup::model()->updateAll(array('group_order' => $grouporder), 'gid=:gid', array(':gid' => (int)substr($ID, 1)));
                $grouporder++;
            }
            elseif ($ID[0] == 'q')
            {
                $qid = (int)substr($ID, 1);
                $gid = (int)substr($parent, 1);
                if (!isset($aQuestionOrder[$gid]))
                    $aQuestionOrder[$gid] = 0;

                $sBaseLanguage = Survey::model()->findByPk($iSurveyID)->language;
                $oQuestion = Question::model()->findByPk(array("qid"=>$qid,'language'=>$sBaseLanguage));
                $oldGid = $oQuestion['gid'];

                if($oldGid != $gid) {
                        fixMovedQuestionConditions($qid,$oldGid,$gid,$iSurveyID);
                }
                Question::model()->updateAll(array('question_order' => $aQuestionOrder[$gid], 'gid' => $gid), 'qid=:qid', array(':qid' => $qid));
                Question::model()->updateAll(array('gid' => $gid), 'parent_qid=:parent_qid', array(':parent_qid' => $qid));
                $aQuestionOrder[$gid]++;
            }
        }
        LimeExpressionManager::SetDirtyFlag(); // so refreshes syntax highlighting
        Yii::app()->session['flashmessage'] = gT("The new question group/question order was successfully saved.");
    }

    /**
    * survey::_fetchSurveyInfo()
    * Load survey information based on $action.
    * @param mixed $action
    * @param mixed $iSurveyID
    * @return
    */
    private function _fetchSurveyInfo($action, $iSurveyID=null)
    {
        if ($action == 'newsurvey')
        {
            $oSurvey=new Survey;
        }
        elseif ($action == 'editsurvey' && $iSurveyID)
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
        }

        if($oSurvey)
        {
            $attribs = $oSurvey->attributes;
            $attribs['googleanalyticsapikeysetting'] = $oSurvey->getGoogleanalyticsapikeysetting();
            return $attribs;
        }
    }

    /**
    * survey::_generalTabNewSurvey()
    * Load "General" tab of new survey screen.
    * @return
    */
    private function _generalTabNewSurvey()
    {
        //Use the current user details for the default administrator name and email for this survey
        $user=User::model()->findByPk(Yii::app()->session['loginID']);
        $owner =$user->attributes;

        //Degrade gracefully to $siteadmin details if anything is missing.
        if (empty($owner['full_name']))
            $owner['full_name'] = getGlobalSetting('siteadminname');
        if (empty($owner['email']))
            $owner['email'] = getGlobalSetting('siteadminemail');

        //Bounce setting by default to global if it set globally
        if (getGlobalSetting('bounceaccounttype') != 'off')
        {
            $owner['bounce_email'] = getGlobalSetting('siteadminbounce');
        }
        else
        {
            $owner['bounce_email'] = $owner['email'];
        }

        $aData['action'] = "newsurvey";
        $aData['owner'] = $owner;
        $aLanguageDetails= getLanguageDetails(Yii::app()->session['adminlang']);
        $aData['sRadixDefault'] = $aLanguageDetails['radixpoint'];
        $aData['sDateFormatDefault'] = $aLanguageDetails['dateformat'];
        foreach (getRadixPointData() as $index=>$radixptdata){
          $aRadixPointData[$index]=$radixptdata['desc'];
        }
        $aData['aRadixPointData']=$aRadixPointData;

        foreach (getDateFormatData (0,Yii::app()->session['adminlang']) as $index => $dateformatdata)
        {
          $aDateFormatData[$index]=$dateformatdata['dateformat'];
        }
        $aData['aDateFormatData']=$aDateFormatData;

        return $aData;
    }

    /**
    * survey::_generalTabEditSurvey()
    * Load "General" tab of edit survey screen.
    * @param mixed $iSurveyID
    * @param mixed $esrow
    * @return
    */
    private function _generalTabEditSurvey($iSurveyID, $esrow)
    {
        $aData['action'] = "editsurveysettings";
        $aData['esrow'] = $esrow;
        $aData['surveyid'] = $iSurveyID;

        // Get users, but we only need id and name (NOT password etc)
        $users = getUserList();
        $aData['users'] = array();
        foreach ($users as $user)
        {
            $aData['users'][$user['uid']] = $user['user'].($user['full_name']?' - '.$user['full_name']:'');
        }
        // Sort users by name
        asort($aData['users']);
        $beforeSurveySettings = new PluginEvent('beforeSurveySettings');
        $beforeSurveySettings->set('survey', $iSurveyID);
        App()->getPluginManager()->dispatchEvent($beforeSurveySettings);
        $aData['pluginSettings'] = $beforeSurveySettings->get('surveysettings');
        return $aData;
    }

    /**
    * survey::_tabPresentationNavigation()
    * Load "Presentation & navigation" tab.
    * @param mixed $esrow
    * @return
    */
    private function _tabPresentationNavigation($esrow)
    {
        global $showxquestions, $showgroupinfo, $showqnumcode;

        Yii::app()->loadHelper('globalsettings');

        $shownoanswer = getGlobalSetting('shownoanswer') ? getGlobalSetting('shownoanswer') : 'Y';

        $aData = array();
        $aData['esrow'] = $esrow;
        $aData['shownoanswer'] = $shownoanswer;
        $aData['showxquestions'] = $showxquestions;
        $aData['showgroupinfo'] = $showgroupinfo;
        $aData['showqnumcode'] = $showqnumcode;
        return $aData;
    }

    /**
    * survey::_tabPublicationAccess()
    * Load "Publication * access control" tab.
    * @param mixed $esrow
    * @return
    */
    private function _tabPublicationAccess($esrow)
    {
        $aDateFormatDetails = getDateFormatData(Yii::app()->session['dateformat']);
        $startdate = '';
        if ($esrow['startdate'])
        {
            Yii::app()->loadLibrary('Date_Time_Converter');
            $datetimeobj = new Date_Time_Converter($esrow["startdate"],"Y-m-d H:i:s"); //new Date_Time_Converter($esrow['startdate'] , "Y-m-d H:i:s");
            $startdate = $datetimeobj->convert($aDateFormatDetails['phpdate'].' H:i');
        }

        $expires = '';
        if ($esrow['expires'])
        {
            Yii::app()->loadLibrary('Date_Time_Converter');
            $datetimeobj = new Date_Time_Converter($esrow['expires'], "Y-m-d H:i:s"); //new Date_Time_Converter($esrow['expires'] , "Y-m-d H:i:s");
            $expires = $datetimeobj->convert($aDateFormatDetails['phpdate'].' H:i');
        }
        $aData['dateformatdetails']= $aDateFormatDetails;
        $aData['esrow'] = $esrow;
        $aData['startdate'] = $startdate;
        $aData['expires'] = $expires;
        return $aData;
    }

    /**
    * survey::_tabNotificationDataManagement()
    * Load "Notification & data management" tab.
    * @param mixed $esrow
    * @return
    */
    private function _tabNotificationDataManagement($esrow)
    {
        $aData['esrow'] = $esrow;
        return $aData;
    }

    /**
    * survey::_tabTokens()
    * Load "Tokens" tab.
    * @param mixed $esrow
    * @return
    */
    private function _tabTokens($esrow)
    {
        $aData = array();
        $aData['esrow'] = $esrow;
        return $aData;
    }

    private function _tabPanelIntegration($esrow)
    {
        $aData = array();
        return $aData;
    }

    /**
    * survey::_tabImport()
    * Load "Import" tab.
    * @param mixed $iSurveyID
    * @return
    */
    private function _tabImport()
    {
        $aData = array();
        return $aData;
    }

    /**
    * survey::_tabCopy()
    * Load "Copy" tab.
    * @param mixed $iSurveyID
    * @return
    */
    private function _tabCopy()
    {
        $aData = array();
        return $aData;
    }

    /**
    * survey::_tabResourceManagement()
    * Load "Resources" tab.
    * @param mixed $iSurveyID
    * @return
    */
    private function _tabResourceManagement($iSurveyID)
    {
        global $sCKEditorURL;

        // TAB Uploaded Resources Management
        $ZIPimportAction = " onclick='if (validatefilename(this.form,\"" . gT('Please select a file to import!', 'js') . "\")) { this.form.submit();}'";
        if (!function_exists("zip_open"))
        {
            $ZIPimportAction = " onclick='alert(\"" . gT("The ZIP library is not activated in your PHP configuration thus importing ZIP files is currently disabled.", "js") . "\");'";
        }

        $disabledIfNoResources = '';
        if (hasResources($iSurveyID, 'survey') === false)
        {
            $disabledIfNoResources = " disabled='disabled'";
        }
        //$aData['esrow'] = $esrow;
        $aData['ZIPimportAction'] = $ZIPimportAction;
        $aData['disabledIfNoResources'] = $disabledIfNoResources;
        $dqata['sCKEditorURL'] = $sCKEditorURL;

        return $aData;
    }

    function expire($iSurveyID)
    {
        $iSurveyID = (int) $iSurveyID;
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update'))
        {
            die();
        }
        Yii::app()->session['flashmessage'] = gT("The survey was successfully expired by setting an expiration date in the survey settings.");
        Survey::model()->expire($iSurveyID);
        $this->getController()->redirect(array('admin/survey/sa/view/surveyid/' . $iSurveyID));
    }

    function getUrlParamsJSON($iSurveyID)
    {
        $iSurveyID = (int) $iSurveyID;
        $sBaseLanguage = Survey::model()->findByPk($iSurveyID)->language;
        $sQuery = "select '' as act, up.*,q.title, sq.title as sqtitle, q.question, sq.question as sqquestion from {{survey_url_parameters}} up
        left join {{questions}} q on q.qid=up.targetqid
        left join {{questions}} sq on sq.qid=up.targetsqid
        where up.sid={$iSurveyID} and (q.language='{$sBaseLanguage}' or q.language is null) and (sq.language='{$sBaseLanguage}' or sq.language is null)";
        $oResult = Yii::app()->db->createCommand($sQuery)->queryAll();
        $i = 0;
        $aData = new stdClass();
        foreach ($oResult as $oRow)
        {
            $aData->rows[$i]['id'] = $oRow['id'];
            if (!is_null($oRow['question']))
            {
                        $oRow['title'] .= ': ' . ellipsize(flattenText($oRow['question'], false, true), 43, .70);
            }
            else
            {
                        $oRow['title'] = gT('(No target question)');
            }

            if ($oRow['sqquestion'] != '')
            {
                $oRow['title'] .= (' - ' . ellipsize(flattenText($oRow['sqquestion'], false, true), 30, .75));
            }
            unset($oRow['sqquestion']);
            unset($oRow['sqtitle']);
            unset($oRow['question']);

            $aData->rows[$i]['cell'] = array_values($oRow);
            $i++;
        }

        $aData->page = 1;
        $aData->records = count($oResult);
        $aData->total = 1;

        echo ls_json_encode($aData);
    }

    /**
    * This private function deletes a survey
    * Important: If you change this function also change the remotecontrol XMLRPC function
    *
    * @param mixed $iSurveyID  The survey ID to delete
    */
    private function _deleteSurvey($iSurveyID)
    {
        Survey::model()->deleteSurvey($iSurveyID);
        rmdirr(Yii::app()->getConfig('uploaddir') . '/surveys/' . $iSurveyID);
    }

    /**
    * Executes registerScriptFile for all needed script/style files
    *
    * @return void
    */
    private function _registerScriptFiles()
    {
        $this->registerScriptFile( 'ADMIN_SCRIPT_PATH', 'surveysettings.js');
        App()->getClientScript()->registerPackage('jquery-json');
        App()->getClientScript()->registerPackage('jqgrid');

    }

    /**
    * Saves the new survey after the creation screen is submitted
    *
    * @param $iSurveyID  The survey id to be used for the new survey. If already taken a new random one will be used.
    */
    function insert($iSurveyID=null)
    {
        if (Permission::model()->hasGlobalPermission('surveys','create'))
        {
            // Check if survey title was set
            if (Yii::app()->request->getPost('surveyls_title')=='')
            {
                Yii::app()->session['flashmessage'] = gT("Survey could not be created because it did not have a title");
                redirect($this->getController()->createUrl('admin'));
                return;
            }

            Yii::app()->loadHelper("surveytranslator");
            // If start date supplied convert it to the right format
            $aDateFormatData = getDateFormatData(Yii::app()->session['dateformat']);
            $sStartDate = Yii::app()->request->getPost('startdate');
            if (trim($sStartDate) != '')
            {
                Yii::import('application.libraries.Date_Time_Converter');
                $converter = new Date_Time_Converter($sStartDate, $aDateFormatData['phpdate'] . ' H:i:s');
                $sStartDate = $converter->convert("Y-m-d H:i:s");
            }

            // If expiry date supplied convert it to the right format
            $sExpiryDate = Yii::app()->request->getPost('expires');
            if (trim($sExpiryDate) != '')
            {
                Yii::import('application.libraries.Date_Time_Converter');
                $converter = new Date_Time_Converter($sExpiryDate, $aDateFormatData['phpdate'] . ' H:i:s');
                $sExpiryDate = $converter->convert("Y-m-d H:i:s");
            }

            $iTokenLength=(int)Yii::app()->request->getPost('tokenlength');
            //token length has to be at least 5, otherwise set it to default (15)
            if($iTokenLength < 5)
            {
                $iTokenLength = 15;
            }
            if($iTokenLength > 36)
            {
                $iTokenLength = 36;
            }

            // Insert base settings into surveys table
            $aInsertData = array(
            'expires' => $sExpiryDate,
            'startdate' => $sStartDate,
            'template' => App()->request->getPost('template'),
            'owner_id' => Yii::app()->session['loginID'],
            'admin' => App()->request->getPost('admin'),
            'active' => 'N',
            'anonymized' => App()->request->getPost('anonymized')=='1'?'Y':'N',
            'faxto' => App()->request->getPost('faxto'),
            'format' => App()->request->getPost('format'),
            'savetimings' => App()->request->getPost('savetimings')=='1'?'Y':'N',
            'language' => App()->request->getPost('language'),
            'datestamp' => App()->request->getPost('datestamp')=='1'?'Y':'N',
            'ipaddr' => App()->request->getPost('ipaddr')=='1'?'Y':'N',
            'refurl' => App()->request->getPost('refurl')=='1'?'Y':'N',
            'usecookie' => App()->request->getPost('usecookie')=='1'?'Y':'N',
            'emailnotificationto' => App()->request->getPost('emailnotificationto'),
            'allowregister' => App()->request->getPost('allowregister')=='1'?'Y':'N',
            'allowsave' => App()->request->getPost('allowsave')=='1'?'Y':'N',
            'navigationdelay' => App()->request->getPost('navigationdelay'),
            'autoredirect' => App()->request->getPost('autoredirect')=='1'?'Y':'N',
            'showxquestions' => App()->request->getPost('showxquestions')=='1'?'Y':'N',
            'showgroupinfo' => App()->request->getPost('showgroupinfo'),
            'showqnumcode' => App()->request->getPost('showqnumcode'),
            'shownoanswer' => App()->request->getPost('shownoanswer')=='1'?'Y':'N',
            'showwelcome' => App()->request->getPost('showwelcome')=='1'?'Y':'N',
            'allowprev' => App()->request->getPost('allowprev')=='1'?'Y':'N',
            'questionindex' => App()->request->getPost('questionindex'),
            'nokeyboard' => App()->request->getPost('nokeyboard')=='1'?'Y':'N',
            'showprogress' => App()->request->getPost('showprogress')=='1'?'Y':'N',
            'printanswers' => App()->request->getPost('printanswers')=='1'?'Y':'N',
            'listpublic' => App()->request->getPost('listpublic')=='1'?'Y':'N',
            'htmlemail' => App()->request->getPost('htmlemail')=='1'?'Y':'N',
            'sendconfirmation' => App()->request->getPost('sendconfirmation')=='1'?'Y':'N',
            'tokenanswerspersistence' => App()->request->getPost('tokenanswerspersistence')=='1'?'Y':'N',
            'alloweditaftercompletion' => App()->request->getPost('alloweditaftercompletion')=='1'?'Y':'N',
            'usecaptcha' => Survey::transcribeCaptchaOptions(),
            'publicstatistics' => App()->request->getPost('publicstatistics')=='1'?'Y':'N',
            'publicgraphs' => App()->request->getPost('publicgraphs')=='1'?'Y':'N',
            'assessments' => App()->request->getPost('assessments')=='1'?'Y':'N',
            'emailresponseto' => App()->request->getPost('emailresponseto'),
            'tokenlength' => $iTokenLength,
            );

            $warning = '';
            // make sure we only update emails if they are valid
            if (Yii::app()->request->getPost('adminemail', '') == ''
                || validateEmailAddress(Yii::app()->request->getPost('adminemail'))) {
                $aInsertData['adminemail'] = Yii::app()->request->getPost('adminemail');
            } else {
                $aInsertData['adminemail'] = '';
                $warning .= gT("Warning! Notification email was not updated because it was not valid.").'<br/>';
            }
            if (Yii::app()->request->getPost('bounce_email', '') == ''
                || validateEmailAddress(Yii::app()->request->getPost('bounce_email'))) {
                $aInsertData['bounce_email'] = Yii::app()->request->getPost('bounce_email');
            } else {
                $aInsertData['bounce_email'] = '';
                $warning .= gT("Warning! Bounce email was not updated because it was not valid.").'<br/>';
            }

            if (!is_null($iSurveyID))
            {
                $aInsertData['wishSID'] = $iSurveyID;
            }

            $iNewSurveyid = Survey::model()->insertNewSurvey($aInsertData);
            if (!$iNewSurveyid)
                die('Survey could not be created.');

            // Prepare locale data for surveys_language_settings table
            $sTitle          = Yii::app()->request->getPost('surveyls_title');
            $sDescription    = Yii::app()->request->getPost('description');
            $sWelcome        = Yii::app()->request->getPost('welcome');
            $sURLDescription = Yii::app()->request->getPost('urldescrip');

            $sTitle          = html_entity_decode($sTitle, ENT_QUOTES, "UTF-8");
            $sDescription    = html_entity_decode($sDescription, ENT_QUOTES, "UTF-8");
            $sWelcome        = html_entity_decode($sWelcome, ENT_QUOTES, "UTF-8");
            $sURLDescription = html_entity_decode($sURLDescription, ENT_QUOTES, "UTF-8");

            // Fix bug with FCKEditor saving strange BR types
            $sTitle       = fixCKeditorText($sTitle);
            $sDescription = fixCKeditorText($sDescription);
            $sWelcome     = fixCKeditorText($sWelcome);


            // Insert base language into surveys_language_settings table
            $aInsertData = array(
                'surveyls_survey_id'      => $iNewSurveyid,
                'surveyls_title'          => $sTitle,
                'surveyls_description'    => $sDescription,
                'surveyls_welcometext'    => $sWelcome,
                'surveyls_language'       => Yii::app()->request->getPost('language'),
                'surveyls_urldescription' => Yii::app()->request->getPost('urldescrip'),
                'surveyls_endtext'        => Yii::app()->request->getPost('endtext'),
                'surveyls_url'            => Yii::app()->request->getPost('url'),
                'surveyls_dateformat'     => (int) Yii::app()->request->getPost('dateformat'),
                'surveyls_numberformat'   => (int) Yii::app()->request->getPost('numberformat'),
            );

            $langsettings = new SurveyLanguageSetting;
            $langsettings->insertNewSurvey($aInsertData);
            // Update survey permissions
            Permission::model()->giveAllSurveyPermissions(Yii::app()->session['loginID'], $iNewSurveyid);

            Yii::app()->session['flashmessage'] = $warning.gT("Survey was successfully added.");
            if (App()->request->getPost('saveandclose'))
            {
                $this->getController()->redirect(array('admin/survey/sa/view/surveyid/' . $iNewSurveyid));
            }
            else
            {
                $this->getController()->redirect(array('admin/survey/sa/editlocalsettings/surveyid/' . $iNewSurveyid));
            }

        }
    }

    /**
    * Renders template(s) wrapped in header and footer
    *
    * @param string $sAction Current action, the folder to fetch views from
    * @param string|array $aViewUrls View url(s)
    * @param array $aData Data to be passed on. Optional.
    */
    protected function _renderWrappedTemplate($sAction = 'survey', $aViewUrls = array(), $aData = array())
    {
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData);
    }

}
