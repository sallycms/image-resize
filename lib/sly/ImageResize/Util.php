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
	public static function scaleMediaImagesInHtml($html, array $options = array()) {
		$mediumService = sly_Core::getContainer()->get('sly-service-medium');
		$options       = array_merge(array(
			'max_width'     => null,
			'max_height'    => null,
			'resize'        => true,
			'disable_hash'  => false,
			'absolute_uris' => false
		), $options);

		// TODO: This is not yet finished.

		$callback = function($matches) use ($options, $mediumService) {
			$before    = $matches[1];
			$filepath  = $matches[2];
			$filename  = $matches[4];
			$after     = $matches[5];
			$resizeOpt = $options;
			$medium    = $mediumService->findByFilename($filename);

			if ($medium instanceof sly_Model_Medium) {
				$attrs = $before.' '.$after;

				if ($options['resize']) {
					// determine width from attribute or CSS style
					preg_match('/\bwidth\s*=\s*"([0-9]+)"/is', $attrs, $width);
					if (!$width) preg_match('/\bwidth\s*:\s*([0-9]+)\s*px/is', $attrs, $width);

					// determine height from attribute or CSS style
					preg_match('/\bheight\s*=\s*"([0-9]+)"/is', $attrs, $height);
					if (!$height) preg_match('/\bheight\s*:\s*([0-9]+)\s*px/is', $attrs, $height);

					// prepare resize options
					if ($width)  $resizeOpt['width']  = (int) $width[1];
					if ($height) $resizeOpt['height'] = (int) $height[1];
				}

				$filepath = $medium->resize($options, $forceRelative ? null : (sly_Core::isBackend() ? true : null));
			}

			return sprintf('<img %ssrc="%s"%s>', $before, $filepath, $after);
		};

		$html = preg_replace_callback('#<img ([^<>]*)src="((data/)?mediapool/(?!resize/)([^?&;"]+))[a-zA-Z0-9?&;=]*"([^>]*)>#', $callback, $html);

		return $html;
	}

	/**
	 *
	 * @throws Exception
	 * @param  sly_Model_Medium $medium
	 * @param  array            $options  (width, height, width_crop, height_crop, width_primary, height_primary, extra)
	 * @param  bool             $path     null = relative file URI; false = virtual filename; true = absolute file URI
	 * @return string
	 */
	public static function resize(\sly_Model_Medium $medium, array $options = array(), $path = null) {
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
		$filename   = Filename::fromMedium($medium);
		$resizes    = array_filter($widthFirst ? array($width, $height) : array($height, $width));

		$filename->setResizes($resizes);

		if ($options['extra']) {
			$filename->addOffset($options['extra']);
		}

		// get virtual filename

		if ($path === null) {
			return $filename->getUri();
		}
		elseif ($path === true) {
			return $filename->getAbsoluteUri();
		}
		else {
			return $filename->getVirtualFilename();
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

		$imgInfo = getimagesize($filename);
		if ($imgInfo === false) return false;

		return in_array($imgInfo[2], $supported) ? $imgInfo[2] : false;
	}
}
