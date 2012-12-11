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
	/**
	 * @deprecated  since 3.2, will be removed in 4.0
	 */
	protected function init() {
		$layout = sly_Core::getLayout();
		$layout->addCSSFile('../data/dyn/public/sallycms/image-resize/backend.less');
		$layout->pageHeader(t('iresize_image_resize'));
	}

	public function indexAction() {
		$this->init();
		$this->render('index.phtml', array(), false);
	}

	public function updateAction() {
		if (sly_Core::getVersion('X.Y') !== '0.7') {
			sly_Util_Csrf::checkToken();
		}

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

		sly_Core::getFlashMessage()->appendInfo(t('iresize_config_saved'));
		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('pages', 'imageresize'));
	}

	protected function getViewFolder() {
		return dirname(__FILE__).'/../../../views/';
	}
}
