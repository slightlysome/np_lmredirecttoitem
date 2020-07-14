<?php
/*
    LMRedirectToItem Nucleus plugin
    Copyright (C) 2011 Leo (www.slightlysome.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
	(http://www.gnu.org/licenses/gpl-2.0.html)
	
	See lmredirecttoitem/help.html for plugin description, install, usage and change history.
*/

class NP_LMRedirectToItem extends NucleusPlugin
{
	// name of plugin
	function getName()
	{
		return 'LMRedirectToItem';
	}

	// author of plugin
	function getAuthor()
	{
		return 'Leo (www.slightlysome.net)';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://www.slightlysome.net/nucleus-plugins/np_lmredirecttoitem';
	}

	// version of the plugin
	function getVersion()
	{
		return '1.1.0';
	}

	// a description to be shown on the installed plugins listing
	function getDescription()
	{
		return 'The LMRedirectToItem plugin is for setting up redirects to items. Primarily meant as a help when migrating to a different URL scheme.';
	}

	function supportsFeature ($what)
	{
		switch ($what)
		{
			case 'SqlTablePrefix':
				return 1;
			case 'SqlApi':
				return 1;
			case 'HelpPage':
				return 1;
			default:
				return 0;
		}
	}

	function hasAdminArea()
	{
		return 1;
	}

	function getMinNucleusVersion()
	{
		return '350';
	}
	
	function getEventList() 
	{ 
		return array('PostAuthentication', 'QuickMenu'); 
	}

	function getPluginDep() 
	{
		return array();
	}

	function getTableList()
	{	
		return 	array($this->getTableRedirect());
	}

	function getTableRedirect()
	{
		// select * from nucleus_plug_lmredirecttoitem;
		return sql_table('plug_lmredirecttoitem');
	}

	function _createTableRedirect()
	{
		$query  = "CREATE TABLE IF NOT EXISTS ".$this->getTableRedirect();
		$query .= "( ";
		$query .= "redirectid int(11) NOT NULL auto_increment, ";
		$query .= "fromurl varchar(255) NOT NULL, ";
		$query .= "itemid int(11) NOT NULL, ";
		$query .= "lastused datetime NULL, ";
		$query .= "usecount int(11) NOT NULL, ";
		$query .= "PRIMARY KEY (redirectid), ";
		$query .= "UNIQUE KEY fromurl (fromurl) ";
		$query .= ") ";
		
		sql_query($query);
	}
		
	function install()
	{
		$sourcedataversion = $this->getDataVersion();

		$this->upgradeDataPerform(1, $sourcedataversion);
		$this->setCurrentDataVersion($sourcedataversion);
		$this->upgradeDataCommit(1, $sourcedataversion);
		$this->setCommitDataVersion($sourcedataversion);					
	}
	
	function unInstall()
	{
		if ($this->getOption('del_uninstall') == 'yes')	
		{
			foreach ($this->getTableList() as $table) 
			{
				sql_query("DROP TABLE IF EXISTS ".$table);
			}
		}
	}

	////////////////////////////////////////////////////////////////////////
	// Events

	function event_QuickMenu(&$data) 
	{
		global $member;

		if (!$member->isAdmin()) return;
			array_push($data['options'],
				array('title' => 'LMRedirectToItem',
					'url' => $this->getAdminURL(),
					'tooltip' => 'Administer LMRedirectToItem'));
	}

	function event_PostAuthentication(&$data)
	{
		global $CONF;
		
		$fromurl = serverVar('REQUEST_URI');
		
		$aRedirect = $this->_getRedirectByFromURL($fromurl);
		
		if($aRedirect)
		{
			$aRedirect = $aRedirect['0'];
			
			$itemid = $aRedirect['itemid'];
			
			if($this->_getItemByItemId($itemid))
			{
				$checkurl = $this->_findItemFromURL($itemid);
				
				if($checkurl <> $fromurl)
				{
					$redirectid = $aRedirect['redirectid'];
					$redirecttype = $aRedirect['redirecttype'];

					$this->_updateRedirectCounter($redirectid);
					$itemlink = createItemLink($itemid);

					$pos = strpos($itemlink, '://');
					if(!$pos)
					{
						$itemlink = $CONF['IndexURL'].$itemlink;
					}					
					
					$location = 'Location: '.$itemlink;
					header($location, true, $redirecttype);
					exit;
				}
			}
		}
	}

	///////////////////////////////////////////
	// Public functions on Redirect

	function changeRedirect($redirectid, $fromurl, $itemid, $redirecttype)
	{
		return  $this->_updateRedirect($redirectid, $fromurl, $itemid, $redirecttype);
	}

