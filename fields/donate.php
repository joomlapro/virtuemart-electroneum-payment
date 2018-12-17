<?php

defined ('_JEXEC') or die('Restricted access');
/**
** Parts of this code is written by joomlapro.com Copyright (c) 2012, 2015 All Right Reserved.
** Many part of this code is from VirtueMart Team Copyright (c) 2004 - 2015. All rights reserved.
** Some parts might even be Joomla and is Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved. 
** http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
** This source is free software. This version may have been modified pursuant
** to the GNU General Public License, and as distributed it includes or
** is derivative of works licensed under the GNU General Public License or
** other free or open source software licenses.
**
** THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT             WARRANTY OF ANY  
** KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
** IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
** PARTICULAR PURPOSE.

** <author>JoomlaPro / Virtuemart team</author>
** <email>info@joomlapro.com</email>
** <date>2017 NOV</date>
*/

jimport('joomla.form.formfield');

class JFormFieldDonate extends JFormField {

    protected $type = 'donate';

    // getLabel() left out 
	
	   public function getLabel() {
		   
	    return '';
	    return '<label id="params_donation-lbl"  for="donation" class="hasPopover" title="" data-content="Donation" data-original-title="Donation">
	Please donate to the creators and maintainers of this Electroneum Donate plugin by Joomlapro.com.</label>';
	   }
    
 
	

    public function getInput() 
	{
		 
		 $params = $this->form->getData();
	     $allparams = $params->get('params');
		 
		    $download_id = $allparams->download_key;
			if($download_id != "")
			{
				$db = JFactory::getDbo();
				$query = $db->getQuery(true)
				->select(array('e.extension_id', 'e.type', 'e.name', 'e.manifest_cache', 'us.update_site_id', 'us.enabled', 'us.extra_query'))
				->from($db->quoteName('#__extensions', 'e'))
				->join('LEFT OUTER', $db->quoteName('#__update_sites_extensions', 'use') . ' ON (' . $db->quoteName('e.extension_id') . ' = ' .
					$db->quoteName('use.extension_id') . ')')
				->join('LEFT OUTER', $db->quoteName('#__update_sites', 'us') . ' ON (' . $db->quoteName('us.update_site_id') . ' = ' .
					$db->quoteName('use.update_site_id') . ')')
				->where($db->quoteName('element') . ' = ' . $db->quote('electroneum'));
				
				
	
				$db->setQuery($query);
				$component = $db->LoadObject();
				
				
				
				$extension                 = new stdClass;
				$extension->update_site_id = $component->update_site_id;
				$extension->name           = $component->name;
				$extension->type           = 'extension';
		
				// Link to the PRO version updater XML:
				$extension->location = 'https://joomlapro.com/index.php?option=com_rdsubs&view=updater&cat=20&type=3&format=xml';
				$extension->enabled              = 1;
				$extension->last_check_timestamp = 0;
				$extension->extra_query          = 'key=' . $download_id;
				
		
				if ($component->update_site_id)
				{
					// Update the object
					JFactory::getDbo()->updateObject('#__update_sites', $extension, 'update_site_id');
		
					
		
				}
		}
	   ?>
      
	   <?php
	    $url = "https://joomlapro.com/donate";
	    JHTML::_('behavior.modal');
	  
	    $html[] = '<p id="donatep">Please donate to the creators and maintainers of this Electroneum Donate plugin by Joomlapro.com.</p>';
	    $html[] = '<div class="" style="margin-right:50px;"><a id="activebtn" style="width:163px;" class="modal btn btn-success span12" href="'.$url.'" rel="{handler: \'iframe\', size: {x: 500, y: 450}}">Donate</a></div>';
	    $html[] = '<div style="clear:both; margin-bottom:10px;"></div>';
	    $html[] = '<p>If problem please go to <a href="https://joomlapro.com/donate" target="_blank">https://joomlapro.com/donate</a></p><hr />';
	   
	   ?>
       <script type="text/javascript">
	    jQuery(document).ready(function(e) {
           jQuery("#donatep").parent("div").addClass('firstdiv');
        });
	   </script>
       <style type="text/css">
	   .firstdiv
	   {
		   margin-left:0px;
		   width:100%;

	   }
	   #adminForm
	   {
		  padding:10px;
	   }
	   .control-group
	   {
		   width:100%;
	   }
	   </style>
       <?php
	  return implode("\n", $html);
        
    }
}
?>