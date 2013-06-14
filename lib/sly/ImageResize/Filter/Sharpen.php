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
 * Sharpen filter
 *
 * This is based on 'Unsharp Mask for PHP - version 2.1.1'
 *
 * @author Christoph Erdmann (cerdmann.com, changed it a little, cause i could
 *                           not reproduce the darker blurred image, now it is
 *                           up to 15% faster with same results)
 * @author Torstein Hønsi    (2003-07, thoensi_at_netcom_dot_no)
 */
class Sharpen {
	protected $amount;
	protected $radius;
	protected $threshold;

	public function __construct($amount = 80, $radius = 0.5, $threshold = 3) {
		// Attempt to calibrate the parameters according to Photoshop

		if ($amount > 500)    $amount    = 500;
		if ($radius > 50)     $radius    = 50;
		if ($threshold > 255) $threshold = 255;

		$amount = $amount * 0.016;
		$radius = $radius * 2;
		$radius = abs(round($radius));

		if ($radius == 0) {
			throw new Exception('Invalid radius (0) given.');
		}

		$this->amount    = $amount;
		$this->radius    = $radius;
		$this->threshold = $threshold;
	}

	public function filter($img) {
		$w = imagesx($img);
		$h = imagesy($img);

		// do the actual magic
		$sharpened = $this->filterImage($img, $w, $h);

		// Calculate the difference between the sharpened pixels and the original and set the pixels
		$this->fixColors($img, $sharpened, $w, $h);

		// kill the temporary image
		unset($sharpened);

		return $img;
	}

	protected function filterImage($image, $width, $height) {
		$sharpened = imagecreatetruecolor($width, $height);

		// Gaussian blur matrix:
		$matrix = array(
			array(1, 2, 1),
			array(2, 4, 2),
			array(1, 2, 1)
		);

		imagecopy($sharpened, $image, 0, 0, 0, 0, $width, $height);
		imageconvolution($sharpened, $matrix, 16, 0);

		return $sharpened;
	}

	protected function fixColors($workingImage, $blurredImage, $width, $height) {
		for ($x = 0; $x < $width; ++$x) { // each row
			for ($y = 0; $y < $height; ++$y) { // each pixel
				list ($rOrig, $gOrig, $bOrig) = $this->getRGB($workingImage, $x, $y);
				list ($rBlur, $gBlur, $bBlur) = $this->getRGB($blurredImage, $x, $y);

				// When the masked pixels differ less from the original
				// than the threshold specifies, they are set to their original value.

				$rNew = (abs($rOrig - $rBlur) >= $this->threshold) ? max(0, min(255, ($this->amount * ($rOrig - $rBlur)) + $rOrig)) : $rOrig;
				$gNew = (abs($gOrig - $gBlur) >= $this->threshold) ? max(0, min(255, ($this->amount * ($gOrig - $gBlur)) + $gOrig)) : $gOrig;
				$bNew = (abs($bOrig - $bBlur) >= $this->threshold) ? max(0, min(255, ($this->amount * ($bOrig - $bBlur)) + $bOrig)) : $bOrig;

				// if there are differences, allocate and set the new color

				if ($rOrig != $rNew || $gOrig != $gNew || $bOrig != $bNew) {
					$color = imagecolorallocate($workingImage, $rNew, $gNew, $bNew);
					imagesetpixel($workingImage, $x, $y, $color);
				}
			}
		}
	}

	protected function getRGB($image, $x, $y) {
		$color = imagecolorat($image, $x, $y);

		return array(
			($color >> 16) & 0xFF,
			($color >>  8) & 0xFF,
			$color         & 0xFF
		);
	}
}
