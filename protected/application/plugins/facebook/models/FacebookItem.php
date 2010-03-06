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
class FacebookItem extends SourceItem {

	protected $_prefix 	= 'facebook';
	
	protected $_preamble = 'Facebook Status: ';
	
	public function toArray() {
		$this->_data['status'] = $this->getStatus();
		return $this->_data;
	}
	
	public function getType() {
		return SourceItem::STATUS_TYPE;
	}
	
	public function getStatus() {
		$status = htmlspecialchars($this->_data['status']);
		
		return $status; 
	}
	
	public function setStatus($status) {
		$db = Zend_Registry::get('database');
		
		$sql = "UPDATE `facebook_data` SET `status`=:status "
			 . "WHERE source_id = :source_id AND id = :item_id ";
		
		$data 		= array("source_id" 	=> $this->getSource(),
							"item_id"		=> $this->getID(),
							"status"		=> $status);
							
 		$stmt 	= $db->query($sql, $data);

 		return;
	}
	
	public function getTitle() {
		return $this->getStatus();
	}
	
	public function setTitle($title) {
		$this->setStatus($title);
	}
	
	public function getBackup() {
		$item = array();
		$item['Id']					= $this->_data['status_id'];
		$item['Status']				= $this->_data['status'];
		$item['Source']				= $this->_data['source'];
		$item['Timestamp']			= $this->_data['created_at'];
		return $item;
	}
}