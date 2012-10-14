<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
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
	 * @staticvar string $internalDir
	 * @return string Path to Internal Directory
	 */
	public static function getInternalDirectory() {
		if (empty(self::$internalDir)) {
			$service           = sly_Service_Factory::getAddOnService();
			$is06              = sly_Core::getVersion('X.Y') === '0.6';
			$name              = self::getName();
			self::$internalDir = $is06 ? $service->internalFolder($name) : $service->internalDirectory($name);
		}
		return self::$internalDir;
	}

	/**
	 * Used to receive the addon name
	 *
	 * @staticvar string $internalDir
	 * @return string Path to Internal Directory
	 */
	public static function getName() {
		if (empty(self::$name)) {
			$is06        = sly_Core::getVersion('X.Y') === '0.6';
			self::$name  = $is06 ? 'image_resize' : 'sallycms/image-resize';
		}
		return self::$name;
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
		$name    = self::getName();
		$service = sly_Service_Factory::getAddOnService();
		return $service->getProperty($name, $key, $default);
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
}
