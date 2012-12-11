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

		$layout   = sly_Core::getLayout();
		$page     = $layout->getNavigation()->find('imageresize');
		$subpages = $page->getSubpages();

		foreach ($subpages as $key => $subpage) {
			$subpages[$key] = array($subpage->getPageParam(), $subpage->getTitle());
		}

		$layout->addCSSFile('../data/dyn/public/sallycms/image-resize/backend.less');
		$layout->pageHeader(t('iresize_image_resize'), $subpages);
	}

	public function indexAction() {
		$this->init();
		$this->render('index.phtml', array(), false);
	}

	public function updateAction() {
		$this->init();

		$max_cachefiles   = sly_request('max_cachefiles',      'int');
		$max_filters      = sly_request('max_filters',         'int');
		$max_resizekb     = sly_request('max_resizekb',        'int');
		$max_resizepixel  = sly_request('max_resizepixel',     'int');
		$jpg_quality      = min(abs(sly_request('jpg_quality', 'int')), 100);
		$upscalingAllowed = sly_request('upscaling_allowed',   'boolean');
		$recompress       = sly_request('recompress',          'boolean');

		$service = sly_Service_Factory::getAddOnService();
		$name    = 'sallycms/image-resize';

		$service->setProperty($name, 'max_cachefiles',    $max_cachefiles);
		$service->setProperty($name, 'max_filters',       $max_filters);
		$service->setProperty($name, 'max_resizekb',      $max_resizekb);
		$service->setProperty($name, 'max_resizepixel',   $max_resizepixel);
		$service->setProperty($name, 'jpg_quality',       $jpg_quality);
		$service->setProperty($name, 'upscaling_allowed', $upscalingAllowed);
		$service->setProperty($name, 'recompress',        $recompress);

		print sly_Helper_Message::info(t('iresize_config_saved'));
		$this->indexAction();
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('pages', 'imageresize'));
	}

	protected function getViewFolder() {
		return dirname(__FILE__).'/../../../views/';
	}
}
