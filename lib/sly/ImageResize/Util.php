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

/**
 * @author zozi@webvariants.de
 */
abstract class Util {
	/**
	 * find <img> tags with width and heigth style attrs and translate it to the
	 * image resize syntax
	 *
	 * @param  string $html
	 * @param  int    $maxImageSize
	 * @return string
	 */
	public static function scaleMediaImagesInHtml($html, $maxImageSize = 650) {
		// use imageresize to scale images instead of style width and height
		$html = preg_replace(
			'~style="width\:[ ]*([0-9]+)px;[ ]*height\:[ ]*([0-9]+)px;?"[ \r\n]*src="data/mediapool/([a-zA-Z0-9\.-_]+)"~',
			'src="imageresize/\1w__\2h__\3"',
			$html
		);

		// the same just height first
		$html = preg_replace(
			'~style="height\:[ ]*([0-9]+)px;[ ]*width\:[ ]*([0-9]+)px;?"[ \r\n]*src="data/mediapool/([a-zA-Z0-9\.-_]+)"~',
			'src="imageresize/\2w__\1h__\3"',
			$html
		);

		// resize the rest of the images to max resize value
		if ($maxImageSize) {
			$html = preg_replace(
				'~src="data/mediapool/([a-zA-Z0-9\.-_]+)(?<!\.bmp)"~',
				'src="imageresize/'.$maxImageSize.'a__\1"',
				$html
			);
		}

		return $html;
	}

	/**
	 *
	 * @throws Exception
	 * @param  sly_Model_Medium $medium
	 * @param  array            $options  (width, height, width_crop, height_crop, width_primary, height_primary, extra)
	 * @param  bool             $path     null = with data/medium; false = no path; true = absolute path
	 * @return string
	 */
	public static function resize(\sly_Model_Medium $medium, array $options = array(), $path = null, \sly_Request $request = null) {
		$options = array_merge(array(
			'width'          => null,
			'height'         => null,
			'width_crop'     => false,
			'height_crop'    => false,
			'width_primary'  => false,
			'height_primary' => false,
			'extra'          => ''
		), $options);

		// handle width/height params

		$params = array();
		$width  = '';
		$height = '';

		if ($options['width']) {
			$width = ($options['width_crop'] ? 'c' : '').$options['width'].'w';
		}

		if ($options['height']) {
			$height = ($options['height_crop'] ? 'c' : '').$options['height'].'h';
		}

		// build param list

		$widthFirst = $options['width_primary'] || !$options['height_primary'];

		if ($widthFirst) {
			if ($width)  $params[] = $width;
			if ($height) $params[] = $height;
		}
		else {
			if ($height) $params[] = $height;
			if ($width)  $params[] = $width;
		}

		if ($options['extra']) {
			$params[] = $options['extra'];
		}

		// get virtual filename

		$filename = $medium->getFilename();
		$result   = implode('__', $params).'__'.$filename;

		if ($path === null) {
			return 'imageresize/'.$result;
		}
		elseif ($path === true) {
			$request = $request ?: \sly_Core::getContainer()->get('sly-request');

			return $request->getBaseUrl(true).'/imageresize/'.$result;
		}
		else {
			return $result;
		}
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

		$imgInfo = getimagesize(filename);
		if ($imgInfo === false) return false;

		return in_array($imgInfo[2], $supported) ? $imgInfo[2] : false;
	}
}
