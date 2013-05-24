<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Imageresize extends sly_Controller_Backend implements sly_Controller_Interface {
	public function indexAction() {
		$name      = 'sallycms/image-resize';
		$container = $this->getContainer();
		$layout    = $container['sly-layout'];
		$service   = $container['sly-service-addon'];
		$config    = $container['sly-imageresize-service']->getConfig(null);
		$version   = $service->getPackageService()->getVersion($name);

		$layout->addCSSFile(sly\Assets\Util::addOnUri($name, 'backend.less'));
		$layout->pageHeader(t('iresize_image_resize'));

		print sly_Helper_Message::renderFlashMessage($container['sly-flash-message']);

		$this->render('index.phtml', array(
			'version' => $version,
			'config'  => $config
		), false);
	}

	public function updateAction() {
		sly_Util_Csrf::checkToken();

		$request          = $this->getRequest();
		$max_cachefiles   = $request->post('max_cachefiles',    'int');
		$max_filters      = $request->post('max_filters',       'int');
		$max_resizekb     = $request->post('max_resizekb',      'int');
		$max_resizepixel  = $request->post('max_resizepixel',   'int');
		$jpg_quality      = $request->post('jpg_quality',       'int');
		$upscalingAllowed = $request->post('upscaling_allowed', 'boolean');
		$recompress       = $request->post('recompress',        'boolean');

		$container   = $this->getContainer();
		$jpg_quality = min(abs($jpg_quality), 100);
		$service     = $container['sly-service-addon'];
		$name        = 'sallycms/image-resize';

		$service->setProperty($name, 'max_cachefiles',    $max_cachefiles);
		$service->setProperty($name, 'max_filters',       $max_filters);
		$service->setProperty($name, 'max_resizekb',      $max_resizekb);
		$service->setProperty($name, 'max_resizepixel',   $max_resizepixel);
		$service->setProperty($name, 'jpg_quality',       $jpg_quality);
		$service->setProperty($name, 'upscaling_allowed', $upscalingAllowed);
		$service->setProperty($name, 'recompress',        $recompress);

		$container['sly-flash-message']->appendInfo(t('iresize_config_saved'));

		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		$user = $this->getContainer()->get('sly-service-user')->getCurrentUser();

		return $user && ($user->isAdmin() || $user->hasRight('pages', 'imageresize'));
	}

	protected function getViewFolder() {
		return __DIR__.'/../../../views/';
	}
}
