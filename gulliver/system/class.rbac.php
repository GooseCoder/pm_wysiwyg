<?php
/**
 * class.rbac.php
 * @package gulliver.system 
 *
 * ProcessMaker Open Source Edition
 * Copyright (C) 2004 - 2011 Colosa Inc.
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
/**
 * File: $Id$
 *
 * RBAC class definition
 *
 * @package gulliver.system
 * @copyright (C) 2002 by Colosa Development Team.
 * @link http://www.colosa.com
 * @link  http://manuals.colosa.com/gulliver/rbac.html
 * @author Fernando Ontiveros
 */

/**
 * Clase Wrapper
 *
 * @package gulliver.system
 * @author Fernando Ontiveros
 */

class RBAC
{
  /**
  *
  * @access private
  * @var $userObj
  */
  var $userObj;
  var $usersPermissionsObj;
  var $usersRolesObj;
  var $systemObj;
  var $rolesObj;
  var $permissionsObj;
  var $userloggedobj;
  var $currentSystemobj;
  var $rolesPermissionsObj;
  var $authSourcesObj;

  var $aUserInfo = array();
  var $aRbacPlugins = array();
  var $sSystem = '';

  static private $instance = NULL;

  private function __construct() {
  }

  /**
  * to get singleton instance
  *
  * @access public
  * @return object
  */  
  function &getSingleton() {
    if (self::$instance == NULL) {
      self::$instance = new RBAC();
    }
    return self::$instance;
  }

  /**
  * to get start with some classess
  *
  * @access public
  * @return object
  */ 
  function initRBAC () {
   if ( is_null($this->userObj ) ) {
     require_once ( "classes/model/RbacUsers.php" );
     $this->userObj = new RbacUsers;
   }

   if ( is_null($this->systemObj ) ) {
     require_once ( "classes/model/Systems.php" );
     $this->systemObj = new Systems;
   }

   if ( is_null($this->usersRolesObj ) ) {
     require_once ( "classes/model/UsersRoles.php" );
     $this->usersRolesObj = new UsersRoles;
   }

   if ( is_null($this->rolesObj ) ) {
     require_once ( "classes/model/Roles.php" );
     $this->rolesObj = new Roles;
   }

   if ( is_null($this->permissionsObj ) ) {
     require_once ( "classes/model/Permissions.php" );
     $this->permissionsObj = new Permissions;
   }

   if ( is_null($this->rolesPermissionsObj ) ) {
     require_once ( "classes/model/RolesPermissions.php" );
     $this->rolesPermissionsObj = new RolesPermissions;
   }

   if (is_null($this->authSourcesObj)) {
     require_once 'classes/model/AuthenticationSource.php';
     $this->authSourcesObj = new AuthenticationSource();
   }
   //hook for RBAC plugins
   $pathPlugins = PATH_RBAC . 'plugins';
   if ( is_dir ( $pathPlugins ) ) {
     if ($handle = opendir( $pathPlugins )) {
       while ( false !== ($file = readdir($handle))) {
         if ( strpos($file, '.php',1) && is_file( $pathPlugins . PATH_SEP . $file) &&
              substr($file,0,6) == 'class.' && substr($file,-4) == '.php' )  {

           $sClassName = substr($file,6, strlen($file) - 10);
           require_once ($pathPlugins . PATH_SEP . $file);
           $this->aRbacPlugins[] = $sClassName;

         }
       }
     }
   }
  }