	function createRedirectForAllItems()
	{
		$aItemInfo = $this->_getItemAll();
		if ($aItemInfo === false) { return false; }
		
		foreach($aItemInfo as $aItem)
		{
			$itemid = $aItem['itemid'];
		
			$fromurl = $this->_findItemFromURL($itemid);
			if($fromurl === false) { return false; }
			
			if(!$this->_getRedirectByFromURL($fromurl))
			{
				$res = $this->_insertRedirect($fromurl, $itemid, 301);
				if($res === false) { return false; }
			}
		}
		
		return true;
	}

	function removeRedirectAll()
	{
		return $this->_deleteRedirectAll();
	}
	
	///////////////////////////////////////////
	// Internal functions on Redirect

	function _removeRedirect($redirectid)
	{
		return $this->_deleteRedirect($redirectid);
	}

	function _findItemFromURL($itemid)
	{
		global $CONF;
		
		$itemlink = createItemLink($itemid);
		$fromurl = '';
		
		$pos = strpos($itemlink, '://');

		if(!$pos)
		{
			$itemlink = $CONF['IndexURL'].$itemlink;
			$pos = strpos($itemlink, '://');
		}
		
		if($pos)
		{
			$pos = strpos($itemlink, '/', $pos + 3);
			
			if($pos)
			{
				$fromurl = substr($itemlink, $pos);
			}
		}
		
		if(!$fromurl) { return false; }
		
		return $fromurl;
	}
	
	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Redirect

	function _insertRedirect($fromurl, $itemid, $redirecttype)
	{
		$query = "INSERT ".$this->getTableRedirect()." (fromurl, itemid, lastused, usecount, redirecttype) "
				."VALUES ('".sql_real_escape_string($fromurl)."', "
					.IntVal($itemid).", "
					."NULL, "
					."0, "
					.IntVal($redirecttype)." "
					.")";
					
		$res = sql_query($query);
		
		if(!$res)
		{
			return false;

		}
		
		$redirectid = sql_insert_id();
		
		return $redirectid;
	}


	function _getRedirectAll()
	{
		return $this->_getRedirect(0, '');
	}
	
	function _getRedirectByFromURL($fromurl)
	{
		return $this->_getRedirect(0, $fromurl);
	}

	function _getRedirectByRedirectId($redirectid)
	{
		return $this->_getRedirect($redirectid, '');
	}
	
