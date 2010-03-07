<?php
/*
 *  Copyright 2008-2009 Laurent Eschenauer and Alard Weisscher
 *  Copyright 2010 John Hobbs
 *  Copyright 2010 Lee Stone
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *  
 */
 
//Need the facebook library in here
require_once('facebooklib/facebook.php');
 
class FacebookModel extends SourceModel {

	protected $_name 	= 'facebook_data';

	protected $_prefix  = 'facebook';

	protected $_search  = 'status';
	
	protected $_update_tweet = "Updated %d times my Facebook status %s"; 

	public function getServiceName() {
		return "Facebook";
	}

	public function getServiceURL() {
		return "http://www.facebook.com/profile.php?id=".$this->getProperty('userid');
	}

	public function getServiceDescription() {
		return "Facebook helps you connect and share with the people in your life.";
	}

	public function isStoryElement() {
		return true;
	}
	
	public function importData() {

		$items = $this->updateData(true);

		$this->setImported(true);
		return $items;
	}
	
	function processItems($items) {
		$result = array();
		if ($items && count($items)>0) foreach ($items as $item) {
			$data = array();
			
			$data['created_at'] 			= (string) $item["time"];
			$data['status_id']				= (string) $item["status_id"];
			$data['status'] 				= (string) $this->getProperty('name')." ".$item["message"];
			$data['source'] 				= (string) $item["source"];

			$id = $this->addItem($data, $data['created_at'] , SourceItem::STATUS_TYPE, false, false, false, $data['status']);
			
			if ($id) $result[] = $id;	
			unset($data);
		}
		return $result;
	}

	public function updateData($import = false) {
		// Get service properties
		$config = Zend_Registry::get("configuration");
		$api_key  = $config->facebook->api_key;
		$api_secret  = $config->facebook->api_secret;
		
		// Get user properties
		$userid = $this->getProperty('userid');
		$ses_key = $this->getProperty('ses_key');
		$ses_sec = $this->getProperty('ses_sec');
		
		// Get the status updates from facebook. This uses FQL and the Facebook Library
		$fb = new Facebook("$api_key", "$api_secret");
		$fb->set_user($userid, $ses_key, $expires=null, $ses_sec);

		// If import for first time, we don't need a since timestamp in the query
		if($import){
			$query = "SELECT status_id,message,time,source FROM status WHERE uid  = '$userid'";
		}else{
			//$last_status = getLastTime();
			//TODO Fix so getLastTime(); Works
			$sql  = "SELECT created_at FROM `facebook_data` ORDER BY id DESC";
			$last_status = $this->_db->fetchOne($sql);
			$query = "SELECT status_id,message,time,source FROM status WHERE uid  = '$userid' AND time > '$last_status'";
		}
		$items = $fb->api_client->fql_query($query);
		
		if(is_null($items)){
			break;		
		}
		
		$allresult = array();
		
		// Can now process the status updates
		//processItems($items);
		//TODO Fix so processItems works
		$result = array();
		if ($items && count($items)>0) foreach ($items as $item) {
			$data = array();
			
			$data['created_at'] 			= $item["time"];
			$data['status_id']				= $item["status_id"];
			$data['status'] 				= $this->getProperty('name')." ".$item["message"];
			$data['source'] 				= $item["source"];
			$data['is_hidden'] 				= false;

			$id = $this->addItem($data, $data['created_at'] , SourceItem::STATUS_TYPE, false, false, 0, $data['status']);
			
			if ($id) $result[] = $id;	
			unset($data);
		}
			
		$allresult = array_merge($allresult,$result);

		// Mark as updated (could have been with errors)
		$this->markUpdated();
		
		return $allresult;
	}
	