  /**
  * Gets the roles and permission for one RBAC_user
  *
  * gets the Role and their permissions for one User
  *
  * @author Fernando Ontiveros Lira <fernando@colosa.com>
  * @access public

  * @param  string $sSystem    the system
  * @param  string $sUser      the user
  * @return $this->aUserInfo[ $sSystem ]
  */
  function loadUserRolePermission( $sSystem, $sUser, $pathData = null, $sid =null ) {
    //if we provide a path data and session we will cache the session Info for this user
    if ( $pathData != null && $sid != null ) {
      $pathData = $pathData . 'session' . PATH_SEP;
      $filePath = $pathData . $sid . '.rbac';
      if ( file_exists ( $filePath ) && filesize($filePath) > 0 ) {
          $this->aUserInfo = unserialize( file_get_contents ( $filePath ) );
        return;
      }
    }
    $this->sSystem = $sSystem;
    $fieldsSystem = $this->systemObj->loadByCode($sSystem);
    $fieldsRoles = $this->usersRolesObj->getRolesBySystem ($fieldsSystem['SYS_UID'], $sUser );
    $fieldsPermissions = $this->usersRolesObj->getAllPermissions ($fieldsRoles['ROL_UID'], $sUser );
    $this->aUserInfo[ $sSystem ]['SYS_UID'] = $fieldsSystem['SYS_UID'];
    $this->aUserInfo[ $sSystem ]['ROLE'] = $fieldsRoles;
    $this->aUserInfo[ $sSystem ]['PERMISSIONS'] = $fieldsPermissions;
  
    if ( $pathData != null && $sid != null ) {
       G::mk_dir ( $pathData );
      file_put_contents( $filePath, serialize ( $this->aUserInfo ) );
    }
  }
  
  /**
  * verification the register automatic
  *
  *
  * @access public
  * @param  string $strUser    the system
  * @param  string $strPass    the password
  * @return $res
  */
  function checkAutomaticRegister( $strUser, $strPass) {
    $result = -1; //default return value, 
    
    foreach ( $this->aRbacPlugins as $sClassName) {
      $plugin =  new $sClassName();
      if ( method_exists($plugin, 'automaticRegister' ) ) {
        $oCriteria = new Criteria('rbac');
        $oCriteria->add(AuthenticationSourcePeer::AUTH_SOURCE_PROVIDER, $sClassName );
        $oCriteria->addAscendingOrderByColumn(AuthenticationSourcePeer::AUTH_SOURCE_NAME );
        $oDataset = AuthenticationSourcePeer::doSelectRS($oCriteria);
        $oDataset->setFetchmode(ResultSet::FETCHMODE_ASSOC);
        $oDataset->next();
        $aRow = $oDataset->getRow();
        while ( is_array ( $aRow ) ) {
          $aRow = array_merge ( $aRow, unserialize ( $aRow['AUTH_SOURCE_DATA'] ) );
          //Check if this authsource is enabled for AutoRegister, if not skip this
          if ( $aRow['AUTH_SOURCE_AUTO_REGISTER'] == 1 ) {
            $plugin->sAuthSource = $aRow['AUTH_SOURCE_UID'];
            $plugin->sSystem     = $this->sSystem;
            //search the usersRolesObj
            //create the users in ProcessMaker
            $res = $plugin->automaticRegister($aRow, $strUser, $strPass);
            if ( $res == 1 ) return $res;
          }
          $oDataset->next();
          $aRow = $oDataset->getRow();
        }
      }
    }

  }

  /**
  * this function is checking the register automatic without authentication
  *
  *
  * @access public
  * @param  string $sAuthType  
  * @param  string $sAuthSource
  * @param  string $aUserFields
  * @param  string $sAuthUserDn
  * @param  string $strPass
  * @return number
  *  -2: wrong password
  *  -3: inactive user
  *  -4: due date
  *  -5: invalid authentication source
  */
  function VerifyWithOtherAuthenticationSource( $sAuthType, $sAuthSource, $aUserFields, $sAuthUserDn, $strPass)
  {
    //check if the user is active
    if ( $aUserFields['USR_STATUS']  != 1 )
      return -3;  //inactive user

    //check if the user's due date is valid
    if ( $aUserFields['USR_DUE_DATE']  < date('Y-m-d') )
      return -4;  //due date
      
    foreach ( $this->aRbacPlugins as $sClassName) {
      if ( strtolower($sClassName) == strtolower($sAuthType) ) {
        $plugin =  new $sClassName();
        $plugin->sAuthSource = $sAuthSource;
        $plugin->sSystem     = $this->sSystem;
        $bValidUser = $plugin->VerifyLogin ( $sAuthUserDn, $strPass );

        if ( $bValidUser === TRUE)
          return ( $aUserFields['USR_UID'] );
        else
          return -2; //wrong password

      }
    }
    return -5; //invalid authentication source
  }

