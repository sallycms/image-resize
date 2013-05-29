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

	protected function copyImageArea($image, $width, $height, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $detroySourceImage = false) {
		$target = $this->getNewImage($width, $height, $image, $this->imageType);

		// copy layer part of resized image into empty image
		imagecopy($target, $image, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH);
		if ($detroySourceImage) imagedestroy($image);

		return $target;
	}

	/**
	 * Execute the resizing based on image params
	 *
	 * @param array $sizes  width, height, crop and offset parameters
	 */
	public function resample($image, array $sizes) {
		// if we would upscale the image but upscaling is disabled, send the original file size

		if (!$this->upscalingAllowed && ($this->thumbWidth >= $this->width || $this->thumbHeight >= $this->height)) {
			$this->thumbWidth  = $this->width;
			$this->thumbHeight = $this->height;
		}

		// force thumb size to be in range [1...n]
		$this->thumbWidth  = max(1, $this->thumbWidth);
		$this->thumbHeight = max(1, $this->thumbHeight);

		// create a fresh image
		$output = $resampler->getNewImage($this->thumbWidth, $this->thumbHeight, $image, $this->imageType);

		imagecopyresampled(
			$this->imgthumb,
			$this->imgsrc,
			$this->thumbWidthOffset,
			$this->thumbHeightOffset,
			$this->widthOffset,
			$this->heightOffset,
			$this->thumbWidth,
			$this->thumbHeight,
			$this->width,
			$this->height
		);

		return $output;
	}

	/**
	 * fills destination image with transparent color of source image
	 *
	 * @param resource $source     source image
	 * @param resource $dest       destination image
	 * @param int      $imageType  image type constant
	 */
	public function keepTransparent($source, $dest, $imageType) {
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
