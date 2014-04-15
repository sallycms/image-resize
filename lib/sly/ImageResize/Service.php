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
use sly_Filesystem_Filesystem;
use sly_Filesystem_Service;

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

	public function getCacheDir() {
		return $this->cacheDir;
	}

	/**
	 * delete all files in the internal directory
	 */
	public function flushCache() {
		$service = new sly_Filesystem_Service(new sly_Filesystem_Filesystem(new Local($this->cacheDir)));
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
		if ($key === null) return $this->config;

		return isset($this->config[$key]) ? $this->config[$key] : $default;
	}

	public function getControlFile($realFile) {
		return $this->cacheDir.DIRECTORY_SEPARATOR.'control_'.sha1($realFile).'.json';
	}

	public function getSpecialImage() {
		return SLY_ADDONFOLDER.'/sallycms/image-resize/assets/testimage.jpg';
	}
}
