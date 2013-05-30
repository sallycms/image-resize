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

/**
 * Blur filter
 *
 * This is based on 'Unsharp Mask for PHP - version 2.1.1'
 *
 * @author Christoph Erdmann (cerdmann.com, changed it a little, cause i could
 *                           not reproduce the darker blurred image, now it is
 *                           up to 15% faster with same results)
 * @author Torstein Hønsi    (2003-07, thoensi_at_netcom_dot_no)
 */
class Blur extends Sharpen {
	public function __construct($amount = 80, $radius = 8, $threshold = 3) {
		parent::__construct($amount, $radius, $threshold);
	}

	protected function filterImage($img, $width, $height) {
		$blurred = imagecreatetruecolor($width, $height);

		// Move copies of the image around one pixel at the time and merge them with weight
		// according to the matrix. The same matrix is simply repeated for higher radii.

		for ($i = 0; $i < $this->radius; ++$i) {
			imagecopy     ($blurred, $img, 0, 0, 1, 1, $width-1, $height-1);            // up left
			imagecopymerge($blurred, $img, 1, 1, 0, 0, $width,   $height,   50);        // down right
			imagecopymerge($blurred, $img, 0, 1, 1, 0, $width-1, $height,   33.33333);  // down left
			imagecopymerge($blurred, $img, 1, 0, 0, 1, $width,   $height-1, 25);        // up right
			imagecopymerge($blurred, $img, 0, 0, 1, 0, $width-1, $height,   33.33333);  // left
			imagecopymerge($blurred, $img, 1, 0, 0, 0, $width,   $height,   25);        // right
			imagecopymerge($blurred, $img, 0, 0, 0, 1, $width,   $height-1, 20);        // up
			imagecopymerge($blurred, $img, 0, 1, 0, 0, $width,   $height,   16.666667); // down
			imagecopymerge($blurred, $img, 0, 0, 0, 0, $width,   $height,   50);        // center
		}

		return $blurred;
	}
}