	function _getRedirect($redirectid, $fromurl)
	{
		$ret = array();
		
		$currentdataversion = $this->getCurrentDataVersion();
		
		$query = "SELECT redirectid, fromurl, itemid, UNIX_TIMESTAMP(lastused) AS lastused, usecount ";
		
		if($currentdataversion >= 2)
		{
			$query .= ", redirecttype ";
		}
		else
		{
			$query .= ", 301 AS redirecttype ";
		}
	
		$query .= "FROM ".$this->getTableRedirect()." ";
		
		if($redirectid)
		{
			$query .= "WHERE redirectid = ".IntVal($redirectid)." ";
		}
		elseif($fromurl)
		{
			$query .= "WHERE fromurl = '".sql_real_escape_string($fromurl)."' ";
		}
		else
		{
			$query .= "ORDER BY fromurl ";
		}

		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'redirectid'	=> $o->redirectid,
					'fromurl'		=> $o->fromurl,
					'itemid'		=> $o->itemid,
					'lastused'		=> $o->lastused,
					'usecount'		=> $o->usecount,
					'redirecttype'	=> $o->redirecttype
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	function _updateRedirect($redirectid, $fromurl, $itemid, $redirecttype)
	{
		$query = "UPDATE ".$this->getTableRedirect()." SET "
				."fromurl = '".sql_real_escape_string($fromurl)."', "
				."itemid = ".IntVal($itemid).", "
				."redirecttype = ".IntVal($redirecttype)." "
				."WHERE redirectid = ".$redirectid." ";
					
		$res = sql_query($query);
		
		if(!$res)
		{
			return false;

		}
				
		return true;
	}

	function _updateRedirectCounter($redirectid)
	{
		$query = "UPDATE ".$this->getTableRedirect()." SET "
				."usecount = usecount + 1, "
				."lastused =  now() "
				."WHERE redirectid = ".$redirectid." ";
					
		$res = sql_query($query);
		
		if(!$res) { return false; }
				
		return true;
	}
	function _deleteRedirectAll()
	{
		return $this->_deleteRedirect(-1);
	}
	function _deleteRedirect($redirectid)
	{
		$query = "DELETE FROM ".$this->getTableRedirect()." ";
		
		if($redirectid)
		{
			if($redirectid <> -1)
			{
				$query .= "WHERE redirectid = ".$redirectid." ";
			}
		}
		else
		{
			return false;
		}

		$res = sql_query($query);
		
		if(!$res) { return false; }
		
		return true;
	}
	
	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Item
	
	function _getItemAll()
	{
		return $this->_getItemInfo(0, 0);
	}
	
	function _getItemByItemId($itemid)
	{
		return $this->_getItemInfo($itemid, 0);
	}

	function _getItemByCategoryId($categoryid)
	{
		return $this->_getItemInfo(0, $categoryid);
	}
	
	function _getItemInfo($itemid, $categoryid)
	{
		$ret = array();
		
		$query = "SELECT inumber AS itemid, ititle AS itemname, iblog AS blogid, UNIX_TIMESTAMP(itime) as timestamp, idraft AS draft, icat AS catid FROM ".sql_table('item')." ";
		
		if($itemid)
		{
			$query .= "WHERE inumber = ".$itemid." ";
		}
		elseif($categoryid)
		{
			$query .= "WHERE  icat = ".$categoryid." ";
		}

		$res = sql_query($query);
		
		if($res)
		{
			while ($o = sql_fetch_object($res)) 
			{
				array_push($ret, array(
					'itemid'	=> $o->itemid,
					'itemname'	=> $o->itemname,
					'blogid'	=> $o->blogid,
					'timestamp'	=> $o->timestamp,
					'draft'		=> $o->draft,
					'catid'		=> $o->catid
					));
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Plugin Upgrade handling functions
	function getCurrentDataVersion()
	{
		$currentdataversion = $this->getOption('currentdataversion');
		
		if(!$currentdataversion)
		{
			$currentdataversion = 1;
		}
		
		return $currentdataversion;
	}

	function setCurrentDataVersion($currentdataversion)
	{
		$res = $this->setOption('currentdataversion', $currentdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getCommitDataVersion()
	{
		$commitdataversion = $this->getOption('commitdataversion');
		
		if(!$commitdataversion)
		{
			$commitdataversion = 1;
		}

		return $commitdataversion;
	}

	function setCommitDataVersion($commitdataversion)
	{	
		$res = $this->setOption('commitdataversion', $commitdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getDataVersion()
	{
		return 3;
	}
	
	function upgradeDataTest($fromdataversion, $todataversion)
	{
		// returns true if rollback will be possible after upgrade
		$res = true;
				
		return $res;
	}
	
	function upgradeDataPerform($fromdataversion, $todataversion)
	{
		// Returns true if upgrade was successfull
		
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$this->createOption('del_uninstall', 'Delete NP_LMRedirectToItem data on uninstall?', 'yesno','no');
					$this->_createTableRedirect();
					$res = true;
					break;
				case 2:
					// ALTER TABLE nucleus_plug_lmredirecttoitem ADD redirecttype int(11) NOT NULL DEFAULT '301'
					$res = $this->_addColumnIfNotExists($this->getTableRedirect(), 'redirecttype', 'int(11) NOT NULL DEFAULT \'301\'');
					break;
				case 3:
					$this->createOption('currentdataversion', 'currentdataversion', 'text','1', 'access=hidden');
					$this->createOption('commitdataversion', 'commitdataversion', 'text','1', 'access=hidden');
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		
		return true;
	}
	
	function upgradeDataRollback($fromdataversion, $todataversion)
	{
		// Returns true if rollback was successfull
		for($ver = $fromdataversion; $ver >= $todataversion; $ver--)
		{
			switch($ver)
			{
				case 2:
					// ALTER TABLE nucleus_plug_lmredirecttoitem DROP COLUMN redirecttype
					$res = $this->_dropColumnIfExists($this->getTableRedirect(), 'redirecttype');
					break;
				case 3:
					$this->deleteOption('currentdataversion');
					$this->deleteOption('commitdataversion');
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function upgradeDataCommit($fromdataversion, $todataversion)
	{
		// Returns true if commit was successfull
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 2:
					$res = true;
					break;
				case 3:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		return true;
	}
	
	function _checkColumnIfExists($table, $column)
	{
		// Retuns: $column: Found, '' (empty string): Not found, false: error
		$found = '';
		
		$res = sql_query("SELECT * FROM ".$table." WHERE 1 = 2");

		if($res)
		{
			$numcolumns = sql_num_fields($res);

			for($offset = 0; $offset < $numcolumns && !$found; $offset++)
			{
				if(sql_field_name($res, $offset) == $column)
				{
					$found = $column;
				}
			}
		}
		
		return $found;
	}
	
	function _addColumnIfNotExists($table, $column, $columnattributes)
	{
		$found = $this->_checkColumnIfExists($table, $column);
		
		if($found === false) 
		{
			return false;
		}
		
		if(!$found)
		{
			$res = sql_query("ALTER TABLE ".$table." ADD ".$column." ".$columnattributes);

			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function _dropColumnIfExists($table, $column)
	{
		$found = $this->_checkColumnIfExists($table, $column);
		
		if($found === false) 
		{
			return false;
		}
		
		if($found)
		{
			$res = sql_query("ALTER TABLE ".$table." DROP COLUMN ".$column);

			if(!$res)
			{
				return false;
			}
		}

		return true;
	}
}
?>