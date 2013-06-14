<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImageResize\Filter;

use sly\ImageResize\Exception;

/**
 * Brands an image by adding a brand image to it
 */
class Brand {
	protected $brandImage;
	protected $hPos;
	protected $vPos;
	protected $hPadding;
	protected $vPadding;

	public function __construct($brandImage, $hPos = 'right', $vPos = 'bottom', $hPadding = 0, $vPadding = 0) {
		if (!file_exists($brandImage)) {
			throw new Exception('The branding image "'.$brandImage.'" could not be found.');
		}

		if (!in_array($hPos, array('left', 'center', 'right'))) {
			throw new Exception('Unexpected value "'.$hPos.'" for the horizontal position.');
		}

		if (!in_array($vPos, array('top', 'center', 'bottom'))) {
			throw new Exception('Unexpected value "'.$vPos.'" for the vertical position.');
		}

		$this->brandImage = $brandImage;
		$this->hPos       = $hPos;
		$this->vPos       = $vPos;
		$this->hPadding   = (int) $hPadding;
		$this->vPadding   = (int) $vPadding;
	}

	public function filter($image) {
		// open image file and determine dimentions
		$brandImage  = file_get_contents($this->brandImage);
		$brandImage  = imagecreatefromstring($brandImage);
		$brandWidth  = imagesx($brandImage);
		$brandHeight = imagesy($brandImage);

		switch ($this->hPos) {
			case 'left':
				$dstX = 0;
				break;

			case 'center':
				$dstX = (int) ((imagesx($image) - $brandWidth) / 2);
				break;

			case 'right':
				$dstX = imagesx($image) - $brandWidth;
				break;
		}

		switch ($this->vPos) {
			case 'top':
				$dstY = 0;
				break;

			case 'center':
				$dstY = (int) ((imagesy($image) - $brandHeight) / 2);
				break;

			case 'bottom':
				$dstY = imagesy($image) - $brandHeight;
				break;
		}

		imagealphablending($image, true);
		imagecopy($image, $brandImage, $dstX + $this->hPadding, $dstY + $this->yPadding, 0, 0, $brandWidth, $brandHeight);

		imagedestroy($brandImage);

		return $image;
	}
}
