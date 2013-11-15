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
 * @author zozi@webvariants.de
 */
class A2_Util {
	private static $name;
	private static $internalDir;

	/**
	 * Used to receive the Path to the internal directory of this addon
	 *
	 * @return string  path to Internal Directory
	 */
	public static function getInternalDirectory() {
		if (empty(self::$internalDir)) {
			$service           = sly_Service_Factory::getAddOnService();
			self::$internalDir = $service->internalDirectory('sallycms/image-resize');
		}

		return self::$internalDir;
	}

	/**
	 * Used to receive the addon name
	 *
	 * @deprecated since 3.2, will be removed in 4.0
	 *
	 * @return string  always 'sallycms/image-resize'
	 */
	public static function getName() {
		return 'sallycms/image-resize';
	}

	/**
	 * delete all files in the internal directory
	 */
	public static function cleanInternalDirectory() {
		$cacheFolder = new sly_Util_Directory(self::getInternalDirectory());
		$cacheFolder->deleteFiles();
	}

	/**
	 * clean all files in the Asset Service
	 */
	public static function cleanPossiblyCachedFiles() {
		self::cleanInternalDirectory();
		sly_Service_Factory::getAssetService()->validateCache();
	}

	/**
	 * get a config property of the addon
	 *
	 * @param string $key     the config key
	 * @param mixed $default  a default value if the entry not exists
	 * @return mixed          the config value
	 */
	public static function getProperty($key, $default = null) {
		$service = sly_Service_Factory::getAddOnService();
		return $service->getProperty('sallycms/image-resize', $key, $default);
	}

	/**
	 * find <img> tags with width and heigth style attrs and translate it to the
	 * image resize syntax
	 *
	 * @param type $html
	 * @param type $maxImageSize
	 * @return type
	 */
	public static function scaleMediaImagesInHtml($html, $maxImageSize = 650) {
		// use imageresize to scale images instead of style width and height
		$html = preg_replace(
			'~style="width\:[ ]*([0-9]+)px;[ ]*height\:[ ]*([0-9]+)px;?"[ \r\n]*src="data/mediapool/([a-zA-Z0-9\.-_]+)"~',
			'src="data/mediapool/\1w__\2h__\3"',
			$html
		);

		// the same just height first
		$html = preg_replace(
			'~style="height\:[ ]*([0-9]+)px;[ ]*width\:[ ]*([0-9]+)px;?"[ \r\n]*src="data/mediapool/([a-zA-Z0-9\.-_]+)"~',
			'src="data/mediapool/\2w__\1h__\3"',
			$html
		);

		// resize the rest of the images to max resize value
		if ($maxImageSize) {
			$html = preg_replace(
				'~src="data/mediapool/([a-zA-Z0-9\.-_]+)(?<!\.bmp)"~',
				'src="data/mediapool/'.$maxImageSize.'a__\1"',
				$html
			);
		}

		return $html;
	}

	/**
	 *
	 * @param sly_Model_Medium $medium
	 * @param array $options (width, height, width_crop, height_crop, width_primary, height_primary, extra)
	 * @param bool $path null - with data/medium, false - no path, true - absolute path
	 * @return string
	 * @throws Exception
	 */
	public static function resize($medium, $options = array(), $path = null) {
		if (!$medium instanceof sly_Model_Medium) {
			$options = (array_key_exists(0, $medium['arguments'])) ? $medium['arguments'][0] : array();
			$path = (array_key_exists(1, $medium['arguments'])) ? $medium['arguments'][1] : null;
			$medium = $medium['object'];
			if (!$medium instanceof sly_Model_Medium) {
				throw new Exception('wrong arguments');
			}
		}

		/* if no width_primary or height_primary option is given, first width or height is primary */
		if (!array_key_exists('width_primary', $options) && !array_key_exists('height_primary', $options)) {
			foreach ($options as $key => $value) {
				switch ($key) {
					case 'width':
						$options['width_primary'] = true;
						break 2;
					case 'height':
						$options['height_primary'] = true;
						break 2;
				}
			}
		}

		$options = array_merge(array(
			'width' => null,
			'height' => null,
			'width_crop' => false,
			'height_crop' => false,
			'width_primary' => false,
			'height_primary' => false,
			'disable_hash' => false,
			'extra' => ''
		), $options);

		$filename = $medium->getFilename();
		$params = array();

		$width = $options['width'] ? (($options['width_crop'] ? 'c' : '') . $options['width'] . 'w' ) : '';
		$height = $options['height'] ? (($options['height_crop'] ? 'c' : '') . $options['height'] . 'h' ) : '';

		$width_first = $options['width_primary'] || !$options['height_primary'];
		if ($width_first) {
			if ($width) {
				$params[] = $width;
			}
			if ($height) {
				$params[] = $height;
			}
		} else {
			if ($height) {
				$params[] = $height;
			}
			if ($width) {
				$params[] = $width;
			}
		}

		if ($options['extra']) {
			$params[] = $options['extra'];
		}

		$result = $params ? implode('__', $params) . '__' . $filename : $filename;

		$container = method_exists('sly_Core', 'getContainer') ? sly_Core::getContainer() : null;
		if ($medium->exists()  && $container && $container->has('sly-filehash') && !$options['disable_hash']) {
			$filehash = $container->get('sly-filehash'); // comes soon to sally or addon
			/* @var $filehash sly_Service_Filehash */
			$result .= '?t=' . $filehash->hash($medium->getFullPath());
		}

		if ($path === null) {
			return 'data/mediapool/' . $result;
		}
		if ($path) {
			return sly_Util_HTTP::getBaseUrl(true) . '/data/mediapool/' . $result;
		} else {
			return $result;
		}
	}
}