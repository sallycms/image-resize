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
	 * SLY_ARTICLE_OUTPUT
	 *
	 * @param string $content
	 */
	public function articleOutput($content) {
		preg_match_all('#<img [^\>]*src="((data/)?mediapool/([^"]+))[^\>]*>#is', $content, $matches, PREG_SET_ORDER);
		if (!$matches) return $content;

		$mediumService = $this->container['sly-service-medium'];

		foreach ($matches as $match) {
			$tag      = $match[0];
			$uri      = $match[1];
			$filename = basename(urldecode($match[3]));

			// determine width from attribute or CSS style
			preg_match('/\bwidth="(.+?)"/is', $tag, $width);
			if (!$width) preg_match('/\bwidth: ?(.+?)px/is', $tag, $width);

			if ($width && $mediumService->fileExists($filename)) {
				$medium = $mediumService->findByFilename($filename);

				// this *should* never happen...
				if (!$medium) {
					continue;
				}

				$htmlWidth = (int) $width[1];
				$realWidth = $medium->getWidth();

				if ($realWidth != $htmlWidth) {
					$newSrc  = 'mediapool/resize/'.$htmlWidth.'w__'.urlencode($filename);
					$content = str_replace($uri, $newSrc, $content);
				}
			}
		}

		return $content;
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
		$options = $params['arguments'][0];
		$path    = array_key_exists(1, $medium['arguments']) ? $medium['arguments'][1] : null;
		$request = $this->container['sly-request'];

		return Util::resize($medium, $options, $path, $request);
	}

	/**
	 * SLY_ASSET_PROCESS
	 */
	public function processAsset($inputFile, array $params) {
		error_reporting(E_ALL);

		$filename    = $params['filename'];
		$service     = $this->container['sly-imageresize-service'];
		$jsonService = $this->container['sly-service-json'];
		$controlFile = $service->getControlFile($filename->getFilename());
		$controlData = file_exists($controlFile) ? $jsonService->load($controlFile, false, true) : array();

		// prepare the thumbnail

		$config    = $service->getConfig(null);
		$thumbnail = $filename->getThumbnail($config, $inputFile);
		$realName  = $filename->getFilename();

		// remove old cache files

		$tmpDir      = $service->getCacheDir();
		$controlData = $this->removeOutdatedCacheFiles($controlData, $config, $tmpDir);

		// process the image

		$tmpFile = $tmpDir.'/tmp_'.sha1($filename->getVirtualFilename()).'.'.$filename->getExtension();
		$tmpFile = $thumbnail->generate($tmpFile);

		// remember the new file so we can kill it in some next request
		// make sure the tmp file is added/moved to the last spot in the list,
		// as it serves as a freshness queue.

		$controlData = $this->addCacheFile($controlData, $tmpFile);
		$jsonService->dump($controlFile, $controlData);

		return $tmpFile;
	}

	protected function removeOutdatedCacheFiles(array $controlData, array $config, $tmpDir) {
		$max = $config['max_cachefiles'];

		if (count($controlData) >= $max) {
			$max    = $max - 1; // -1 to make room for the requested file's cache file
			$oldest = array_slice($controlData, 0, -$max);

			foreach ($oldest as $basename) {
				$cacheFile = $tmpDir.'/'.$basename;

				if (file_exists($cacheFile)) {
					@unlink($cacheFile);
				}
			}

			$controlData = array_slice($controlData, -$max);
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