  /**
  * authentication of an user through of class RBAC_user
  *
  * checking that an user has right to start an applicaton
  *
  * @author Fernando Ontiveros Lira <fernando@colosa.com>
  * @access public

  * @param  string $strUser    UserId  (login) an user
  * @param  string $strPass    Password
  * @return
  *  -1: no user
  *  -2: wrong password
  *  -3: inactive usuario
  *  -4: due date
  *  -5: invalid authentication source   ( **new )
  *  n : uid of user
  */
  function VerifyLogin( $strUser, $strPass)
  {
    if ( strlen($strPass) == 0) return -2;
    //check if the user exists in the table RB_WORKFLOW.USERS
    $this->initRBAC();
    //if the user exists, the VerifyUser function will return the user properties
    if ( $this->userObj->verifyUser($strUser) == 0 ) {
      //here we are checking if the automatic user Register is enabled, ioc return -1
      $res = $this->checkAutomaticRegister( $strUser, $strPass);
      if ( $res == 1 )
        $this->userObj->verifyUser($strUser);
      else
        return $res;
    }

    //default values
    $sAuthType = 'mysql';
    if ( isset($this->userObj->fields['USR_AUTH_TYPE']) ) $sAuthType = strtolower ( $this->userObj->fields['USR_AUTH_TYPE'] );

    //hook for RBAC plugins
    if ( $sAuthType != 'mysql' && $sAuthType != '' ) {
      $sAuthSource = $this->userObj->fields['UID_AUTH_SOURCE'];
      $sAuthUserDn = $this->userObj->fields['USR_AUTH_USER_DN'];
      $res = $this->VerifyWithOtherAuthenticationSource( $sAuthType, $sAuthSource, $this->userObj->fields, $sAuthUserDn, $strPass);

      return $res;
    }
    else {
      $res = $this->userObj->VerifyLogin($strUser, $strPass);
      return $res;
    }
  }

  /**
  * Verify if the user exist or not exists, the argument is the UserName
  *
  * @author Everth S. Berrios
  * @access public
  * @param string $strUser
  * @return $res
  */
  function verifyUser($strUser) {
    $res = $this->userObj->verifyUser($strUser);
    return $res;
  }

  /**
  * Verify if the user exist or not exists, the argument is the UserUID
  *
  * @author Everth S. Berrios
  * @access public
  * @param string $strUserId
  * @return $res
  */
  function verifyUserId($strUserId) {
    $res = $this->userObj->verifyUserId($strUserId);
    return $res;
  }

  /**
  * Verify if the user has a right over the permission
  *
  * @author Fernando Ontiveros
  * @access public

  * @param  string $uid    id of user
  * @param  string $system Code of System
  * @param  string $perm   id of Permissions
  * @return
  *    1: If it is ok
  *   -1: System doesn't exists
  *   -2: The User has not a Role
  *   -3: The User has not this Permission.
  */
  function userCanAccess ($perm)
  {
    if ( isset ( $this->aUserInfo[ $this->sSystem ]['PERMISSIONS'] ) ) {
      $res = -3;
      //if ( !isset ( $this->aUserInfo[ $this->sSystem ]['ROLE'. 'x'] ) ) $res = -2;
      foreach ( $this->aUserInfo[ $this->sSystem ]['PERMISSIONS'] as $key=>$val )
        if ( $perm == $val['PER_CODE'] ) $res = 1;
    }
    else
      $res = -1;

    return $res;
  }

  /**
  * to create an user
  *
  * @access public
  * @param  array $aData
  * @param  string $sRolCode
  * @return $sUserUID
  */
  function createUser($aData = array(), $sRolCode = '') {
    if ($aData['USR_STATUS'] == 'ACTIVE') {
      $aData['USR_STATUS'] = 1;
    }
    if ($aData['USR_STATUS'] == 'INACTIVE') {
      $aData['USR_STATUS'] = 0;
    }
    $sUserUID = $this->userObj->create($aData);
    if ($sRolCode != '') {
      $this->assignRoleToUser($sUserUID, $sRolCode);
    }
    return $sUserUID;
  }

