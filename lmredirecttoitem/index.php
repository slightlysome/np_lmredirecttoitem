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
*/

	$strRel = '../../../'; 
	require($strRel . 'config.php');
	include_libs('PLUGINADMIN.php');

	// Workaround for bug in Nucleus Code.
	if(($CONF['URLMode'] <> 'pathinfo') && (substr($CONF['Self'], strlen($CONF['Self']) - 10) <> '/index.php'))
	{
		$CONF['Self'] .= '/index.php';
		$CONF['ItemURL'] = $CONF['Self'];
	}
	
	$oPluginAdmin  = new PluginAdmin('LMRedirectToItem');
	$pluginURL 	   = $oPluginAdmin->plugin->getAdminURL();
	
	if (!($member->isLoggedIn()))
	{
		$oPluginAdmin->start();
		echo '<p>You must be logged in to use the LMRedirectToItem plugin admin area.</p>';
		$oPluginAdmin->end();
		exit;
	}

	if (!($member->isAdmin()))
	{
		$oPluginAdmin->start();
		echo '<p>You must be admin to use the LMRedirectToItem plugin admin area.</p>';
		$oPluginAdmin->end();
		exit;
	}

	$redirectid = intRequestVar('redirectid');

	if($redirectid)
	{
		$aRedirect = $oPluginAdmin->plugin->_getRedirectByRedirectId($redirectid);
		
		if(!$redirectid)
		{
		$oPluginAdmin->start();
		echo '<p>Unknown redirectid</p>';
		$oPluginAdmin->end();
		exit;
		}
		$aRedirect = $aRedirect['0'];
	}

	$action = requestVar('action');

	$oPluginAdmin->start("<style type='text/css'>
	<!--
	
		div#content a {
			text-decoration: none;
		}
		div#content img {
			vertical-align: middle;
			margin-top: -3px;
		}
		p.message {
			font-weight: bold;
		}
		p.error {
			font-size: 100%;
			font-weight: bold;
			color: #880000;
		}
		pre {
			overflow: auto;
			height: 400px;
		}
		iframe {
			width: 100%;
			height: 400px;
			border: 1px solid gray;
		}
		div.dialogbox {
			border: 1px solid #ddd;
			background-color: #F6F6F6;
			margin: 18px 0 1.5em 0;
		}
		div.dialogbox h4 {
			background-color: #bbc;
			color: #000;
			margin: 0;
			padding: 5px;
		}
		div.dialogbox h4.light {
			background-color: #ddd;
		}
		div.dialogbox div {
			margin: 0;
			padding: 10px;
		}
		div.dialogbox button {
			margin: 10px 0 0 6px;
			float: right;
		}
		div.dialogbox p {
			margin: 0;
		}
		div.dialogbox p.buttons {
			text-align: right;
			overflow: auto;
		}
		div.dialogbox textarea {
			width: 100%;
			margin: 0;
		}
		div.dialogbox label {
			float: left;
			width: 100px;
		}
		
	-->
	</style>");

	echo '<h2>LMRedirectToItem Administration</h2>';

	$actions = array('edit', 'edit_process', 'delete', 'delete_process', 
		'createredirect', 'createallitems', 'createallitems_process', 'deleteall', 'deleteall_process',
		'upgradeplugindata', 'upgradeplugindata_process', 'rollbackplugindata', 'rollbackplugindata_process', 
		'commitplugindata', 'commitplugindata_process');

	if (in_array($action, $actions)) 
	{ 
		if (!$manager->checkTicket())
		{
			echo '<p class="error">Error: Bad ticket</p>';

			lShowRedirects();
		} 
		else 
		{
			call_user_func('_lmredirecttoitem_' . $action);
		}
	} 
	else 
	{
		lShowRedirects();
	}
	
	$oPluginAdmin->end();
	exit;

	
	function lShowRedirects()
	{
		global $oPluginAdmin, $manager, $pluginURL;
		
		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();
		
//		echo "<p class='message'>sourcedataversion: $sourcedataversion, commitdataversion:$commitdataversion, currentdataversion: $currentdataversion</p>";
		
		if($currentdataversion > $sourcedataversion)
		{
			echo '<p class="error">An old version of the plugin files are installed. Downgrade of the plugin data is not supported.</p>';
		}
		else if($currentdataversion < $sourcedataversion)
		{
			// Upgrade
		
			echo '<div class="dialogbox">';
			echo '<h4 class="light">Upgrade plugin data</h4><div>';
			echo '<form method="post" action="' . htmlspecialchars($pluginUrl) . '">';
			$manager->addTicketHidden();
			echo '<input type="hidden" name="action" value="upgradeplugindata" />';
			echo '<p>The plugin data need to be upgraded before the plugin can be used. ';
			echo 'This function will upgrade the plugin data to the latest version.</p>';
			echo '<p class="buttons"><input type="submit" value="Upgrade" />';
			echo '</p></form></div></div>';
		}
		else
		{
			if($commitdataversion < $currentdataversion)
			{
				// Commit or Rollback
				echo '<div class="dialogbox">';
				echo '<h4 class="light">Commit plugin data upgrade</h4><div>';
				echo '<form method="post" action="' . htmlspecialchars($pluginUrl) . '">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="commitplugindata" />';
				echo '<p>If you choose to continue using this version after you have tested this version of the plugin, ';
				echo 'you have to choose to commit the plugin data upgrade. This function will commit the plugin data ';
				echo 'to the latest version. After the plugin data is committed will you not be able to rollback the ';
				echo 'plugin data to the previous version.</p>';
				echo '<p class="buttons"><input type="submit" value="Commit" />';
				echo '</p></form></div></div>';
				
				echo '<div class="dialogbox">';
				echo '<h4 class="light">Rollback plugin data upgrade</h4><div>';
				echo '<form method="post" action="' . htmlspecialchars($pluginUrl) . '">';
				$manager->addTicketHidden();
				echo '<input type="hidden" name="action" value="rollbackplugindata" />';
				echo '<p>If you choose to go back to the previous version of the plugin after you have tested this ';
				echo 'version of the plugin, you have to choose to rollback the plugin data upgrade. This function ';
				echo 'will rollback the plugin data to the previous version. ';
				echo 'After the plugin data is rolled back you have to update the plugin files to the previous version of the plugin.</p>';
				echo '<p class="buttons"><input type="submit" value="Rollback" />';
				echo '</p></form></div></div>';
			}

			echo '<table><thead><tr>';
			echo '<th>Redirect From</th><th>To ItemId</th><th>Type</th><th>Last Redirect</th><th>Count</th><th colspan="2">Actions</th>';
			echo '</tr></thead>';

			$aRedirectInfo = $oPluginAdmin->plugin->_getRedirectAll();

			foreach($aRedirectInfo as $aRedirect)
			{
				$editURL = $manager->addTicketToUrl($pluginURL . '?action=edit&redirectid='.$aRedirect['redirectid']);
				$editLink = '<a href="'.htmlspecialchars($editURL).'" title="Edit Redirect">Edit</a>';
			
				$deleteURL = $manager->addTicketToUrl($pluginURL . '?action=delete&redirectid='.$aRedirect['redirectid']);
				$deleteLink = '<a href="'.htmlspecialchars($deleteURL).'" title="Delete Redirect">Delete</a>';
			
				echo '<tr onmouseover="focusRow(this);" onmouseout="blurRow(this);">';
				
				if($aRedirect['lastused'])
				{
					$lastused = date('M d Y, H:i', $aRedirect['lastused']);
				}
				else
				{
					$lastused = '';
				}
				
				$itemid = $aRedirect['itemid'];
				if($oPluginAdmin->plugin->_getItemByItemId($itemid))
				{
					$itemURL = createItemLink($itemid);
					$itemLink = '<a href="'.htmlspecialchars($itemURL).'" title="Show Item">'.$itemid.'</a>';
				}
				else
				{
					$itemLink = $itemid;
				}

				switch ($aRedirect['redirecttype'])
				{
					case 301:
						$redirecttype = "Permanent";
						break;
					case 302:
						$redirecttype = "Temporary";
						break;
					default:
						$redirecttype = "Unknown";
						break;
				}

				echo '<td>'.$aRedirect['fromurl'].'</td><td>'.$itemLink.'</td><td>'.$redirecttype.'</td><td>'.$lastused.'</td><td>'.$aRedirect['usecount'].'</td><td>'.$editLink.'</td><td>'.$deleteLink.'</td>';
				echo '</tr>';		
			}
			echo '</table>';

			lShowCreateRedirect();
			
			echo '<div class="dialogbox">';
			echo '<h4 class="light">Create redirects for all items</h4><div>';
			echo '<form method="post" action="' . htmlspecialchars($pluginUrl) . '">';
			$manager->addTicketHidden();
			echo '<input type="hidden" name="action" value="createallitems" />';
			echo '<p>This function will create redirects for all items in all blogs.</p>';
			echo '<p class="buttons"><input type="submit" value="Create Redirects" />';
			echo '</p></form></div></div>';
			
			echo '<div class="dialogbox">';
			echo '<h4 class="light">Delete all redirects</h4><div>';
			echo '<form method="post" action="' . htmlspecialchars($pluginUrl) . '">';
			$manager->addTicketHidden();
			echo '<input type="hidden" name="action" value="deleteall" />';
			echo '<p>This function will delete all redirects.</p>';
			echo '<p class="buttons"><input type="submit" value="Delete All Redirects" />';
			echo '</p></form></div></div>';
			
			echo '<div class="dialogbox">';
			echo '<h4 class="light">Plugin help page</h4>';
			echo '<div>';
			echo '<p>The help page for this plugin is available <a href="'.$CONF['AdminURL'].'index.php?action=pluginhelp&plugid='.$oPluginAdmin->plugin->getID().' ">here</a>.</p>';
			echo '</div></div>';
		}
	}

	function lShowCreateRedirect($fromurl = '', $itemid = '', $redirecttype = '', $showcancel = false)
	{
		global $oPluginAdmin, $manager, $pluginURL;
		
		$historygo = intRequestVar('historygo');
		if($showcancel)
		{
			$historygo--;
		}

		if(!$itemid)
		{
			$itemid = '';
		}
		
		echo '<div class="dialogbox">';
		echo '<h4 class="light">Create new redirect</h4><div>';
		echo '<form method="post" action="' . htmlspecialchars($pluginUrl) . '">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="createredirect" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<p><label for="fromurl">Redirect From:</label> ';
		echo '<input type="text" name="fromurl" size="80" value="'.htmlspecialchars($fromurl).'" /></p>';
		echo '<p><label for="itemid">To ItemId:</label> ';
		echo '<input type="text" name="itemid" size="10" value="'.$itemid.'" /></p>';
		echo '<p><label for="redirecttype">Redirect type:</label> ';

		echo '<select name="redirecttype"><option value="301"';
		if($redirecttype == 301 || !$redirecttype)
		{
			echo ' selected="selected"';
		}

		echo '>Permanent</option><option value="302"';
		if($redirecttype == 302)
		{
			echo ' selected="selected"';
		}		
		echo '>Temporary</option></select></p>';
		
		echo '<p class="buttons"><input type="submit" value="Create Redirect" />';

		if($showcancel)
		{
			echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		}
		echo '</p></form></div></div>';
	}
	
	function _lmredirecttoitem_createredirect()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$itemid = intRequestVar('itemid');
		$fromurl = trim(RequestVar('fromurl'));
		$redirecttype = intRequestVar('redirecttype');
		
		if(!$itemid)
		{
			echo '<p class="error">ItemId must have a value.</p>';
			lShowCreateRedirect($fromurl, $itemid, $redirecttype, true);
			return;
		}

		if(!$fromurl)
		{
			echo '<p class="error">Redirect From must have a value.</p>';
			lShowCreateRedirect($fromurl, $itemid, $redirecttype, true);
			return;
		}

		if($fromurl[0] <> "/")
		{
			echo '<p class="error">Redirect From must start with a /.</p>';
			lShowCreateRedirect($fromurl, $itemid, $redirecttype, true);
			return;
		}

		$aItem = $oPluginAdmin->plugin->_getItemByItemId($itemid);
		
		if(!$aItem)
		{
			echo '<p class="error">Unknown ItemId &quot;'.$itemid.'&quot;.</p>';
			lShowCreateRedirect($fromurl, $itemid, $redirecttype, true);
			return;
		}
		$aItem = $aItem['0'];

		$aRedirect = $oPluginAdmin->plugin->_getRedirectByFromURL($fromurl);
		
		if($aRedirect)
		{
			echo '<p class="error">From URL already exists &quot;'.$fromurl.'&quot;.</p>';
			lShowCreateRedirect($fromurl, $itemid, $redirecttype, true);
			return;
		}
		
		if($oPluginAdmin->plugin->_insertRedirect($fromurl, $itemid, $redirecttype))
		{
			echo '<p class="message"> Created Redirect from &quot;'.htmlspecialchars($fromurl).'&quot; to item &quot;'.$itemid.'&quot;.</p>';
			lShowRedirects();
		}
		else
		{
			echo '<p class="error">Create of Redirect failed.</p>';
			lShowCreateRedirect($fromurl, $itemid, $redirecttype, true);
			return;
		}
	}

	function _lmredirecttoitem_edit($fromurl = '', $itemid = '', $redirecttype = '')
	{
		global $oPluginAdmin, $manager, $pluginURL, $redirectid, $aRedirect;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		if(!$redirectid)
		{
			echo '<p class="error">Missing redirectid.</p>';
			return;
		}
		
		if(!$fromurl)
		{
			$fromurl = $aRedirect['fromurl'];
		}

		if(!$itemid)
		{
			$itemid = $aRedirect['itemid'];
		}
		
		if(!$redirecttype)
		{
			$redirecttype = $aRedirect['redirecttype'];
		}

		echo '<div class="dialogbox">';
		echo '<form method="post" action="' . htmlspecialchars($pluginURL) . '">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="edit_process" />';
		echo '<input type="hidden" name="redirectid" value="'.$redirectid.'" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Edit Redirect</h4><div>';
		echo '<p><label for="fromurl">Redirect From:</label> ';
		echo '<input type="text" name="fromurl" size="80" value="'.htmlspecialchars($fromurl).'" /></p>';
		echo '<p><label for="itemid">To ItemId:</label> ';
		echo '<input type="text" name="itemid" size="10" value="'.$itemid.'" /></p>';
		echo '<p><label for="redirecttype">Redirect type:</label> ';

		echo '<select name="redirecttype"><option value="301"';
		if($redirecttype == 301 || !$redirecttype)
		{
			echo ' selected="selected"';
		}

		echo '>Permanent</option><option value="302"';
		if($redirecttype == 302)
		{
			echo ' selected="selected"';
		}		
		echo '>Temporary</option></select></p>';

		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Edit" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmredirecttoitem_edit_process()
	{
		global $oPluginAdmin, $manager, $pluginURL, $redirectid, $aRedirect;

		if(!$redirectid)
		{
			echo '<p class="error">Missing redirectid.</p>';
			return;
		}

		if (requestVar('sure') == 'yes')
		{
			$fromurl = trim(RequestVar('fromurl'));
			$itemid = intRequestVar('itemid');
			$redirecttype = intRequestVar('redirecttype');

			$aOrgRedirect = $aRedirect;
			$orgfromurl = $aOrgRedirect['fromurl'];
			$orgitemid = $aOrgRedirect['itemid'];
			$orgredirecttype = $aOrgRedirect['redirecttype'];

			if(!$itemid)
			{
				echo '<p class="error">ItemId must have a value.</p>';
				_lmredirecttoitem_edit($fromurl, $itemid, $redirecttype);
				return;
			}

			if(!$fromurl)
			{
				echo '<p class="error">Redirect From must have a value.</p>';
				_lmredirecttoitem_edit($fromurl, $itemid, $redirecttype);
				return;
			}

			if($fromurl[0] <> "/")
			{
				echo '<p class="error">Redirect From must start with a /.</p>';
				_lmredirecttoitem_edit($fromurl, $itemid, $redirecttype);
				return;
			}

			if($fromurl == $orgfromurl && $orgitemid == $itemid && $orgredirecttype == $redirecttype)
			{
				echo '<p class="message">No changes.</p>';
				lShowRedirects();
				return;
			}
			
			if($fromurl <> $orgfromurl && $oPluginAdmin->plugin->_getRedirectByFromURL($fromurl))
			{
				echo '<p class="error">New Redirect From already exists.</p>';
				_lmredirecttoitem_edit($fromurl, $itemid, $redirecttype);
				return;
			}

			if($itemid <> $orgitemid && !$oPluginAdmin->plugin->_getItemByItemId($itemid))
			{
				echo '<p class="error">Unknown ItemId &quot;'.$itemid.'&quot;.</p>';
				_lmredirecttoitem_edit($fromurl, $itemid, $redirecttype);
				return;
			}

			if($oPluginAdmin->plugin->changeRedirect($redirectid, $fromurl, $itemid, $redirecttype))
			{
				echo '<p class="message">Updated Redirect.</p>';
				lShowRedirects();
			}
			else
			{
				echo '<p class="error">Update failed.</p>';
				_lmredirecttoitem_edit($fromurl, $itemid, $redirecttype);
				return;
			}
		}
	}
	
	
	function _lmredirecttoitem_delete()
	{
		global $oPluginAdmin, $manager, $pluginURL, $redirectid, $aRedirect;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		$fromurl = $aRedirect['fromurl'];
				
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.htmlspecialchars($pluginURL).'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="delete_process" />';
		echo '<input type="hidden" name="redirectid" value="'.$redirectid.'" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Delete Redirect From &quot;'.htmlspecialchars($fromurl).'&quot;?</h4><div>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Delete" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmredirecttoitem_delete_process()
	{
		global $oPluginAdmin, $manager, $pluginURL, $redirectid, $aRedirect;

		if (requestVar('sure') == 'yes')
		{
			$fromurl = $aRedirect['fromurl'];

			if($oPluginAdmin->plugin->_removeRedirect($redirectid))
			{
				echo '<p class="message">Redirect From &quot;'.htmlspecialchars($fromurl).'&quot; deleted.</p>';
				lShowRedirects();
			}
			else
			{
				echo '<p class="error">Delete failed.</p>';
				lShowRedirects();
				return;
			}
		}
		else
		{
			// User cancelled
			lShowRedirects();
		}
	}
	
	function _lmredirecttoitem_createallitems()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.htmlspecialchars($pluginURL).'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="createallitems_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Create redirects for all items</h4><div>';
		echo '<p>Are you sure you want to create redirects for all items?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Create Redirects" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmredirecttoitem_createallitems_process()
	{
		global $oPluginAdmin, $manager, $pluginURL;
		
		if($oPluginAdmin->plugin->createRedirectForAllItems())
		{
			echo '<p class="message">Redirects created.</p>';
			lShowRedirects();
		}
		else
		{
			echo '<p class="error">Creation of redirects failed.</p>';
			lShowRedirects();
			return;
		}
	}
		
	function _lmredirecttoitem_deleteall()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.htmlspecialchars($pluginURL).'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="deleteall_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Delete all redirects</h4><div>';
		echo '<p>Are you sure you want to delete all redirects?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Delete All Redirects" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}
	
	function _lmredirecttoitem_deleteall_process()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		if (requestVar('sure') == 'yes')
		{
			if($oPluginAdmin->plugin->removeRedirectAll())
			{
				echo '<p class="message">All redirects deleted.</p>';
				lShowRedirects();
			}
			else
			{
				echo '<p class="error">Deletion of all redirects failed.</p>';
				lShowRedirects();
				return;
			}
		}
		else
		{
			lShowRedirects();
		}
	}

	function _lmredirecttoitem_upgradeplugindata()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();

		$canrollback = $oPluginAdmin->plugin->upgradeDataTest($currentdataversion, $sourcedataversion);

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.htmlspecialchars($pluginURL).'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="upgradeplugindata_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Upgrade plugin data</h4><div>';
		echo '<p>Taking a database backup is recommended before performing the upgrade. ';
	
		if($canrollback)
		{
			echo 'After the upgrade is done you can choose to commit the plugin data to the new version, or rollback the plugin data to the previous version. ';
		}
		else
		{
			echo 'This upgrade of the plugin data is not reversible. ';
		}
		
		echo '</p><br /><p>Are you sure you want to upgrade the plugin data now?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Perform Upgrade" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmredirecttoitem_upgradeplugindata_process()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();

		$canrollback = $oPluginAdmin->plugin->upgradeDataTest($currentdataversion, $sourcedataversion);

		if (requestVar('sure') == 'yes' && $sourcedataversion > $currentdataversion)
		{
			if($oPluginAdmin->plugin->upgradeDataPerform($currentdataversion + 1, $sourcedataversion))
			{
				$oPluginAdmin->plugin->setCurrentDataVersion($sourcedataversion);
				
				if(!$canrollback)
				{
					$oPluginAdmin->plugin->upgradeDataCommit($currentdataversion + 1, $sourcedataversion);
					$oPluginAdmin->plugin->setCommitDataVersion($sourcedataversion);					
				}
				
				echo '<p class="message">Upgrade of plugin data was successful.</p>';
				lShowRedirects();
			}
			else
			{
				echo '<p class="error">Upgrade of plugin data was failed.</p>';
				lShowRedirects();
				return;
			}
		}
		else
		{
			lShowRedirects();
		}
	}	

	function _lmredirecttoitem_rollbackplugindata()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.htmlspecialchars($pluginURL).'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="rollbackplugindata_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Rollback plugin data upgrade</h4><div>';
		echo '<p>You may loose any plugin data added after the plugin data upgrade was performed. ';
		echo 'After the rollback is performed must you replace the plugin files with the plugin files for the previous version. ';
		echo '</p><br /><p>Are you sure you want to rollback the plugin data upgrade now?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Perform Rollback" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmredirecttoitem_rollbackplugindata_process()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();

		if (requestVar('sure') == 'yes' && $currentdataversion > $commitdataversion)
		{
			if($oPluginAdmin->plugin->upgradeDataRollback($currentdataversion, $commitdataversion + 1))
			{
				$oPluginAdmin->plugin->setCurrentDataVersion($commitdataversion);
								
				echo '<p class="message">Rollback of the plugin data upgrade was successful. You must replace the plugin files with the plugin files for the previous version before you can continue.</p>';
			}
			else
			{
				echo '<p class="error">Rollback of the plugin data upgrade failed.</p>';
				return;
			}
		}
		else
		{
			lShowRedirects();
		}
	}	
	
	function _lmredirecttoitem_commitplugindata()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$historygo = intRequestVar('historygo');
		$historygo--;
		
		echo '<div class="dialogbox">';
		echo '<form method="post" action="'.htmlspecialchars($pluginURL).'">';
		$manager->addTicketHidden();
		echo '<input type="hidden" name="action" value="commitplugindata_process" />';
		echo '<input type="hidden" name="historygo" value="'.$historygo.'" />';
		echo '<h4 class="light">Commit plugin data upgrade</h4><div>';
		echo '<p>After the commit of the plugin data upgrade is performed can you not rollback the plugin data to the previous version.</p>';
		echo '</p><br /><p>Are you sure you want to commit the plugin data now?</p>';
		echo '<p class="buttons">';
		echo '<input type="hidden" name="sure" value="yes" /">';
		echo '<input type="submit" value="Perform Commit" />';
		echo '<input type="button" name="sure" value="Cancel" onclick="history.go('.$historygo.');" />';
		echo '</p>';
		echo '</div></form></div>';
	}

	function _lmredirecttoitem_commitplugindata_process()
	{
		global $oPluginAdmin, $manager, $pluginURL;

		$sourcedataversion = $oPluginAdmin->plugin->getDataVersion();
		$commitdataversion = $oPluginAdmin->plugin->getCommitDataVersion();
		$currentdataversion = $oPluginAdmin->plugin->getCurrentDataVersion();

		if (requestVar('sure') == 'yes' && $currentdataversion > $commitdataversion)
		{
			if($oPluginAdmin->plugin->upgradeDataCommit($commitdataversion + 1, $currentdataversion))
			{
				$oPluginAdmin->plugin->setCommitDataVersion($currentdataversion);
								
				echo '<p class="message">Commit of the plugin data upgrade was successful.</p>';
				lShowRedirects();
			}
			else
			{
				echo '<p class="error">Commit of the plugin data upgrade failed.</p>';
				return;
			}
		}
		else
		{
			lShowRedirects();
		}
	}	
?>