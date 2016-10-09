<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class BlockAdvertising extends Module
{
	/* Title associated to the image */
	public $partn_title;

	/* Link associated to the image */
	public $partn_link;

	/* Name of the image without extension */
	public $partn_imgname;

	/* Image path with extension */
	public $partn_img;

	public function __construct()
	{
		$this->name = 'blockpartners';
		$this->tab = 'advertising_marketing';
		$this->version = '0.10.1';
		$this->author = 'PrestaShop';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('Partners block');
		$this->description = $this->l('Adds an parteners block to selected sections of your e-commerce website.');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');

		$this->initialize();
	}

	/*
	 * Set the properties of the module, like the link to the image and the title (contextual to the current shop context)
	 */
	protected function initialize()
	{
		$this->partn_imgname = 'partners';
		if ((Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_SHOP)
			&& file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->partn_imgname.'-g'.$this->context->shop->getContextShopGroupID().'.'.Configuration::get('BLOCKPARTN_IMG_EXT'))
		)
			$this->partn_imgname .= '-g'.$this->context->shop->getContextShopGroupID();
		if (Shop::getContext() == Shop::CONTEXT_SHOP
			&& file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->partn_imgname.'-s'.$this->context->shop->getContextShopID().'.'.Configuration::get('BLOCKPARTN_IMG_EXT'))
		)
			$this->partn_imgname .= '-s'.$this->context->shop->getContextShopID();

		// If none of them available go default
		if ($this->partn_imgname == 'partners')
			$this->partn_img = Tools::getMediaServer($this->name)._MODULE_DIR_.$this->name.'/img/fixtures/'.$this->partn_imgname.'.jpg';
		else
			$this->partn_img = Tools::getMediaServer($this->name)._MODULE_DIR_.$this->name.'/img/'.$this->partn_imgname.'.'.Configuration::get('BLOCKPARTN_IMG_EXT');
		$this->partn_link = htmlentities(Configuration::get('BLOCKPARTN_LINK'), ENT_QUOTES, 'UTF-8');
		$this->partn_title = htmlentities(Configuration::get('BLOCKPARTN_TITLE'), ENT_QUOTES, 'UTF-8');
	}

	public function install()
	{
		if (!parent::install())
			return false;

		// Hook the module either on the left or right column
		$theme = new Theme(Context::getContext()->shop->id_theme);
		if ((!$theme->default_left_column || !$this->registerHook('leftColumn'))
			&& (!$theme->default_right_column || !$this->registerHook('rightColumn'))
		)
		{
			// If there are no colums implemented by the template, throw an error and uninstall the module
			$this->_errors[] = $this->l('This module needs to be hooked to a column, but your theme does not implement one');
			parent::uninstall();

			return false;
		}

		Configuration::updateGlobalValue('BLOCKPARTN_LINK', 'http://www.prestashop.com/');
		Configuration::updateGlobalValue('BLOCKPARTN_TITLE', 'PrestaShop');
		// Try to update with the extension of the image that exists in the module directory
		foreach (scandir(_PS_MODULE_DIR_.$this->name) as $file)
			if (in_array($file, array('partners.jpg', 'partners.gif', 'partners.png')))
				Configuration::updateGlobalValue('BLOCKPARTN_IMG_EXT', substr($file, strrpos($file, '.') + 1));

		return true;
	}

	public function uninstall()
	{
		Configuration::deleteByName('BLOCKPARTN_LINK');
		Configuration::deleteByName('BLOCKPARTN_TITLE');
		Configuration::deleteByName('BLOCKPARTN_IMG_EXT');

		return (parent::uninstall());
	}

	/**
	 * delete the contextual image (it is not allowed to delete the default image)
	 *
	 * @return void
	 */
	private function _deleteCurrentImg()
	{
		// Delete the image file
		if ($this->partn_imgname != 'partners' && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->partn_imgname.'.'.Configuration::get('BLOCKPARTN_IMG_EXT')))
			unlink(_PS_MODULE_DIR_.$this->name.'/img/'.$this->partn_imgname.'.'.Configuration::get('BLOCKPARTN_IMG_EXT'));

		// Update the extension to the global value or the shop group value if available
		Configuration::deleteFromContext('BLOCKPARTN_IMG_EXT');
		Configuration::updateValue('BLOCKPARTN_IMG_EXT', Configuration::get('BLOCKPARTN_IMG_EXT'));

		// Reset the properties of the module
		$this->initialize();
	}

	public function postProcess()
	{
		if (Tools::isSubmit('submitDeleteImgConf'))
			$this->_deleteCurrentImg();

		$errors = '';
		if (Tools::isSubmit('submitAdvConf'))
		{
			if (isset($_FILES['partn_img']) && isset($_FILES['partn_img']['tmp_name']) && !empty($_FILES['partn_img']['tmp_name']))
			{
				if ($error = ImageManager::validateUpload($_FILES['partn_img'], Tools::convertBytes(ini_get('upload_max_filesize'))))
					$errors .= $error;
				else
				{
					Configuration::updateValue('BLOCKPARTN_IMG_EXT', substr($_FILES['partn_img']['name'], strrpos($_FILES['partn_img']['name'], '.') + 1));

					// Set the image name with a name contextual to the shop context
					$this->partn_imgname = 'partners';
					if (Shop::getContext() == Shop::CONTEXT_GROUP)
						$this->partn_imgname = 'partners-g'.(int)$this->context->shop->getContextShopGroupID();
					elseif (Shop::getContext() == Shop::CONTEXT_SHOP)
						$this->partn_imgname = 'partners-s'.(int)$this->context->shop->getContextShopID();

					// Copy the image in the module directory with its new name
					if (!move_uploaded_file($_FILES['partn_img']['tmp_name'], _PS_MODULE_DIR_.$this->name.'/img/'.$this->partn_imgname.'.'.Configuration::get('BLOCKPARTN_IMG_EXT')))
						$errors .= $this->l('File upload error.');
				}
			}

			// If the link is not set, then delete it in order to use the next default value (either the global value or the group value)
			if ($link = Tools::getValue('partn_link'))
				Configuration::updateValue('BLOCKPARTN_LINK', $link);
			elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP)
				Configuration::deleteFromContext('BLOCKPARTN_LINK');

			// If the title is not set, then delete it in order to use the next default value (either the global value or the group value)
			if ($title = Tools::getValue('partn_title'))
				Configuration::updateValue('BLOCKPARTN_TITLE', $title);
			elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP)
				Configuration::deleteFromContext('BLOCKPARTN_TITLE');

			// Reset the module properties
			$this->initialize();
			$this->_clearCache('blockpartners.tpl');

			if (!$errors)
				Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&conf=6');
			echo $this->displayError($errors);
		}

	}

	/**
	 * getContent used to display admin module form
	 *
	 * @return string content
	 */
	public function getContent()
	{
		$this->postProcess();

		return $this->renderForm();
	}

	public function hookRightColumn($params)
	{
		if (!$this->isCached('blockpartners.tpl', $this->getCacheId()))
			$this->smarty->assign(
				array(
					'image' => $this->context->link->protocol_content.$this->partn_img,
					'partn_link' => $this->partn_link,
					'partn_title' => $this->partn_title,
				)
			);

		return $this->display(__FILE__, 'blockpartners.tpl', $this->getCacheId());
	}

	public function hookLeftColumn($params)
	{
		return $this->hookRightColumn($params);
	}

	public function hookHeader($params)
	{
		$this->context->controller->addCSS($this->_path.'blockpartners.css', 'all');
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Configuration'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'file',
						'label' => $this->l('Image for the advertisement'),
						'name' => 'partn_img',
						'desc' => $this->l('By default the image will appear in the left column. The recommended dimensions are 155 x 163px.'),
						'thumb' => $this->context->link->protocol_content.$this->partn_img,
					),
					array(
						'type' => 'text',
						'label' => $this->l('Target link for the image'),
						'name' => 'partn_link',
					),
					array(
						'type' => 'text',
						'label' => $this->l('Title of the target link'),
						'name' => 'partn_title',
						'desc' => $this->l('This title will be displayed when you mouse over the advertisement block in your shop.')
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitAdvConf';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'partn_link' => Tools::getValue('partn_link', Configuration::get('BLOCKPARTN_LINK')),
			'partn_title' => Tools::getValue('partn_title', Configuration::get('BLOCKPARTN_TITLE')),
		);
	}
}
