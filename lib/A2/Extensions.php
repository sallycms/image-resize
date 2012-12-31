<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @author Robert
 */
class A2_Extensions {
	// c=crop before w=width|h=heigth|a=both followed by o=offset|r=right|l=left|t=top|b=bottom
	// followed by f=filter followed by u=upscaling
	private static $godRegex = '@((?:c?[0-9]{1,4}[whaxc]__){1,2}(?:\-?[0-9]{1,4}[orltb]?__){0,2}(?:f[a-z0-9]+__)*(?:u[01]?__)?(?:n__)?(?:t[0-9]+__)?)(.*)$@';

	/**
	 * Try to parse a file as an imageresize request
	 *
	 * This method can be used by other addOns to determine whether a given file
	 * is likely to be a virtual filename, used by this addOn.
	 *
	 * @param  string $filename  the filename (like "600w__foo.jpg")
	 * @return mixed             null if invalid, else an array with the filename and the params
	 */
	public static function parseFilename($filename) {
		// separate filename and parameters (x and c in whaxc are just for backwards compatibility)
		preg_match(self::$godRegex, $filename, $params);
		if (!isset($params[1]) || empty($params[2])) return null;

		return array(
			'filename' => $params[2],
			'params'   => explode('__', trim($params[1], '_'))
		);
	}

	/**
	 * Verarbeitet ein vom Asset Cache angegebene Bild
	 *
	 * @param  array $params
	 * @return string
	 */
	public static function resizeListener(array $params) {
		$filename = $params['subject'];

		if (!self::filePathOk($filename)) {
			return $filename;
		}
		// data/mediapool/c100w__c200h__20r__20t__filename.jpg

		// separate filename and parameters (x and c in whaxc are just for backwards compatibility)
		$result = self::parseFilename($filename);
		if ($result === null) return $filename;

		// get parts
		$imageFile = self::getImageFile($result['filename']);
		$params    = $result['params'];

		$intDir      = A2_Util::getInternalDirectory();
		$controlFile = self::getControlFile($imageFile);
		$controlData = file_exists($controlFile) ? json_decode(file_get_contents($controlFile), true) : array();

		// handle max_cachefiles for this image
		if (count($controlData) >= A2_Util::getProperty('max_cachefiles') && !in_array($filename, $controlData)) {
			$assetService = sly_Service_Factory::getAssetService();

			if (is_callable(array($assetService, 'removeCacheFiles'))) {
				//remove first created rezized file from asset service
				$assetService->removeCacheFiles(array_shift($controlData));
			}
			else {
				//this is a fallback for pre Sally 0.6.7 only
				//remove all resized files
				$controlData = array();
				unlink($controlFile);
				$assetService->validateCache();
			}
		}

		// iterate parameters
		$imgParams        = array();
		$filters          = array();
		$upscalingAllowed = (bool) A2_Util::getProperty('upscaling_allowed', false);
		$recompress       = (bool) A2_Util::getProperty('recompress', true);
		$jpegQuality      = (int) A2_Util::getProperty('jpg_quality', 85);
		$type             = null;

		foreach ($params as $param) {
			// check crop option
			$crop   = false;
			$prefix = substr($param, 0, 1);

			if ($prefix == 'c') {
				$crop = true;
				$param = substr($param, 1);
			}
			// check filter option
			elseif ($prefix == 'f') {
				$filters[] = substr($param, 1);
				continue;
			}
			// check upscale option
			elseif ($prefix == 'u') {
				if (strlen($param) < 2 || substr($param, 1, 1) === '1') {
					$upscalingAllowed = true;
				}
				continue;
			}
			// n for no compression
			elseif ($prefix == 'n') {
				$recompress = false;
				continue;
			}
			elseif ($prefix == 't') {
				$imageType = substr($param, 1);
				if (in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WBMP))) {
					$type = $imageType;
				}
				continue;
			}

			// identify type
			$suffix = substr($param, strlen($param)-1);
			// get value
			$value = substr($param, 0, strlen($param)-1);

			// set parameters for resizing (x and c just for backwards compatibility)
			if (in_array($suffix, array('w', 'h', 'a', 'x', 'c'))) {
				switch ($suffix) {
					case 'w':
						$suffix = 'width';
						break;

					case 'h':
						$suffix = 'height';
						break;

					case 'a':
						$suffix = 'auto';
						break;

					case 'x':
					case 'c':
						$suffix = 'width';
						$crop = true;
						break;
				}

				$imgParams[$suffix] = array('value' => $value, 'crop' => ($crop));
			}