  /**
  * updated an user
  *
  * @access public
  * @param  array $aData
  * @param  string $sRolCode
  * @return void
  */
  function updateUser($aData = array(), $sRolCode = '') {
    if (isset($aData['USR_STATUS'])) {
      if ($aData['USR_STATUS'] == 'ACTIVE') {
        $aData['USR_STATUS'] = 1;
      }
    }
    $this->userObj->update($aData);
    if ($sRolCode != '') {
      $this->removeRolesFromUser($aData['USR_UID']);
      $this->assignRoleToUser($aData['USR_UID'], $sRolCode);
    }
  }

  /**
  * to put role an user
  *
  * @access public
  * @param  string $sUserUID
  * @param  string $sRolCode
  * @return void
  */
  function assignRoleToUser($sUserUID = '', $sRolCode = '') {
    $aRol = $this->rolesObj->loadByCode($sRolCode);
    $this->usersRolesObj->create($sUserUID, $aRol['ROL_UID']);
  }

  /**
  * remove a role from an user
  *
  * @access public
  * @param  array $sUserUID
  * @return void
  */
  function removeRolesFromUser($sUserUID = '') {
    $oCriteria = new Criteria('rbac');
    $oCriteria->add(UsersRolesPeer::USR_UID, $sUserUID);
    UsersRolesPeer::doDelete($oCriteria);
  }

  /**
  * change status of an user
  *
  * @access public
  * @param  array $sUserUID
  * @return void
  */
  function changeUserStatus($sUserUID = '', $sStatus = 'ACTIVE') {
    if ($sStatus == 'ACTIVE') {
      $sStatus = 1;
    }

    $aFields               = $this->userObj->load($sUserUID);
    $aFields['USR_STATUS'] = $sStatus;
    $this->userObj->update($aFields);
  }

  /**
  * remove an user
  *
  * @access public
  * @param  array $sUserUID
  * @return void
  */
  function removeUser($sUserUID = '') {
    $this->userObj->remove($sUserUID);
    $this->removeRolesFromUser($sUserUID);
  }
  //


  /**
  * getting user's basic information  (rbac)
  *
  * getting datas that is saved in rbac
  *
  * @author Fernando Ontiveros Lira <fernando@colosa.com>
  * @access public

  * @param  string $uid    id user
  * @return array with info of an user
  */
  function load ($uid ){
    $this->initRBAC();
    $this->userObj->Fields = $this->userObj->load($uid);

    $fieldsSystem = $this->systemObj->loadByCode($this->sSystem);
    $fieldsRoles = $this->usersRolesObj->getRolesBySystem ($fieldsSystem['SYS_UID'], $uid );
    $this->userObj->Fields['USR_ROLE'] = $fieldsRoles['ROL_CODE'];
    return $this->userObj->Fields;
  }

  /**
  * loading permission by code
  *
  *
  * @access public

  * @param  string $sCode
  * @return void
  */
//  function loadPermissionByCode($sCode) {
//    return $this->permissionsObj->loadByCode($sCode);
//  }

  /**
  * create permission 
  *
  *
  * @access public

  * @param  string $sCode
  * @return void
  */
  function createPermision($sCode) {
    return $this->permissionsObj->create(array('PER_CODE' => $sCode));
  }

  /**
  * loading role by code
  *
  *
  * @access public

  * @param  string $sCode
  * @return void
  */
//  function loadRoleByCode($sCode) {
//    return $this->rolesObj->loadByCode($sCode);
//  }

  /**
  * list all roles
  *
  *
  * @access public

  * @param  string $systemCode
  * @return $this->rolesObj
  */

  function listAllRoles ( $systemCode = 'PROCESSMAKER') {
      return $this->rolesObj->listAllRoles($systemCode);
  }

  /**
  * getting all roles
  *
  *
  * @access public

  * @param  string $systemCode
  * @return $this->rolesObj->getAllRoles
  */  
  function getAllRoles ( $systemCode = 'PROCESSMAKER') {
      return $this->rolesObj->getAllRoles($systemCode);
  }
  
/**
  * getting all roles by filter
  *
  *
  * @access public
  * @param  string $filter
  * @return $this->rolesObj->getAllRolesFilter
  */  
  function getAllRolesFilter ($start,$limit,$filter) {
      return $this->rolesObj->getAllRolesFilter($start,$limit,$filter);
  }

  /**
  * list all permission
  *
  *
  * @access public

  * @param  string $systemCode
  * @return $this->rolesObj->listAllPermissions
  */ 
  function listAllPermissions ( $systemCode = 'PROCESSMAKER') {
      return $this->rolesObj->listAllPermissions($systemCode);
  }

