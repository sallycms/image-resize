<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
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
	private static $godRegex = '@((?:c?[0-9]{1,4}[whaxc]__){1,2}(?:\-?[0-9]{1,4}[orltb]?__){0,2}(?:f[a-z0-9]+__)*)(.*)$@';

	/**
	 * Verarbeitet ein vom Asset Cache angegebene Bild
	 *
	 * @param  array $params
	 * @return string
	 */
	public static function resizeListener(array $params) {
		$filename = $params['subject'];

		// c100w__c200h__20r__20t__filename.jpg

		// separate filename and parameters (x and c in whaxc are just for backwards compatibility)
		preg_match(self::$godRegex, $filename, $params);
		if (!isset($params[1]) || empty($params[2])) return $filename;

		// get parts
		$imageFile = $params[2];
		$params    = trim($params[1], '_');

		// split parameters
		$params = explode('__', $params);

		// iterate parameters
		$imgParams = array();
		$filters   = array();

		foreach ($params as $param) {
			// check crop option
			$crop   = false;
			$prefix = substr($param, 0, 1);

			if ($prefix == 'c') {
				$crop = true;
				$param = substr($param, 1);
			}
			elseif ($prefix == 'f') {
				$filters[] = substr($param, 1);
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

		try {
			$thumb = new A2_Thumbnail($imageFile);
			$thumb->setNewSize($imgParams);
			$thumb->addFilters($filters);

			$service = sly_Service_Factory::getAddOnService();
			$tmpFile = $service->publicFolder('image_resize').'/'.md5(mt_rand()).'.bin';

			$thumb->generateImage($tmpFile);
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
		$files = $params['subject'];
		$pool  = 'data/mediapool/';

		// Check every file for "data/mediapool/100w_foo.jpg" and turn them
		// into strings like "data/mediapool/foo.jpg", so that the asset cache
		// can check whether they have changed and remove the cached version.

		foreach ($files as $idx => $filename) {
			if (sly_Util_String::startsWith(str_replace(DIRECTORY_SEPARATOR, '/', $filename), $pool)) {
				$relname = substr($filename, strlen($pool)); // "100w__foo.jpg"

				if (preg_match(self::$godRegex, $relname, $match)) {
					$files[$idx] = $pool.$match[2];
				}
			}
		}

		return $files;
	}
}
