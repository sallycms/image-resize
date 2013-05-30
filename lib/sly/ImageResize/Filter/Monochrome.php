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

class Monochrome {
	protected $rFactor;
	protected $gFactor;
	protected $bFactor;

	public function __construct($rFactor = 1, $gFactor = 1, $bFactor = 1) {
		$this->rFactor = $rFactor;
		$this->gFactor = $gFactor;
		$this->bFactor = $bFactor;
	}

	public function filter($src_im) {
		$src_x  = ceil(imagesx($src_im));
		$src_y  = ceil(imagesy($src_im));
		$dst_x  = $src_x;
		$dst_y  = $src_y;
		$dst_im = imagecreatetruecolor($dst_x, $dst_y);

		if (function_exists('imageantialias')) {
			imageantialias($dst_im, true); // PHP compiled with bundled version of GD
		}

		imagecopyresampled($dst_im, $src_im, 0, 0, 0, 0, $dst_x, $dst_y, $src_x, $src_y);

		// Change style of image pixelwise
		for ($y = 0; $y < $dst_y; ++$y) {
			for ($x = 0; $x < $dst_x; ++$x) {
				$col  = imagecolorat($dst_im, $x, $y);
				$r    = ($col & 0xFF0000) >> 16;
				$g    = ($col & 0x00FF00) >> 8;
				$b    = $col & 0x0000FF;
				$grey = (min($r, $g, $b) + max($r, $g, $b)) / 2;

				// Boost colors
				$boost       = 1.2;
				$boostborder = 250;

				for ($i = 0; $i < 25; ++$i) {
					if ($grey > $boostborder) {
						$grey *= $boost;
						break;
					}

					$boost       -= .01;
					$boostborder -= 10;
				}

				// Set palette

				$r = min(abs(round($grey * $this->rFactor)), 255);
				$g = min(abs(round($grey * $this->gFactor)), 255);
				$b = min(abs(round($grey * $this->bFactor)), 255);

				// set pixel color

				$col = imagecolorallocate($dst_im, $r, $g, $b);
				imagesetpixel($dst_im, $x, $y, $col);
			}
		}

		return $dst_im;
	}
}