  /**
  * this function creates a role
  *
  *
  * @access public

  * @param  array $aData
  * @return $this->rolesObj->createRole
  */ 
  function createRole($aData) {
    return $this->rolesObj->createRole($aData);
  }
  
  /**
  * this function removes a role
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->removeRole
  */ 
  function removeRole($ROL_UID){
    return $this->rolesObj->removeRole($ROL_UID);
  }
  
  /**
  * this function checks a new role
  *
  *
  * @access public

  * @param  string $code
  * @return $this->rolesObj->verifyNewRole
  */
  function verifyNewRole($code){
  return $this->rolesObj->verifyNewRole($code);
  }
  
  /**
  * this function updates a role
  *
  *
  * @access public

  * @param  string $fields
  * @return $this->rolesObj->updateRole
  */
  function updateRole($fields){
  return $this->rolesObj->updateRole($fields);
  }
  
  /**
  * this function loads by ID
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->loadById
  */
  function loadById($ROL_UID){
  return $this->rolesObj->loadById($ROL_UID);
  }
  
  /**
  * this function gets the user's roles
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->getRoleUsers
  */
  function getRoleUsers($ROL_UID,$filter=''){
  return $this->rolesObj->getRoleUsers($ROL_UID,$filter);
  }
  
/**
  * this function gets the number of users by roles
  *
  *
  * @access public
  * @author: Enrique Ponce de Leon <enrique@colosa.com>
  * 
  * @return $this->rolesObj->getAllUsersByRole
  */
  function getAllUsersByRole(){
    return $this->rolesObj->getAllUsersByRole();
  }
  
/**
  * this function gets the number of users by department
  *
  *
  * @access public
  * @author: Enrique Ponce de Leon <enrique@colosa.com>
  * 
  * @return $this->rolesObj->getAllUsersByRole
  */
  function getAllUsersByDepartment(){
    return $this->rolesObj->getAllUsersByDepartment();
  }
  
  /**
  * this function gets roles code
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->getRoleCode
  */
  function getRoleCode($ROL_UID){
  return $this->rolesObj->getRoleCode($ROL_UID);
  }
  
  /**
  * this function removes role from an user
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @param  string $USR_UID
  * @return $this->rolesObj->deleteUserRole
  */
  function deleteUserRole($ROL_UID, $USR_UID){
  return $this->rolesObj->deleteUserRole($ROL_UID, $USR_UID);
  }
  
  /**
  * this function gets all user
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->getAllUsers
  */
  function getAllUsers($ROL_UID, $filter=''){
  return $this->rolesObj->getAllUsers($ROL_UID,$filter);
  }
  
  /**
  * this function assigns role an user
  *
  *
  * @access public

  * @param  array $aData
  * @return $this->rolesObj->assignUserToRole
  */
  function assignUserToRole($aData){
  return $this->rolesObj->assignUserToRole($aData);
  }
 
  /**
  * this function gets role permission
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->getRolePermissions
  */
  function getRolePermissions($ROL_UID, $filter=''){
  return $this->rolesObj->getRolePermissions($ROL_UID,$filter);
  }
  
  /**
  * this function gets all permissions
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @param  string $PER_SYSTEM
  * @return $this->rolesObj->getAllPermissions
  */
  function getAllPermissions($ROL_UID,$PER_SYSTEM="",$filter=''){      
      return $this->rolesObj->getAllPermissions($ROL_UID,$PER_SYSTEM,$filter);
  }
  
  /**
  * this function assigns permissions and role
  *
  *
  * @access public

  * @param  array $aData
  * @return $this->rolesObj->assignPermissionRole
  */
  function  assignPermissionRole($sData){
  return $this->rolesObj->assignPermissionRole($sData);
  }
  
  /**
  * this function assigns permissions to a role
  *
  *
  * @access public

  * @param  string $sRoleUID
  * @param  string $sPermissionUID
  * @return $this->rolesPermissionsObj->create
  */
  function assignPermissionToRole($sRoleUID, $sPermissionUID) {
    return $this->rolesPermissionsObj->create(array('ROL_UID' => $sRoleUID, 'PER_UID' => $sPermissionUID));
  }


