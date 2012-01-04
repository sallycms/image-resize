<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Imageresize extends sly_Controller_Backend implements sly_Controller_Interface {
	private $init = false;

	protected function init() {
		if ($this->init) return;
		$this->init = true;

		sly_Core::getI18N()->appendFile(dirname(__FILE__).'/lang');

		$subpages = array(
			array('imageresize',            t('iresize_subpage_config')),
			array('imageresize_clearcache', t('iresize_subpage_clear_cache')),
		);

		$page   = sly_Core::getNavigation()->find('image_resize', 'addon');
		$layout = sly_Core::getLayout();

		$page->addSubpages($subpages);

		$layout->addCSSFile('../data/dyn/public/image_resize/backend.css');
		$layout->pageHeader(t('iresize_image_resize'), $subpages);
	}

	public function indexAction() {
		$this->init();
		print $this->render('index.phtml');
	}

	public function updateAction() {
		$this->init();

		$max_cachefiles   = sly_request('max_cachefiles',      'int');
		$max_filters      = sly_request('max_filters',         'int');
		$max_resizekb     = sly_request('max_resizekb',        'int');
		$max_resizepixel  = sly_request('max_resizepixel',     'int');
		$jpg_quality      = min(abs(sly_request('jpg_quality', 'int')), 100);
		$upscalingAllowed = sly_request('upscaling_allowed',   'boolean');
		$nocompress       = sly_request('nocompress',          'boolean');

		$service = sly_Service_Factory::getAddOnService();

		$service->setProperty('image_resize', 'max_cachefiles',    $max_cachefiles);
		$service->setProperty('image_resize', 'max_filters',       $max_filters);
		$service->setProperty('image_resize', 'max_resizekb',      $max_resizekb);
		$service->setProperty('image_resize', 'max_resizepixel',   $max_resizepixel);
		$service->setProperty('image_resize', 'jpg_quality',       $jpg_quality);
		$service->setProperty('image_resize', 'upscaling_allowed', $upscalingAllowed);
		$service->setProperty('image_resize', 'nocompress',        $nocompress);

		print sly_Helper_Message::info(t('iresize_config_saved'));
		$this->indexAction();
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('image_resize[]'));
	}

	protected function getViewFolder() {
		return SLY_ADDONFOLDER.'/image_resize/views/';
	}
}
