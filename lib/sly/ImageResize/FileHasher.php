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

use wv\BabelCache\CacheInterface;

class FileHasher {
	protected $cache;
	protected $hashes;
	protected $prefix;

	const CACHE_NAMESPACE = 'sly.imageresize';

	public function __construct(CacheInterface $cache, $cachePrefix) {
		$this->cache  = $cache;
		$this->hashes = array();
		$this->prefix = $cachePrefix;
	}

	public function flushCache($filename = null) {
		if ($filename === null) {
			$this->hashes = array();
			$this->cache->flush(self::CACHE_NAMESPACE);
		}
		else {
			unset($this->hashes[$filename]);
			$this->cache->delete(self::CACHE_NAMESPACE, $this->key($filename));
		}
	}

	public function hash($filename, $silent = true) {
		// skip all this if we did it already
		//
		if (array_key_exists($filename, $this->hashes)) {
			return $this->hashes[$filename];
		}

		// determine last file change

		$mtime = @filemtime($filename);

		if ($mtime === false) {
			if ($silent) {
				$this->hashes[$filename] = null;
				return null;
			}

			throw new Exception('Could not get mtime for file "'.$filename.'".');
		}

		// check for any cached file hash

		$key  = $this->key($filename);
		$data = $this->cache->get(self::CACHE_NAMESPACE, $key, null);

		if ($data && $data['mtime'] === $mtime) {
			$this->hashes[$filename] = $data['hash'];

			return $data['hash'];
		}

		// hash the file content

		$hash = @sha1_file($filename);

		if ($hash === false) {
			if ($silent) {
				$this->hashes[$filename] = null;
				return null;
			}

			throw new Exception('Could not get SHA-1 hash for file "'.$filename.'".');
		}

		// remember the current state

		$hash = substr(base_convert($hash, 16, 36), 0, 10);
		$data = array('mtime' => $mtime, 'hash' => $hash);

		$this->cache->set(self::CACHE_NAMESPACE, $key, $data);
		$this->hashes[$filename] = $hash;

		return $hash;
	}

	protected function key($filename) {
		return substr(sha1($this->prefix.sha1($filename)), 0, 12);
	}
}
