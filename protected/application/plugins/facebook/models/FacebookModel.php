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
 
class TwitterModel extends SourceModel {

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
		$userid = $this->getProperty('userid');

		if (!$userid) {
			throw new Stuffpress_Exception("Update failed, connector not properly configured");
		}
		
		// Proceed with the update
		$items = $this->updateData(true);

		$this->setImported(true);
		return $items;
	}

	public function updateData($import = false) {
		// Get service propertie
		$config = Zend_Registry::get("configuration");
		$api_key  = $config->facebook->api_key;
		$api_secret  = $config->facebook->api_secret;
		
		// Get user properties
		$userid = $this->getProperty('userid');
		
		//Get the status updates from facebook. This uses FQL and the Facebook Library
		$fb = new Facebook('$api_key', '$apiSecret');
		//If import for first time, we don't need a since timestamp in the query
		if($import){
			$query = "SELECT status_id,message,time,source FROM status WHERE uid  = '$userid'";
		}else{
			$last_status = getLastTime();
			$query = "SELECT status_id,message,time,source FROM status WHERE uid  = '$userid' AND time > '$last_status'";
		}
		$result = $facebook->api_client->fql_query($query);
		
		if(is_null($result)){
			break;		
		}
		
		//Can now process the status updates
		processItems($result);

		// Mark as updated (could have been with errors)
		$this->markUpdated();
		
		return $result;
	}
	
	private function processItems($items) {
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

	public function getConfigForm($populate=false) {
		$form = new Stuffpress_Form();

		//Name
		$label	 = $this->getServiceName(). " Your Name:";
		$element = $form->createElement('text', 'name', array('label' => $label , 'decorators' => $form->elementDecorators));
		$element->setRequired(true);
		$form->addElement($element);
		
		//userid
		$idlabel	 = $this->getServiceName(). " Facebook User Id:";
		$idelement = $form->createElement('text', 'userid', array('label' => $idlabel , 'decorators' => $form->elementDecorators));
		$idelement->setRequired(true);
		$form->addElement($idelement);
	
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

		return $update;
	}

	private function getLastTime() {
		$sql  = "SELECT created_at FROM `facebook_data` ORDER BY id DESC";
		$time = $this->_db->fetchOne($sql);
		return $time;
	}

}