			// set parameters for crop offset
			if (in_array($suffix, array('o', 'r', 'l', 't', 'b'))) {
				switch ($suffix) {
					case 'o':
						$imgParams['width']['offset']['left'] = $value;
						$imgParams['height']['offset']['top'] = $value;
						break;

					case 'r':
						$imgParams['width']['offset']['right'] = $value;
						break;

					case 'l':
						$imgParams['width']['offset']['left'] = $value;
						break;

					case 't':
						$imgParams['height']['offset']['top'] = $value;
						break;

					case 'b':
						$imgParams['height']['offset']['bottom'] = $value;
						break;
				}
			}
		}

		$time = time();

		//clean up old tmp_ files
		foreach (glob($intDir.'/tmp_*') as $filename_i) {
			preg_match('#/tmp_(\d+)_[^/]+#', $filename_i, $matches);
			if ($matches && $matches[1] < ($time - 120)) {
				@unlink ($filename_i);
			}
		}

		try {
			$thumb = new A2_Thumbnail($imageFile);
			$thumb->setAllowUpscaling($upscalingAllowed);
			$thumb->setImgParams($imgParams);
			$thumb->setNewSize();
			$thumb->addFilters($filters);
			$thumb->setJpgCompress($recompress);
			$thumb->setJpegQuality($jpegQuality);
			if ($type !== null) $thumb->setThumbType($type);

			$tmpFile = $intDir.'/tmp_' . $time . '_' .md5($filename).'.'.sly_Util_String::getFileExtension($imageFile);

			$thumb->generateImage($tmpFile);

			$controlData[] = $filename;
			file_put_contents($controlFile, json_encode($controlData));
		}
		catch (Exception $e) {
			switch ($e->getCode()) {
				case 404:
					// file not found (...)
					header('HTTP/1.0 404 Not Found');
					break;

				case 406:
					// unsupported file type
					header('HTTP/1.0 406 Not Acceptable');
					break;

				case 500:
					// bad image file
					header('HTTP/1.0 500 Internal Server Error');
					break;

				case 501:
					// animated GIF -> return the original filename
					return $imageFile;
			}

			die;
		}

		return $tmpFile;
	}

	public static function translateListener(array $params) {
		$files   = $params['subject'];

		// Check every file for "data/mediapool/100w_foo.jpg" and turn them
		// into strings like "data/mediapool/foo.jpg", so that the asset cache
		// can check whether they have changed and remove the cached version.

		foreach ($files as $idx => $filename) {
			$filename = str_replace(DIRECTORY_SEPARATOR, '/', $filename);
			//first check if this might be a resized file
			if (!self::filePathOk($filename)) {
				continue;
			}

			//concrete check the filenames syntax
			$result = self::parseFilename(basename($filename));
			if ($result === null || empty($result['params'])) {
				continue;
			}

			$filename    = self::getImageFile($result['filename']);
			$controlFile = self::getControlFile($filename);

			/*
			 * If no control file exists, return the control file.
			 * Because it not exists the asset cachefile will be removed.
			 * This is kind of a hack.
			 */
			if (!file_exists($controlFile)) {
				$files[$idx] = $controlFile;
			} else {
				$files[$idx] = $filename;
			}
		}

		return $files;
	}

	/**
	 * Checks if this addon should handle this file
	 * @param  string $filePath the files path
	 * @return boolean   whether the files path is ok
	 */
	public static function filePathOk($filePath) {
		$pool = 'data/mediapool/';
		$special = self::getSpecialFile();
		return (sly_Util_String::startsWith($filePath, $pool)
				|| sly_Util_string::endsWith($filePath, $special));
	}

	public static function getSpecialFile() {
		return sly_Core::config()->get('INSTNAME').'.jpg';
	}

	public static function getControlFile($realFile) {
		return A2_Util::getInternalDirectory().DIRECTORY_SEPARATOR.'control_'.md5($realFile).'.json';
	}

	/**
	 * return the image file path
	 *
	 * @param  string $filename
	 * @return string
	 */
	public static function getImageFile($filename) {
		// use the special test image
		if ($filename !== self::getSpecialFile()) {
			return 'data/mediapool/'.$filename;
		}
		else {
			return 'data/dyn/public/sallycms/image-resize/testimage.jpg';
		}
	}

	/**
	 * SLY_ARTICLE_OUTPUT
	 *
	 * @param array $params
	 */
	public static function articleOutput(array $params) {
		$content = $params['subject'];

		preg_match_all('#<img [^\>]*src="(data/mediapool/([^"]*))[^\>]*>#is', $content, $matches);

		if (is_array($matches[0])) {
			foreach ($matches[0] as $key => $var) {
				preg_match('/width="(.*?)"/is', $var, $width);
				if (!$width) preg_match('/width: (.*?)px/is', $var, $width);

				if ($width) {
					$filename = SLY_MEDIAFOLDER.'/'.$matches[2][$key];

					if (file_exists($filename)) {
						$realsize = getimagesize($filename);

						if ($realsize[0] != $width[1]) {
							$newsrc   = 'data/mediapool/'.$width[1].'w__'.$matches[2][$key];
							$newimage = str_replace($matches[1][$key], $newsrc, $var);
							$content  = str_replace($var, $newimage, $content);
						}
					}
				}
			}
		}

		return $content;
	}

	/**
	 * SLY_SYSTEM_CACHES
	 *
	 * @param array $params  event parameters
	 */
	public static function systemCacheList(array $params) {
		$select     = $params['subject'];
		$name       = 'sallycms/image-resize';
		$selected   = $select->getValue();
		$selected[] = $name;

		$select->addValue($name, t('iresize_resized_images'));
		$select->setSelected($selected);

		return $select;
	}

	/**
	 * SLY_CACHE_CLEARED
	 *
	 * @param array $params
	 */
	public static function cacheCleared(array $params) {
		$isSystem = sly_Core::getCurrentControllerName() === 'system';

		// do nothing if requested
		if ($isSystem) {
			$controller = sly_Core::getCurrentController();

			if (method_exists($controller, 'isCacheSelected') && !(
					$controller->isCacheSelected('sly_asset')
					|| $controller->isCacheSelected('sallycms/image-resize')
				)
			) {
				return $params['subject'];
			}
		}

		A2_Util::cleanPossiblyCachedFiles();
		return isset($params['subject']) ? $params['subject'] : true;
	}

	public static function backendNavigation(array $params) {
		$user = sly_Util_User::getCurrentUser();

		if ($user !== null && ($user->isAdmin() || $user->hasRight('pages', 'imageresize'))) {
			$nav   = sly_Core::getLayout()->getNavigation();
			$group = $nav->getGroup('addons');
			$page  = $nav->addPage($group, 'imageresize', t('iresize_image_resize'));

			$page->addSubpage('imageresize',            t('iresize_subpage_config'));
			$page->addSubpage('imageresize_clearcache', t('iresize_subpage_clear_cache'));
		}

		return $params['subject'];
	}
}
