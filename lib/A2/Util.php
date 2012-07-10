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

	/**
	 * Used to receive the Path to the internal directory of this addon
	 *
	 * @staticvar string $internalDir
	 * @return string Path to Internal Directory
	 */
	public static function getInternalDirectory() {
		static $internalDir;
		if(empty($internalDir)) {
			$service     = sly_Service_Factory::getAddOnService();
			$is06        = sly_Core::getVersion('X.Y') === '0.6';
			$name        = self::getName();
			$internalDir = $is06 ? $service->internalFolder($name) : $service->internalDirectory($name);
		}
		return $internalDir;
	}

	/**
	 * Used to receive the addon name
	 *
	 * @staticvar string $internalDir
	 * @return string Path to Internal Directory
	 */
	public static function getName() {
		static $name;
		if(empty($name)) {
			$is06        = sly_Core::getVersion('X.Y') === '0.6';
			$name        = $is06 ? 'image_resize' : 'sallycms/image-resize';
		}
		return $name;
	}

	public static function cleanInternalDirectory() {
		$cacheFolder = new sly_Util_Directory(self::getInternalDirectory());
		$cacheFolder->deleteFiles();
	}

	public static function cleanPossiblyCachedFiles() {
		self::cleanInternalDirectory();
		sly_Service_Factory::getAssetService()->validateCache();
	}
}