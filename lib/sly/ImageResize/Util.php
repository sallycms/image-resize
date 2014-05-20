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

use sly_Core;
use sly_Model_Medium;

/**
 * @author zozi@webvariants.de
 */
abstract class Util {
	/**
	 * Scale media inside of HTML
	 *
	 * This method will find all mediapool images in the given HTML and apply
	 * resizing to them, altering their URI. The $options can contain values for
	 * ::resize(), as ::resize() is going to be called by this.
	 *
	 * Possible options are:
	 *
	 *   - max_width:     apply this max width to all images
	 *   - max_height:    same as for max_width
	 *   - resize:        if false, no resizing will be done, but the filehash
	 *                    will be appended (if enabled in the backend)
	 *   - absolute_uris: true to generate absolute URIs (great if you're in the
	 *                    backen) or false for relative URIs
	 *   - replace_old:   set this to true to automatically call replaceOldUris
	 *                    on the $html
	 *
	 * @param  string $html
	 * @param  array  $options
	 * @return string
	 */
	public static function scaleMediaImagesInHtml($html, array $options = array()) {
		$mediumService = sly_Core::getContainer()->get('sly-service-medium');
		$options       = array_merge(array(
			'max_width'     => null,
			'max_height'    => null,
			'resize'        => true,
			'absolute_uris' => false,
			'replace_old'   => false
		), $options);

		if ($options['replace_old']) {
			$html = self::replaceOldUris($html);
		}

		if (!preg_match_all('#<img ([^<>]*)src="((data/)?mediapool/(?!resize/)([^?&;"]+))([a-zA-Z0-9?&;=]*)"([^>]*)>#', $html, $matches, PREG_SET_ORDER)) {
			return $html;
		}

		$maxWidth  = $options['max_width'];
		$maxHeight = $options['max_height'];
		$pathStyle = $options['absolute_uris'] ? 'abs' : 'rel';
		$getPixels = function($attrs, $name) {
			// CSS property
			if (preg_match("/\b$name\s*:\s*([0-9]+)\s*px/is", $attrs, $match)) {
				return (int) $match[1];
			}

			// HTML attribute
			if (preg_match("/\b$name\s*=\s*\"([0-9]+)\"/is", $attrs, $match)) {
				return (int) $match[1];
			}

			return null;
		};

		foreach ($matches as $match) {
			$before    = $match[1];
			$filePath  = $match[2];
			$fileName  = $match[4];
			$queryStr  = $match[5];
			$after     = $match[6];
			$fileUri   = $filePath.$queryStr;
			$resizeOpt = $options;
			$medium    = $mediumService->findByFilename($fileName);

			if ($medium instanceof sly_Model_Medium) {
				$attrs = $before.' '.$after;

				if ($options['resize']) {
					$htmlWidth  = $getPixels($attrs, 'width');
					$htmlHeight = $getPixels($attrs, 'height');

					$width  = ($maxWidth  !== null && ($htmlWidth  === null || $htmlWidth  > $maxWidth))  ? $maxWidth  : $htmlWidth;
					$height = ($maxHeight !== null && ($htmlHeight === null || $htmlHeight > $maxHeight)) ? $maxHeight : $htmlHeight;

					// prepare resize options
					if ($width  !== null) $resizeOpt['width']  = $width;
					if ($height !== null) $resizeOpt['height'] = $height;
				}

				$filePath = self::resize($medium, $resizeOpt, $pathStyle);

				// $filePath may now have a trailing '?t=...' at the end, so be
				// careful when appending the original query string to it (or else
				// we end up with 'poop.jpg?t=1234?paramx=valuey').

				if ($queryStr) {
					if (strpos($filePath, '?') !== false) {
						// temporarily un-htmlencode the link, so we can easily trim it
						$queryStr = str_replace('&amp;', '&', $queryStr);
						$queryStr = ltrim($queryStr, '?&');
						$fileUri  = $filePath.'&amp;'.str_replace('&', '&amp;', $queryStr);
					}
					else {
						$fileUri = $filePath.$queryStr;
					}
				}
				else {
					$fileUri = $filePath;
				}
			}

			// remove any trailing bad chars
			$fileUri = rtrim($fileUri, '?&');
			$newTag  = sprintf('<img %ssrc="%s"%s>', $before, $fileUri, $after);

			// replace the match
			$html = str_replace($match[0], $newTag, $html);
		}

		return $html;
	}

