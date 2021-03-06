<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImageResize;

use sly\Assets\App;

/**
 * @author Robert
 */
class Listeners implements \sly_ContainerAwareInterface {
	protected $container;

	public function setContainer(\sly_Container $container = null) {
		$this->container = $container;
	}

	/**
	 * SLY_ASSETS_ROUTER
	 *
	 * @param sly_Router_Base $router
	 */
	public function assetsRouter($router) {
		// add our custom routes

		$router->prependRoute(
			'/mediapool/resize/(?P<file>.+?)', // this will overrule the core asset rule
			array(App::CONTROLLER_PARAM => 'imageresize', App::ACTION_PARAM => 'resize')
		);

		$router->prependRoute(
			'/imageresize/(?P<file>.+?)',
			array(App::CONTROLLER_PARAM => 'imageresize', App::ACTION_PARAM => 'resize')
		);

		return $router;
	}

	/**
	 * SLY_SYSTEM_CACHES
	 *
	 * @param sly_Form_Select_Base $select  list of caches
	 */
	public function systemCacheList(\sly_Form_Select_Base $select) {
		$name       = 'sallycms/image-resize';
		$selected   = $select->getValue();
		$selected[] = $name;

		$select->addValue($name, t('iresize_resized_images'));
		$select->setSelected($selected);

		return $select;
	}

	/**
	 * SLY_CACHE_CLEARED
	 */
	public function cacheCleared() {
		$app      = $this->container['sly-app'];
		$isSystem = $app->getCurrentControllerName() === 'system';

		// do nothing if requested
		if ($isSystem) {
			$controller = $app->getCurrentController();

			if (!$controller->isCacheSelected('sallycms/image-resize')) {
				return;
			}
		}

		$this->container['sly-imageresize-service']->flushCache();
	}

	/**
	 * SLY_BACKEND_NAVIGATION_INIT
	 */
	public function backendNavigation(\sly_Layout_Navigation_Backend $nav) {
		$user = $this->container['sly-service-user']->getCurrentUser();

		if ($user !== null && ($user->isAdmin() || $user->hasPermission('pages', 'imageresize'))) {
			$group = $nav->getGroup('addons');
			$page  = $nav->addPage($group, 'imageresize', t('iresize_image_resize'));

			$page->addSubpage('imageresize',            t('iresize_subpage_config'));
			$page->addSubpage('imageresize_clearcache', t('iresize_subpage_clear_cache'));
		}
	}

	/**
	 * SLY_MODEL_MEDIUM_RESIZE
	 */
	public function resizeMedium($result, array $params) {
		// someone has already done the job
		if (is_string($result)) {
			return $result;
		}

		// signature is $medium->resize($options, $path)

		$medium  = $params['object'];
		$options = array_key_exists(0, $params['arguments']) ? $params['arguments'][0] : array();
		$path    = array_key_exists(1, $params['arguments']) ? $params['arguments'][1] : 'rel';

		return Util::resize($medium, $options, $path, $path);
	}

	public function mediapoolThumbnail($tag, array $params) {
		$medium  = $params['medium'];
		$width   = $params['width'];
		$height  = $params['height'];
		$isImage = $params['isImage'];

		if (!$medium->exists() || !$isImage) {
			return $tag;
		}

		$filename = Filename::fromMedium($medium, true);
		$filename->addResize($width.'w');
		$filename->addResize($height.'h');

		$uri = '../'.$filename->getUri();
		$tag = preg_replace('/(?<=\b)src="[^"]+"/', 'src="'.$uri.'"', $tag);

		return $tag;
	}

	/**
	 * SLY_ASSET_PROCESS
	 */
	public function processAsset($inputFile, array $params) {
		error_reporting(E_ALL);

		$filename  = $params['filename'];
		$service   = $this->container['sly-imageresize-service'];
		$tmpDir    = $service->getCacheDir();
		$cacheFile = $tmpDir.'/tmp_'.sha1($filename->getVirtualFilename()).'.'.$filename->getExtension();

		// process the image if no cachefile exists
		
		if (!file_exists($cacheFile)) {
			$config      = $service->getConfig(null);
			$jsonService = $this->container['sly-service-json'];
			$controlFile = $service->getControlFile($filename->getFilename());
			$controlData = file_exists($controlFile) ? $jsonService->load($controlFile, false, true) : array();
			$thumbnail   = $filename->getThumbnail($config, $inputFile);
			$cacheFile   = $thumbnail->generate($cacheFile);
			
			// remove old cache files
			$controlData = $this->removeOutdatedCacheFiles($controlData, $config['max_cachefiles'], $tmpDir);
			
			// remember the new file so we can kill it in some next request
			// make sure the tmp file is added/moved to the last spot in the list,
			// as it serves as a freshness queue.

			$controlData = $this->addCacheFile($controlData, $cacheFile);
			$jsonService->dump($controlFile, $controlData);
		}

		return $cacheFile;
	}

	protected function removeOutdatedCacheFiles(array $controlData, $maxFiles, $tmpDir) {
		if (count($controlData) >= $maxFiles) {
			$maxFiles = $maxFiles - 1; // -1 to make room for the requested file's cache file
			$oldest   = array_slice($controlData, 0, -$maxFiles);

			foreach ($oldest as $basename) {
				$cacheFile = $tmpDir.'/'.$basename;

				if (file_exists($cacheFile)) {
					@unlink($cacheFile);
				}
			}

			$controlData = array_slice($controlData, -$maxFiles);
		}

		return $controlData;
	}

	protected function addCacheFile(array $controlData, $cacheFile) {
		$cacheFile = basename($cacheFile);
		$idx       = array_search($cacheFile, $controlData);

		if ($idx !== false) {
			unset($controlData[$idx]);
			$controlData = array_values($controlData);
		}

		$controlData[] = basename($cacheFile);

		return $controlData;
	}
}
