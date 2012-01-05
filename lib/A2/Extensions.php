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

		// c100w__c200h__20r__20t__filename.jpg

		// separate filename and parameters (x and c in whaxc are just for backwards compatibility)
		$result = self::parseFilename($filename);
		if ($result === null) return $filename;

		// get parts
		$imageFile = $result['filename'];
		$params    = $result['params'];

		// iterate parameters
		$imgParams        = array();
		$filters          = array();
		$upscalingAllowed = false;
		$recompress       = true;
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

		try {
			$thumb = new A2_Thumbnail($imageFile);
			if ($upscalingAllowed) $thumb->allowUpscaling();
			$thumb->setImgParams($imgParams);
			$thumb->setNewSize();
			$thumb->addFilters($filters);
			if (!$recompress) $thumb->disableJpgCompress();
			if ($type !== null) $thumb->setThumbType($type);

			$service = sly_Service_Factory::getAddOnService();
			$tmpFile = $service->publicFolder('image_resize').'/'.md5(mt_rand()).'.'.sly_Util_String::getFileExtension($imageFile);

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
							$newsrc   = 'imageresize/'.$width[1].'w__'.$matches[2][$key];
							$newimage = str_replace($matches[1][$key], $newsrc, $var);
							$content  = str_replace($var, $newimage, $content);
						}
					}
				}
			}
		}

		return $content;
	}
}
