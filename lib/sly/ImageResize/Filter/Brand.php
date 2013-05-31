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
 * Branded ein Bild mit einem Wasserzeichen
 *
 * Der Filter sucht im Medienpool nach einem Bild mit dem Dateinamen "branding.*"
 * und verwendet den 1. Treffer
 */
class Brand {
	public function filter($src_im) {
		$files = glob(SLY_MEDIAFOLDER.'/branding.*');
		if (empty($files)) return;

		$brandImage = $files[0];
		$brand      = new A2_Thumbnail($brandImage);

		// Abstand vom Rand
		$paddX = 0;
		$paddY = 0;

		$hpos = 'right';  // horizontale Ausrichtung: left/center/right
		$vpos = 'bottom'; // vertikale Ausrichtung: top/center/bottom

		switch ($hpos) {
			case 'left':
				$dstX = 0;
				break;

			case 'center':
				$dstX = (int) ((imagesx($src_im) - $brand->getImageWidth()) / 2);
				break;

			case 'right':
				$dstX = imagesx($src_im) - $brand->getImageWidth();
				break;

			default:
				trigger_error('Unexpected value for "hpos"!', E_USER_ERROR);
		}

		switch ($vpos) {
			case 'top':
				$dstY = 0;
				break;

			case 'center':
				$dstY = (int) ((imagesy($src_im) - $brand->getImageHeight()) / 2);
				break;

			case 'bottom':
				$dstY = imagesy($src_im) - $brand->getImageHeight();
				break;

			default:
				trigger_error('Unexpected value for "vpos"!', E_USER_ERROR);
		}

		imagealphablending($src_im, true);
		imagecopy($src_im, $brand->getImage(), $dstX + $paddX, $dstY + $paddY, 0, 0, $brand->getImageWidth(), $brand->getImageHeight());

		$brand->destroyImage();

		return $src_im;
	}
}