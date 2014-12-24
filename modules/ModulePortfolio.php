<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2013 Leo Feyer
 *
 * @package   portfolio
 * @author    Hamid Abbaszadeh
 * @license   GNU/LGPL
 * @copyright 2014
 */


/**
 * Namespace
 */
namespace portfolio;


/**
 * Class ModulePortfolio
 *
 * @copyright  2014
 * @author     Hamid Abbaszadeh
 * @package    Devtools
 */
abstract class ModulePortfolio extends \Module
{

	/**
	 * URL cache array
	 * @var array
	 */
	private static $arrUrlCache = array();

	/**
	 * Sort out protected archives
	 * @param array
	 * @return array
	 */
	protected function sortOutProtected($arrCategories)
	{
		if (BE_USER_LOGGED_IN || !is_array($arrCategories) || empty($arrCategories))
		{
			return $arrCategories;
		}

		$this->import('FrontendUser', 'User');
		$objCategory = \PortfolioModel::findMultipleByIds($arrCategories);
		$arrCategories = array();

		if ($objCategory !== null)
		{
			while ($objCategory->next())
			{
				if ($objCategory->protected)
				{
					if (!FE_USER_LOGGED_IN)
					{
						continue;
					}

					$groups = deserialize($objCategory->groups);

					if (!is_array($groups) || empty($groups) || !count(array_intersect($groups, $this->User->groups)))
					{
						continue;
					}
				}

				$arrCategories[] = $objCategory->id;
			}
		}

		return $arrCategories;
	}


	/**
	 * Parse one or more items and return them as array
	 * @param object
	 * @param boolean
	 * @return array
	 */
	protected function parseClients($objClients, $blnAddCategory=false)
	{
		$limit = $objClients->count();

		if ($limit < 1)
		{
			return array();
		}

		$count = 0;
		$arrClients = array();

		while ($objClients->next())
		{
			$arrClients[] = $this->parseClient($objClients, $blnAddCategory, ((++$count == 1) ? ' first' : '') . (($count == $limit) ? ' last' : '') . ((($count % 2) == 0) ? ' odd' : ' even'), $count);
		}

		return $arrClients;
	}

	/**
	 * Parse one or more items and return them as array
	 * @param object
	 * @param boolean
	 * @return array
	 */
	protected function parseProjects($objProjects, $blnAddCategory=false)
	{
		$limit = $objProjects->count();

		if ($limit < 1)
		{
			return array();
		}

		$count = 0;
		$arrProjects = array();

		while ($objProjects->next())
		{
			$arrProjects[] = $this->parseProject($objProjects, $blnAddCategory, ((++$count == 1) ? ' first' : '') . (($count == $limit) ? ' last' : '') . ((($count % 2) == 0) ? ' odd' : ' even'), $count);
		}

		return $arrProjects;
	}


	/**
	 * Parse an item and return it as string
	 * @param object
	 * @param boolean
	 * @param string
	 * @param integer
	 * @return string
	 */
	protected function parseClient($objClient, $blnAddCategory=false, $strClass='', $intCount=0)
	{
		global $objPage;

		$objTemplate = new \FrontendTemplate($this->client_template);
		$objTemplate->setData($objClient->row());

		$objTemplate->class = (($this->setClass != '') ? ' ' . $this->setClass : '') . $strClass;
		$objTemplate->class = (($this->client_class != '') ? ' ' . $this->client_class : '') . $strClass;

		$objTemplate->title       = $objClient->title;
		$objTemplate->link        = $objClient->link;
		$objTemplate->description = $objClient->description;

		$objTemplate->url         = $this->generateClientUrl($objClient, $blnAddCategory);
		$objTemplate->more        = $this->generateClientLink($GLOBALS['TL_LANG']['MSC']['moredetail'], $objClient, $blnAddCategory, true);

		$objTemplate->category    = $objClient->getRelated('pid');

		$objTemplate->count = $intCount; // see #5708

		$objTemplate->addImage = false;

		// Add an image
		if ($objClient->singleSRC != '')
		{
			$objModel = \FilesModel::findByUuid($objClient->singleSRC);

			if ($objModel === null)
			{
				if (!\Validator::isUuid($objClient->singleSRC))
				{
					$objTemplate->text = '<p class="error">'.$GLOBALS['TL_LANG']['ERR']['version2format'].'</p>';
				}
			}
			elseif (is_file(TL_ROOT . '/' . $objModel->path))
			{
				// Do not override the field now that we have a model registry (see #6303)
				$arrClient = $objClient->row();

				// Override the default image size
				if ($this->client_imgSize != '')
				{
					$size = deserialize($this->client_imgSize);

					if ($size[0] > 0 || $size[1] > 0)
					{
						$arrClient['size'] = $this->imgSize;
					}
				}

				$arrClient['singleSRC'] = $objModel->path;
				$this->addImageToTemplate($objTemplate, $arrClient);
			}
		}

		return $objTemplate->parse();
	}

