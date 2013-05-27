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
		$params = array();

		// resize params

		/*    timestamp */ $params[] = '(c?[0-9]{1,4}[whaxc])';
		/* uncompressed */ $params[] = '(?P<uncompressed>n)';
		/*    upscaling */ $params[] = '(?P<upscaling>u[01]?)';
		/*       filter */ $params[] = '(?P<filter>f[a-z0-9]+)';

		// offset options ('24o', '150r', ...)
		$offset       = '(?P<offset>-?[0-9]{1,4}[orltb])';
		$offsetRight  = '(?P<oright>-?[0-9]{1,4}r)';
		$offsetLeft   = '(?P<oleft>-?[0-9]{1,4}l)';
		$offsetTop    = '(?P<otop>-?[0-9]{1,4}t)';
		$offsetBottom = '(?P<obottom>-?[0-9]{1,4}b)';

		// misc options

		/*    timestamp */ $params[] = '(?:t[0-9]+)';
		/* uncompressed */ $params[] = 'n';
		/*    upscaling */ $params[] = 'u[01]?';
		/*       filter */ $params[] = 'f[a-z0-9]+';

		$params   = '('.implode('__)|(', $params).')';
		$godRegex = "($params)+__(?P<filename>.+)";
			''.
			$filter.'*'.
			$upscaling.'?'.
			$uncompressed.'?'.
			$timestamp.'?'.
			$filename
		;

		// c=crop before w=width|h=heigth|a=both followed by o=offset|r=right|l=left|t=top|b=bottom
		// followed by f=filter followed by u=upscaling
		$godRegex = '@((?:c?[0-9]{1,4}[whaxc]__){1,2}(?:\-?[0-9]{1,4}[orltb]?__){0,2}F*U?N?T?)(.*)$@';

		$router->prependRoute(
			'/mediapool/resize/(?P<file>.+?)',
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