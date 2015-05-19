<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImageResize\Thumbnail;

use sly\ImageResize\Exception;

/**
 * Calculate image sizes
 */
class Resampler {
	public function getNewImage($width, $height, $sourceImage = null, $imageType = null) {
		// create image which has size of resized layer part
		if (function_exists('imagecreatetruecolor')) {
			$newImage = @imagecreatetruecolor($width, $height);
		}
		else {
			$newImage = @imagecreate($width, $height);
		}

		if (!$newImage) {
			throw new Exception('Could not create a valid thumbnail image.', 500);
		}

		if ($sourceImage && $imageType) {
			$this->keepTransparent($sourceImage, $newImage, $imageType);
		}

		return $newImage;
	}

	public function copyImageArea($image, $width, $height, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $imageType, $detroySourceImage = false) {
		$target = $this->getNewImage($width, $height, $image, $imageType);

		// copy layer part of resized image into empty image
		imagecopy($target, $image, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH);
		if ($detroySourceImage) imagedestroy($image);

		return $target;
	}

	/**
	 * Execute the resizing based on image params
	 *
	 * @param resource $image      an image resource
	 * @param stdClass $sizes      width, height, crop and offset parameters
	 * @param int      $imageType  image type constant
	 */
	public function resample($image, \stdClass $sizes, $imageType) {
		// create a fresh image
		$output = $this->getNewImage($sizes->thumbWidth, $sizes->thumbHeight, $image, $imageType);

		imagecopyresampled(
			$output,
			$image,
			$sizes->thumbWidthOffset,
			$sizes->thumbHeightOffset,
			$sizes->widthOffset,
			$sizes->heightOffset,
			$sizes->thumbWidth,
			$sizes->thumbHeight,
			$sizes->width,
			$sizes->height
		);

		// do only for jpegs because pngs with large white background turn to black
		if ($imageType === IMAGETYPE_JPEG) {
			$this->fixColorAfterResampling(
					$image,
					$output,
					$sizes
			);
		}

		return $output;
	}

	/**
	 * Try to fix colors after bad resampling.
	 * GD poorly handles bright colors when resampling. Here we try to fix it
	 * by getting the original image detail and match the pixel to the resampled
	 * image. if the color differs by a small margin replace it with the
	 * original.
	 *
	 * For a deeper view on the problem have a look at
	 * What GD does
	 * https://en.wikipedia.org/wiki/Bicubic_interpolation
	 * What it should do
	 * https://en.wikipedia.org/wiki/Monotone_cubic_interpolation
	 *
	 * In some years we might use imagecrop and imagescale where you can set
	 * the interpolation method
	 *
	 * @param resource $image      an image resource
	 * @param resource $resampled  an image resource
	 * @param stdClass $sizes      width, height, crop and offset parameters
	 */
	protected function fixColorAfterResampling($image, $resampled, $sizes) {
		$crop = $this->getNewImage($sizes->width, $sizes->height);

		imagecopy(
			$crop,
			$image,
			$sizes->thumbWidthOffset,
			$sizes->thumbHeightOffset,
			$sizes->widthOffset,
			$sizes->heightOffset,
			$sizes->width,
			$sizes->height
		);

		$f = $sizes->width / $sizes->thumbWidth;

		for($y = 0; $y < $sizes->thumbHeight; $y++) {
			for($x = 0; $x < $sizes->thumbWidth; $x++) {
				$cR = imagecolorat($resampled, $x, $y);
				$cC = imagecolorat($crop, floor($x * $f), floor($y * $f));

				if ($cR !== $cC) {
					$reColor = false;
					$cR = imagecolorsforindex($resampled, $cR);
					$cC = imagecolorsforindex($crop, $cC);
					extract($cR);

					if (abs($cC['red'] - $red) < 3) {
						$red = $cC['red'];
						$reColor = true;
					}
					if (abs($cC['green']  - $green) < 3) {
						$green = $cC['green'];
						$reColor = true;
					}
					if (abs($cC['blue'] - $blue) < 3) {
						$blue = $cC['blue'];
						$reColor = true;
					}

					if ($reColor) {
						$color = imagecolorallocate($resampled, $red, $green, $blue);
						imagesetpixel($resampled, $x, $y, $color);
					}
				}
			}
		}
	}

	/**
	 * fills destination image with transparent color of source image
	 *
	 * @param resource $source     source image
	 * @param resource $dest       destination image
	 * @param int      $imageType  image type constant
	 */
	protected function keepTransparent($source, $dest, $imageType) {
		if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
			if ($imageType === IMAGETYPE_GIF) {
				imagepalettecopy($source, $dest);
			}

			// get the original image's index for its transparent color
			$colorTransparent = imagecolortransparent($source);
			$numOfTotalColors = imagecolorstotal($source);

			// if there is an index in the color index range -> the image has transparency
			if ($colorTransparent >= 0 && $colorTransparent < $numOfTotalColors) {
				// get the original image's transparent color's RGB values
				$trnprtColor = imagecolorsforindex($source,  $colorTransparent);

				// allocate the same color in the new image resource
				$colorTransparent = imagecolorallocate($dest, $trnprtColor['red'], $trnprtColor['green'], $trnprtColor['blue']);

				// completely fill the background of the new image with allocated color.
				imagefill($dest, 0, 0, $colorTransparent);

				// set the background color for new image to transparent
				imagecolortransparent($dest, $colorTransparent);
			}
			elseif ($imageType === IMAGETYPE_PNG) {
				imagealphablending($dest, false);

				// create a new transparent color for image
				$color = imagecolorallocatealpha($dest, 0, 0, 0, 127);

				// completely fill the background of the new image with allocated color.
				imagefill($dest, 0, 0, $color);

				imagesavealpha($dest, true);
			}

			if ($imageType === IMAGETYPE_GIF) {
				imagetruecolortopalette($dest, true, $numOfTotalColors);
			}
		}
	}
}