	/**
	 * Parse an item and return it as string
	 * @param object
	 * @param boolean
	 * @param string
	 * @param integer
	 * @return string
	 */
	protected function parseProject($objProject, $blnAddCategory=false, $strClass='', $intCount=0)
	{
		global $objPage;

		$objTemplate = new \FrontendTemplate($this->project_template);
		$objTemplate->setData($objProject->row());

		$objTemplate->class = (($this->project_class != '') ? ' ' . $this->project_class : '') . $strClass;
		$objTemplate->project_class = $this->project_class;

		$objTemplate->title       = $objProject->title;
		$objTemplate->date        = $objProject->date;
		$objTemplate->link        = $objProject->link;
		$objTemplate->duration    = $objProject->duration;
		$objTemplate->status      = $objProject->status;
		$objTemplate->description = $objProject->description;

		$objTemplate->url         = $this->generateProjectUrl($objProject, $blnAddCategory);
		$objTemplate->more        = $this->generateProjectLink($GLOBALS['TL_LANG']['MSC']['moredetail'], $objProject, $blnAddCategory, true);

		$objTemplate->category    = $objProject->getRelated('pid');

		$objTemplate->count = $intCount; // see #5708
		$objTemplate->text = '';

		$objTemplate->addImage = false;

		// Add an image
		if ($objProject->singleSRC != '')
		{
			$objModel = \FilesModel::findByUuid($objProject->singleSRC);

			if ($objModel === null)
			{
				if (!\Validator::isUuid($objProject->singleSRC))
				{
					$objTemplate->text = '<p class="error">'.$GLOBALS['TL_LANG']['ERR']['version2format'].'</p>';
				}
			}
			elseif (is_file(TL_ROOT . '/' . $objModel->path))
			{
				// Do not override the field now that we have a model registry (see #6303)
				$arrProject = $objProject->row();

				// Override the default image size
				if ($this->project_imgSize != '')
				{
					$size = deserialize($this->project_imgSize);

					if ($size[0] > 0 || $size[1] > 0)
					{
						$arrProject['size'] = $this->project_imgSize;
					}
				}

				$arrProject['singleSRC'] = $objModel->path;
				$this->addImageToTemplate($objTemplate, $arrProject);
			}
		}

		return $objTemplate->parse();
	}


	/**
	 * Generate a URL and return it as string
	 * @param object
	 * @param boolean
	 * @return string
	 */
	protected function generateClientUrl($objItem, $blnAddCategory=false)
	{
		$strCacheKey = 'id_' . $objItem->id;

		// Load the URL from cache
		if (isset(self::$arrUrlCache[$strCacheKey]))
		{
			return self::$arrUrlCache[$strCacheKey];
		}

		// Initialize the cache
		self::$arrUrlCache[$strCacheKey] = null;

		// Link to the default page
		if (self::$arrUrlCache[$strCacheKey] === null)
		{
			$objPage = \PageModel::findByPk($objItem->getRelated('pid')->jumpToClient);

			if ($objPage === null)
			{
				self::$arrUrlCache[$strCacheKey] = ampersand(\Environment::get('request'), true);
			}
			else
			{
				self::$arrUrlCache[$strCacheKey] = ampersand($this->generateFrontendUrl($objPage->row(), ((\Config::get('useAutoItem') && !\Config::get('disableAlias')) ?  '/' : '/items/') . ((!\Config::get('disableAlias') && $objItem->alias != '') ? $objItem->alias : $objItem->id)));
			}

		}

		return self::$arrUrlCache[$strCacheKey];
	}

	/**
	 * Generate a link and return it as string
	 * @param string
	 * @param object
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function generateClientLink($strLink, $objClient, $blnAddCategory=false, $blnIsReadMore=false)
	{

		return sprintf('<a href="%s" title="%s">%s%s</a>',
						$this->generateClientUrl($objClient, $blnAddCategory),
						specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $objClient->title), true),
						$strLink,
						($blnIsReadMore ? ' <span class="invisible">'.$objClient->title.'</span>' : ''));

	}

	/**
	 * Generate a URL and return it as string
	 * @param object
	 * @param boolean
	 * @return string
	 */
	protected function generateProjectUrl($objItem, $blnAddCategory=false)
	{
		$strCacheKey = 'id_' . $objItem->id;

		// Load the URL from cache
		if (isset(self::$arrUrlCache[$strCacheKey]))
		{
			return self::$arrUrlCache[$strCacheKey];
		}

		// Initialize the cache
		self::$arrUrlCache[$strCacheKey] = null;

		// Link to the default page
		if (self::$arrUrlCache[$strCacheKey] === null)
		{
			$objPage = \PageModel::findByPk($objItem->getRelated('pid')->jumpToProject);

			if ($objPage === null)
			{
				self::$arrUrlCache[$strCacheKey] = ampersand(\Environment::get('request'), true);
			}
			else
			{
				self::$arrUrlCache[$strCacheKey] = ampersand($this->generateFrontendUrl($objPage->row(), ((\Config::get('useAutoItem') && !\Config::get('disableAlias')) ?  '/' : '/items/') . ((!\Config::get('disableAlias') && $objItem->alias != '') ? $objItem->alias : $objItem->id)));
			}

		}

		return self::$arrUrlCache[$strCacheKey];
	}

	/**
	 * Generate a link and return it as string
	 * @param string
	 * @param object
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function generateProjectLink($strLink, $objProject, $blnAddCategory=false, $blnIsReadMore=false)
	{

		return sprintf('<a href="%s" title="%s">%s%s</a>',
						$this->generateProjectUrl($objProject, $blnAddCategory),
						specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $objProject->title), true),
						$strLink,
						($blnIsReadMore ? ' <span class="invisible">'.$objProject->title.'</span>' : ''));

	}

}
