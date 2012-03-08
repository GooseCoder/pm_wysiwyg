<?php
/**
 * tracker_ViewMap.php
 *
 * ProcessMaker Open Source Edition
 * Copyright (C) 2004 - 2008 Colosa Inc.23
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * For more information, contact Colosa Inc, 2566 Le Jeune Rd.,
 * Coral Gables, FL, 33134, USA, or email info@colosa.com.
 *
 */

   /*
   * Map for Case Tracker
   *
   * @author Everth S. Berrios Morales <everth@colosa.com>
   *
   */
  if (!isset($_SESSION['PROCESS'])) {
    G::header('location: login');
  }
  $G_MAIN_MENU        = 'caseTracker';
  $G_ID_MENU_SELECTED = 'MAP';

  require_once 'classes/model/CaseTracker.php';
  $oCaseTracker = new CaseTracker();
  $aCaseTracker = $oCaseTracker->load($_SESSION['PROCESS']);
  switch (($aCaseTracker['CT_MAP_TYPE'])) {
    case 'NONE':
      //Nothing
    break;
    case 'PROCESSMAP':
      G::LoadClass('case');
      $oCase = new Cases();
      $aFields = $oCase->loadCase($_SESSION['APPLICATION']);
      if (isset($aFields['TITLE'])) {
        $aFields['APP_TITLE'] = $aFields['TITLE'];
      }
      if ($aFields['APP_PROC_CODE'] != '') {
        $aFields['APP_NUMBER'] = $aFields['APP_PROC_CODE'];
      }
      $aFields['CASE']  = G::LoadTranslation('ID_CASE');
      $aFields['TITLE'] = G::LoadTranslation('ID_TITLE');
      $oTemplatePower = new TemplatePower(PATH_TPL . 'processes/processes_Map.html');
      $oTemplatePower->prepare();
      $G_PUBLISH = new Publisher;
      $G_PUBLISH->AddContent('smarty', 'cases/cases_title', '', '', $aFields);
      $G_PUBLISH->AddContent('template', '', '', '', $oTemplatePower);
      $oHeadPublisher =& headPublisher::getSingleton();
      $oHeadPublisher->addScriptCode('
        leimnud.event.add(window,"load",function(){
          var pb=leimnud.dom.capture("tag.body 0");
          Pm=new processmap();
          Pm.options = {
            target    : "pm_target",
            dataServer: "../processes/processes_Ajax",
            uid       : "' . $_SESSION['PROCESS'] . '",
            lang      : "' . SYS_LANG . '",
            theme     : "processmaker",
            size      : {w:pb.offsetWidth-10,h:pb.offsetHeight},
            images_dir: "/jscore/processmap/core/images/",
            rw        : false,
            mi        : false,
            ct        : true,
            hideMenu  : false
          }
          Pm.make();
        });');
      G::RenderPage('publish');
    break;
    case 'STAGES':
      G::LoadClass('case');
      $oCase = new Cases();
      $aFields = $oCase->loadCase($_SESSION['APPLICATION']);
      if (isset($aFields['TITLE'])) {
        $aFields['APP_TITLE'] = $aFields['TITLE'];
      }
      if ($aFields['APP_PROC_CODE'] != '') {
        $aFields['APP_NUMBER'] = $aFields['APP_PROC_CODE'];
      }
      $aFields['CASE']  = G::LoadTranslation('ID_CASE');
      $aFields['TITLE'] = G::LoadTranslation('ID_TITLE');
      $oTemplatePower   = new TemplatePower(PATH_TPL . 'tracker/stages_Map.html');
      $oTemplatePower->prepare();
      $G_PUBLISH = new Publisher;
      $G_PUBLISH->AddContent('smarty', 'cases/cases_title', '', '', $aFields);
      $G_PUBLISH->AddContent('template', '', '', '', $oTemplatePower);
      $oHeadPublisher =& headPublisher::getSingleton();
      $oHeadPublisher->addScriptCode('
        leimnud.Package.Load("stagesmap",{Type:"file",Absolute:true,Path:"/jscore/stagesmap/core/stagesmap.js"});
        leimnud.event.add(window,"load",function(){
          var pb=leimnud.dom.capture("tag.body 0");
          Sm=new stagesmap();
          Sm.options = {
            target    : "sm_target",
            dataServer: "../tracker/tracker_Ajax",
            uid       : "' . $_SESSION['PROCESS'] . '",
            lang      : "' . SYS_LANG . '",
            theme     : "processmaker",
            size      : {w:"780",h:"540"},
            //size    : {w:pb.offsetWidth-10,h:pb.offsetHeight},
            images_dir: "/jscore/processmap/core/images/",
            rw        : false,
            hideMenu  : false
          };
          Sm.make();
        });');
      G::RenderPage('publish');
    break;
}
