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

/**
 * Calculate image sizes
 */
class Resizer {
	// flags
	protected $upscale = false;

	// dimensions
	protected $origWidth    = 0;
	protected $origHeight   = 0;
	protected $width        = 0;
	protected $height       = 0;
	protected $widthOffset  = null;
	protected $heightOffset = null;

	// thumbnail dimensions and stuff
	protected $thumbWidth        = null;
	protected $thumbHeight       = null;
	protected $thumbWidthOffset  = null;
	protected $thumbHeightOffset = null;

	public function __construct($width, $height, $upscalingAllowed) {
		$this->origWidth  = (int) $width;
		$this->origHeight = (int) $height;
		$this->upscale    = !!$upscalingAllowed;

		$this->reset();
	}

	protected function reset() {
		$this->width  = $this->origWidth;
		$this->height = $this->origHeight;

		$this->widthOffset  = null;
		$this->heightOffset = null;

		$this->thumbWidth        = $this->origWidth;
		$this->thumbHeight       = $this->origHeight;
		$this->thumbWidthOffset  = null;
		$this->thumbHeightOffset = null;
	}

	/**
	 * Execute the resizing based on image params
	 *
	 * @param array $imageParams  width, height, crop and offset parameters
	 */
	public function calculateSizes(array $imageParams) {
		$this->reset();

		// resize to square
		if (isset($imageParams['auto'])) {
			$this->resizeBoth($imageParams['auto'], $imageParams['auto']);
		}

		// resize width
		elseif (isset($imageParams['width'])) {
			// and resize height
			if (isset($imageParams['height'])) {
				$this->resizeBoth($imageParams['width'], $imageParams['height']);
			}
			// just resize width
			else {
				$this->resizeWidth($imageParams['width']);
			}
		}

		// resize height
		elseif (isset($imageParams['height'])) {
			$this->resizeHeight($imageParams['height']);
		}

		// return a strut containing all size values
		$result = new \stdClass();

		$result->origWidth  = $this->origWidth;
		$result->origHeight = $this->origHeight;
		$result->width      = $this->width;
		$result->height     = $this->height;

		$result->widthOffset  = $this->widthOffset;
		$result->heightOffset = $this->heightOffset;

		$result->thumbWidth        = $this->thumbWidth;
		$result->thumbHeight       = $this->thumbHeight;
		$result->thumbWidthOffset  = $this->thumbWidthOffset;
		$result->thumbHeightOffset = $this->thumbHeightOffset;

		return $result;
	}

	/**
	 * Resize the dimensions just by the height
	 *
	 * @param array $heightParams  {value: 42}
	 */
	private function resizeHeight(array $heightParams) {
		if (!is_array($heightParams) || !isset($heightParams['value'])) {
			return false;
		}

		$size = (int) $heightParams['value'];

		if ($this->origHeight < $size && !$this->upscale) {
			$size = $this->origHeight;
		}

		$this->thumbHeight = $size;
		$this->thumbWidth  = (int) round($this->origWidth / $this->origHeight * $this->thumbHeight);
	}

	/**
	 * Resize the dimensions just by the width
	 *
	 * @param array $widthParams  {value: 42}
	 */
	private function resizeWidth(array $widthParams) {
		if (!is_array($widthParams) || !isset($widthParams['value'])) {
			return false;
		}

		$size = (int) $widthParams['value'];

		// handle upscaling by optionally capping the resize width
		if ($this->origWidth < $size && !$this->upscale) {
			$size = $this->origWidth;
		}

		$this->thumbWidth  = $size;
		$this->thumbHeight = (int) ($this->origHeight / $this->origWidth * $this->thumbWidth);
	}

	/**
	 * Resize by both width and height
	 *
	 * @param array $width   thumbnail width
	 * @param array $height  thubmnail height
	 */
	private function resizeBoth(array $width, array $height) {
		if (!isset($width['value']) || !isset($height['value'])) {
			return false;
		}

		$imgRatio    = $this->origWidth / $this->origHeight;
		$resizeRatio = $width['value'] / $height['value'];

		// if image ratio is wider than thumb ratio
		if ($imgRatio > $resizeRatio) {
			// if image should be cropped
			if (isset($width['crop']) && $width['crop']) {
				// resize height
				$this->resizeHeight($height);

				// crop width

				// set new cropped width from original image
		  		$this->width = (int) round($resizeRatio * $this->origHeight);

				// set width to crop width
				$this->thumbWidth = (int) $width['value'];

				// if original height is smaller than resize height
				if ($this->origHeight < $height['value']) {
					// and image get not upscaled in height
					if ($this->thumbHeight < $height['value']) {
						// and original width is larger than resize width
						if ($this->origWidth >= $width['value']) {
							// set crop window width to resize width
							$this->width = $width['value'];
						}

						// else do not crop width
						else {
							$this->width      = $this->origWidth;
							$this->thumbWidth = $this->width;
						}
					}
				}

				// right offset
				if (isset($width['offset']['right']) && is_numeric($width['offset']['right'])) {
					$this->widthOffset = (int) ($this->origWidth - $this->width - ($this->origHeight / $this->thumbHeight * $width['offset']['right']));
				}

				// left offset
				elseif (isset($width['offset']['left']) && is_numeric($width['offset']['left'])) {
					$this->widthOffset = (int) $width['offset']['left'];
				}

				// set offset to center image
				else {
					$this->widthOffset = (int) (floor($this->origWidth - $this->width) / 2);
				}
			}

			// else resize into bounding box
			else {
				$this->resizeWidth($width);
			}
		}

		// else image ratio is less wide than thumb ratio
		else {
			// if image should be cropped
			if (isset($height['crop']) && $height['crop']) {
				// resize width
				$this->resizeWidth($width);

				// crop height

				// set new cropped width from original image
		  		$this->height = (int) round($this->origWidth / $resizeRatio);

				// set height to crop height
				$this->thumbHeight = (int) $height['value'];

				// if original width is smaller than resize width
				if ($this->origWidth < $width['value']) {
					// and image get not upscaled in width
					if ($this->thumbWidth < $width['value']) {
						// and original height is larger than resize height
						if ($this->origHeight >= $height['value']) {
							// set crop window height to resize height
							$this->height = $height['value'];
						}

						// else do not crop height
						else {
							$this->height      = $this->origHeight;
							$this->thumbHeight = $this->height;
						}
					}
				}

				// bottom offset
				if (isset($height['offset']['bottom']) && is_numeric($height['offset']['bottom'])) {
					$this->heightOffset = (int) ($this->origHeight - $this->height - ($this->origWidth / $this->thumbWidth * $height['offset']['bottom']));
				}

				// top offset
				elseif (isset($height['offset']['top']) && is_numeric($height['offset']['top'])) {
					$this->heightOffset = (int) $height['offset']['top'];
				}

				// set offset to center image
				else {
					$this->heightOffset = (int) (floor($this->origHeight - $this->height) / 2);
				}

			}

			// else resize into bounding box
			else {
				$this->resizeHeight($height);
			}
		}
	}
}