  /**
  * this function removes permission to role
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @param  string $PER_UID
  * @return $this->rolesObj->deletePermissionRole
  */
  function  deletePermissionRole($ROL_UID, $PER_UID){
  return $this->rolesObj->deletePermissionRole($ROL_UID, $PER_UID);
  }
  
  /**
  * this function counts number of user without role
  *
  *
  * @access public

  * @param  string $ROL_UID
  * @return $this->rolesObj->numUsersWithRole
  */
  function numUsersWithRole($ROL_UID){
  return $this->rolesObj->numUsersWithRole($ROL_UID);
  }

  /**
  * this function creates system code
  *
  *
  * @access public

  * @param  string $sCode
  * @return $this->systemObj->create
  */
  function createSystem($sCode) {
    return $this->systemObj->create(array('SYS_CODE' => $sCode));
  }

  /**
  * this function checks by code
  *
  *
  * @access public

  * @param  string $sCode
  * @return $this->rolesObj->verifyByCode
  */
  function verifyByCode($sCode) {
    return $this->rolesObj->verifyByCode($sCode);
  }

  /**
  * this function gets all authentication source
  * Authentication Sources 
  *
  * @access public

  * @param  void
  * @return $this->authSourcesObj->getAllAuthSources()
  */
  
  function getAllAuthSources() {
    return $this->authSourcesObj->getAllAuthSources();
  }

  /**
  * this function gets all authentication source
  * Authentication Sources 
  *
  * @access public

  * @param  string $sUID
  * @return $this->authSourcesObj->load
  */
  function getAuthSource($sUID) {
    return $this->authSourcesObj->load($sUID);
  }

  /**
  * this function creates an authentication source
  * Authentication Sources 
  *
  * @access public

  * @param  array $aData
  * @return $this->authSourcesObj->create
  */
  function createAuthSource($aData) {
    $this->authSourcesObj->create($aData);
  }


  /**
  * this function updates an authentication source
  * Authentication Sources 
  *
  * @access public

  * @param  array $aData
  * @return $this->authSourcesObj->create
  */
  function updateAuthSource($aData) {
    $this->authSourcesObj->update($aData);
  }

  /**
  * this function removes an authentication source
  * Authentication Sources 
  *
  * @access public

  * @param  string $sUID
  * @return $this->authSourcesObj->remove
  */
  function removeAuthSource($sUID) {
    $this->authSourcesObj->remove($sUID);
  }

  /**
  * this function searchs users
  * 
  *
  * @access public

  * @param  string $sUID
  * @param  string $sKeyword
  * @return array
  */
  function searchUsers($sUID, $sKeyword) {
    $aAuthSource = $this->getAuthSource($sUID);
    $sAuthType   = strtolower($aAuthSource['AUTH_SOURCE_PROVIDER']);
    foreach ( $this->aRbacPlugins as $sClassName) {
      if ( strtolower($sClassName) == $sAuthType ) {
        $plugin =  new $sClassName();
        $plugin->sAuthSource = $sUID;
        $plugin->sSystem     = $this->sSystem;
        return $plugin->searchUsers($sKeyword);
      }
    }
    return array();
  }


  function requirePermissions($permissions){
    $numPerms = func_num_args();
    $permissions = func_get_args();
    
    if ( $numPerms == 1 ){
      $access = $this->userCanAccess($permissions[0]);
    } else if ( $numPerms > 0 ){
      foreach ($permissions as $perm) {
        $access = $this->userCanAccess($perm);
        if( $access != 1 ) {
          $access = -1;
          break;
        }
      }
    } else {
      throw new Exception('function requirePermissions() ->ERROR: Parameters missing!');
    }

   
    if( $access == 1 )
      return true;
    else {
      switch ($access) {
        case -2:
          G::SendTemporalMessage('ID_USER_HAVENT_RIGHTS_SYSTEM', 'error', 'labels');
          G::header('location: ../login/login');
        break;
        case -1:
        default:
          G::SendTemporalMessage('ID_USER_HAVENT_RIGHTS_PAGE', 'error', 'labels');
          G::header('location: ../login/login');
        break;
      }
      exit(0);
    }
  }
}