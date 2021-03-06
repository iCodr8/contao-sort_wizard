<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2014 Leo Feyer
 *
 * @package    SortWizard
 * @author     Daniel Kiesel <daniel@craffft.de>
 * @license    LGPL
 * @copyright  Daniel Kiesel 2012-2014
 */


/**
 * Namespace
 */
namespace SortWizard;


/**
 * Class SortWizard
 *
 * @copyright  Daniel Kiesel 2012-2014
 * @author     Daniel Kiesel <daniel@craffft.de>
 * @package    SortWizard
 */
class SortWizard extends \Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget_chk';

	/**
	 * Options
	 * @var array
	 */
	protected $arrOptions = array();


	/**
	 * Add specific attributes
	 * @param string
	 * @param mixed
	 */
	public function __set($strKey, $varValue)
	{
		switch ($strKey)
		{
			case 'options':
				$this->arrOptions = deserialize($varValue);
				break;

			default:
				parent::__set($strKey, $varValue);
				break;
		}
	}


	/**
	 * Check for a valid option (see #4383)
	 */
	public function validate()
	{
		$varValue = deserialize($this->getPost($this->strName));

		if ($varValue != '' && !$this->isValidOption($varValue))
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalid'], $varValue));
		}

		parent::validate();
	}


	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		$arrButtons = array('up', 'down');
		$strCommand = 'cmd_' . $this->strField;

		// Add JavaScript and css
		if (TL_MODE == 'BE')
		{
			$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/sortwizard/assets/js/sortwizard.min.js';
		    $GLOBALS['TL_CSS'][] = 'system/modules/sortwizard/assets/css/sortwizard.min.css|screen';
		}

		// Use only multiple
		$this->multiple = true;

		if (!is_array($this->varValue))
		{
			$this->varValue = array($this->varValue);
		}

		// Change the order
		if (\Input::get($strCommand) && is_numeric(\Input::get('cid')) && \Input::get('id') == $this->currentRecord)
		{
			$this->import('Database');

			switch (\Input::get($strCommand))
			{
				case 'up':
					$this->varValue = array_move_up($this->varValue, \Input::get('cid'));
					break;

				case 'down':
					$this->varValue = array_move_down($this->varValue, \Input::get('cid'));
					break;
			}

			$this->Database->prepare("UPDATE " . $this->strTable . " SET " . $this->strField . "=? WHERE id=?")
						   ->execute(serialize($this->varValue), $this->currentRecord);

			$this->redirect(preg_replace('/&(amp;)?cid=[^&]*/i', '', preg_replace('/&(amp;)?' . preg_quote($strCommand, '/') . '=[^&]*/i', '', \Environment::get('request'))));
		}

		// Sort options
		if ($this->varValue)
		{
			$arrOptions = array();
			$arrTemp = $this->arrOptions;

			// Move selected and sorted options to the top
			foreach ($this->arrOptions as $i=>$arrOption)
			{
				if (($intPos = array_search($arrOption['value'], $this->varValue)) !== false)
				{
					$arrOptions[$intPos] = $arrOption;
					unset($arrTemp[$i]);
				}
			}

			ksort($arrOptions);
			$this->arrOptions = array_merge($arrOptions, $arrTemp);
		}

		$arrOptions = array();

		// Generate options and add buttons
		foreach ($this->arrOptions as $i=>$arrOption)
		{
			$strButtons = \Image::getHtml('drag.gif', '', 'class="drag-handle" title="' . sprintf($GLOBALS['TL_LANG']['MSC']['move']) . '"');

			foreach ($arrButtons as $strButton)
			{
				$strButtons .= '<a href="'.$this->addToUrl('&amp;'.$strCommand.'='.$strButton.'&amp;cid='.$i.'&amp;id='.$this->currentRecord).'" class="button-move" title="'.\StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['move_'.$strButton][1]).'" onclick="Backend.SortWizard(this,\''.$strButton.'\',\'ctrl_'.$this->strId.'\');return false">'.\Image::getHtml($strButton.'.gif', $GLOBALS['TL_LANG']['MSC']['move_'.$strButton][0], 'class="tl_sortwizard_img"').'</a> ';
			}

			$arrOptions[] = $this->generateSortfield($arrOption, $i, $strButtons);
		}

		// Add a "no entries found" message if there are no options
		if (empty($arrOptions))
		{
			$arrOptions[]= '<p class="tl_noopt">'.$GLOBALS['TL_LANG']['MSC']['noResult'].'</p>';
		}

        return sprintf('<div id="ctrl_%s" class="tl_sortwizard_container tl_sortwizard%s"><h3><label>%s%s%s%s</label>%s</h3><input type="hidden" name="%s" value=""><div class="sortable">%s</div></div>%s',
        				$this->strId,
						(($this->strClass != '') ? ' ' . $this->strClass : ''),
						($this->required ? '<span class="invisible">'.$GLOBALS['TL_LANG']['MSC']['mandatory'].'</span> ' : ''),
						$this->strLabel,
						($this->required ? '<span class="mandatory">*</span>' : ''),
						$this->xlabel,
						($this->reloadButton ? '<a href="javascript: Backend.autoSubmit('."'".$this->strTable."'".');" class="sortwizard-update-list-button" title="'.$GLOBALS['TL_LANG']['MSC']['sortwizard_update_list_button'].'"><img src="system/modules/sortwizard/images/reload.gif" width="16" height="16" alt="'.$GLOBALS['TL_LANG']['MSC']['sortwizard_update_list_button'].'" style="vertical-align:text-bottom"></a>' : ''),
						$this->strName,
						implode('', $arrOptions),
						$this->wizard);
	}


	/**
	 * generateSortfield function.
	 *
	 * @access protected
	 * @param array $arrOption
	 * @param int $i
	 * @param string $strButtons
	 * @return string
	 */
	protected function generateSortfield($arrOption, $i, $strButtons)
	{
		return sprintf('<span><input type="hidden" name="%s" id="opt_%s" value="%s"%s%s onfocus="Backend.getScrollOffset()"> %s<label for="opt_%s">%s</label></span>',
						$this->strName . ($this->multiple ? '[]' : ''),
						$this->strId.'_'.$i,
						($this->multiple ? \StringUtil::specialchars($arrOption['value']) : 1),
						((is_array($this->varValue) && in_array($arrOption['value'], $this->varValue) || $this->varValue == $arrOption['value']) ? ' checked="checked"' : ''),
						$this->getAttributes(),
						$strButtons,
						$this->strId.'_'.$i,
						$arrOption['label']);
	}
}
