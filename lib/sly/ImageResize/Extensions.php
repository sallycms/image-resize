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

/**
 * @author Robert
 */
class Extensions implements \sly_ContainerAwareInterface {
	protected $container;

	public function setContainer(\sly_Container $container = null) {
		$this->container = $container;
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
					$newSrc  = 'imageresize/'.$htmlWidth.'w__'.urlencode($filename);
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
}