	/**
	 * Replace old, 3.x URIs with their new pattern
	 *
	 * This will replace all image URIs starting with 'imageresize/' by them
	 * starting with 'mediapool/resize'. Use this to migrate old content.
	 *
	 * @param  string $html
	 * @return string
	 */
	public static function replaceOldUris($html) {
		$html = preg_replace(
			'#<img ([^<>]*)src="imageresize/([^"]+)"([^>]*)>#',
			'<img $1src="mediapool/resize/$2"$3>',
			$html
		);

		return $html;
	}

	/**
	 *
	 * @throws Exception
	 * @param  sly_Model_Medium $medium
	 * @param  array            $options  (width, height, width_crop, height_crop, width_primary, height_primary, extra)
	 * @param  bool             $path     'rel' = relative file URI; 'virt' = virtual filename; 'abs' = absolute file URI
	 * @return string
	 */
	public static function resize(sly_Model_Medium $medium, array $options = array(), $path = 'rel') {
		// if image type is not supported return filepath

		if (self::getSupportedImageType($medium->getFilename()) === false) {
			$uri = $medium->getFilename();
			if ($path === 'rel' || $path === 'abs') {
				$uri = 'mediapool/'.$uri;
			}
			if ($path === 'abs') {
				$request = sly_Core::getContainer()->get('sly-request');
				$baseUri = $request->getBaseUrl(true);
				$uri     = $baseUri.$uri;
			}

			return $uri;
		}


		// if no width_primary or height_primary option is given, first width or height is primary

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

		// set default options

		$options = array_merge(array(
			'width'          => null,
			'height'         => null,
			'width_crop'     => false,
			'height_crop'    => false,
			'width_primary'  => false,
			'height_primary' => false,
			'disable_hash'   => false,
			'extra'          => ''
		), $options);

		// handle width/height params

		$width  = null;
		$height = null;

		if ($options['width']) {
			$width = ($options['width_crop'] ? 'c' : '').$options['width'].'w';
		}

		if ($options['height']) {
			$height = ($options['height_crop'] ? 'c' : '').$options['height'].'h';
		}

		// build filename

		$widthFirst = $options['width_primary'] || !$options['height_primary'];
		$filename   = Filename::fromMedium($medium, true);
		$resizes    = array_filter($widthFirst ? array($width, $height) : array($height, $width));

		$filename->setResizes($resizes);

		if ($options['extra']) {
			$filename->addOffset($options['extra']);
		}

		// get virtual filename

		$appendFlag = $options['disable_hash'] === true ? false : null;

		switch ($path) {
			case 'rel':  return $filename->getUri($appendFlag);
			case 'abs':  return $filename->getAbsoluteUri($appendFlag);
			case 'virt': return $filename->getVirtualFilename($appendFlag);
		}

		throw new \InvalidArgumentException('Unknown path style "'.$path.'" given. Must be one of [rel, abs, virt].');
	}

 	/**
	 * return the image types supported by this PHP build
	 *
	 * @return array  list of IMAGETYPE_* constants
	 */
	public static function getSupportedTypes() {
		$types     = imagetypes();
		$supported = array();
		$mapping   = array(
			// imagetypes() returns IMG_* constants, but getimagesize() and other
			// functions use the IMAGETYPE_* constants. They are almost the same,
			// except for PNG. So we properly map them here.
			IMAGETYPE_GIF  => IMG_GIF,
			IMAGETYPE_JPEG => IMG_JPG,
			IMAGETYPE_PNG  => IMG_PNG,
			IMAGETYPE_WBMP => IMG_WBMP
		);

		foreach ($mapping as $realType => $bit) {
			if ($types & $bit) {
				$supported[] = $realType;
			}
		}

		return $supported;
	}

	/**
	 * @return int  IMAGETYPE_* constant or false if no (supported) type was detected
	 */
	public static function getSupportedImageType($filename) {
		$supported = self::getSupportedTypes();
		if (empty($supported)) return false;

		$imgInfo = getimagesize($filename);
		if ($imgInfo === false) return false;

		return in_array($imgInfo[2], $supported) ? $imgInfo[2] : false;
	}
}