	public function getConfigForm($populate=false) {
		$form = new Stuffpress_Form();
		
		// Get service properties
		$config = Zend_Registry::get("configuration");
		$api_key  = $config->facebook->api_key;
		$api_secret  = $config->facebook->api_secret;
		
		//This will handle the facebook login
		//We only want to login the first time - checks for the fb paramaeter as this indicates we have already logged in.
		if(isset($_GET['fb'])){
			$fbprocessed = htmlspecialchars($_GET['fb']);
		} else {
			$fbprocessed = false;
		}
		//When saving, we also want to stop it from trying to logging into Facebook again.
		if ($_SERVER["REQUEST_URI"] == "/admin/services/save"){
			$fbprocessed = true;
		}
		if($fbprocessed != true){
			//Get a new facebook login
			$fbl = new Facebook("$api_key", "$api_secret");
			$fbl_user = $fbl->require_login($required_permissions = 'offline_access');
		} else {	
			//As we have logged in previously, we can get the Facebook UserId from the URL
			if(isset($_GET['fbl_user'])){
				$fbl_user = htmlspecialchars($_GET['fbl_user']);
			} else {
				$fbl_user = "";
			}
		}
		
		// When we have the facebook info, we need to break out of the iFrame Facebook puts us in
		// At the end of the URL we add fb=true to indicate we have already logged in
			echo "<SCRIPT language=\"JavaScript\" TYPE=\"text/JavaScript\">
				function handleError() {
				window.parent.location=location+\"&fb=true&fbl_user=$fbl_user\"
				}

				window.onerror = handleError;
				if (window.parent.frames.length>0) {
				if (window.parent.document.body.innerHTML) {
				//do nothing
				}
				}
				</SCRIPT> ";

		// Name
		$label	 = "Your Name:";
		$element = $form->createElement('text', 'name', array('label' => $label , 'decorators' => $form->elementDecorators));
		$element->setRequired(true);
		$form->addElement($element);
		
		// Userid
		$idlabel	 = "Facebook User Id:";
		$idelement = $form->createElement('text', 'userid', array('label' => $idlabel , 'decorators' => $form->elementDecorators));
		$idelement->setRequired(true);
		$idelement->setValue($fbl_user);
		$form->addElement($idelement);
		
		
		// Session Key for user
		// Value is fetched from the URL from the facebook login
		if(isset($_GET['fb_sig_session_key'])){
			$ses_key = htmlspecialchars($_GET['fb_sig_session_key']);
		} else {
			$ses_key = null;
		}
		$keylabel	 = "";
		$keyelement = $form->createElement('hidden', 'ses_key', array('label' => $keylabel , 'decorators' => $form->elementDecorators));
		$keyelement->setRequired(true);
		// We don't want to set the value if it is being populated from the database
		if(!$populate){
			$keyelement->setValue($ses_key);
		}
		$form->addElement($keyelement);
		
		// Session Secret for user
		// Value is fetched from the URL from the facebook login
		if(isset($_GET['fb_sig_ss'])){
			$ses_sec = htmlspecialchars($_GET['fb_sig_ss']);
		} else {
			$ses_sec = null;
		}
		$seclabel	 = "";
		$secelement = $form->createElement('hidden', 'ses_sec', array('label' => $seclabel , 'decorators' => $form->elementDecorators));
		$secelement->setRequired(true);
		// We don't want to set the value if it is being populated from the database
		if(!$populate){
			$secelement->setValue($ses_sec);
		}
		$form->addElement($secelement);
		
	
		if($populate) {
			$values  = $this->getProperties();
			$form->populate($values);
		}

		return $form;
		
	}

	public function processConfigForm($form) {
		$values   = $form->getValues();
		$name =  $values['name'];
		$userid =  $values['userid'];
		$ses_key =  $values['ses_key'];
		$ses_sec =  $values['ses_sec'];
		$update	  = false;

		// Save name
		if($name != $this->getProperty('name')) {
			$this->_properties->setProperty('name',   $name);
			$update = true;
		}
		
		// Save Userid
		if($userid != $this->getProperty('userid')) {
			$this->_properties->setProperty('userid',   $userid);
			$update = true;
		}
		
		// Save Session Key
		if($ses_key != $this->getProperty('ses_key')) {
			$this->_properties->setProperty('ses_key',   $ses_key);
			$update = true;
		}
		
		// Save Session Secret
		if($ses_sec != $this->getProperty('ses_sec')) {
			$this->_properties->setProperty('ses_sec',   $ses_sec);
			$update = true;
		}

		return $update;
	}

	private function getLastTime() {
		$sql  = "SELECT created_at FROM `facebook_data` ORDER BY id DESC";
		$time = $this->_db->fetchOne($sql);
		return $time;
	}

}
