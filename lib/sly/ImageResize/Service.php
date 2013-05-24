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

use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;

/**
 * @author zozi@webvariants.de
 */
class Service implements \sly_ContainerAwareInterface {
	protected $container;
	protected $cacheDir;
	protected $config;

	public function __construct($cacheDir, array $config) {
		$this->cacheDir = $cacheDir;
		$this->config   = $config;
	}

	public function setContainer(\sly_Container $container = null) {
		$this->container = $container;
	}

	/**
	 * delete all files in the internal directory
	 */
	public function flushCache() {
		$service = new \sly_Filesystem_Service(new Filesystem(new Local($this->cacheDir)));
		$service->deleteAllFiles();
	}

	/**
	 * get a config property of the addon
	 *
	 * @param  string $key      the config key
	 * @param  mixed  $default  a default value if the entry not exists
	 * @return mixed            the config value
	 */
	public function getConfig($key, $default = null) {
		return isset($this->config[$key]) ? $this->config[$key] : $default;
	}

	public function getSpecialFile() {
		return $this->container['sly-config']->get('INSTNAME').'.jpg';
	}

	/**
	 * return the image file path
	 *
	 * @param  string $filename
	 * @return string
	 */
	public function getImageFile($filename) {
		// use the special test image
		if ($filename !== self::getSpecialFile()) {
			return 'data/mediapool/'.$filename;
		}
		else {
			return 'data/dyn/public/sallycms/image-resize/testimage.jpg';
		}
	}
}
