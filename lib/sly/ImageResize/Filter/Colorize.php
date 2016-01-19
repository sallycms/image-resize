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

class Colorize {
	protected $red;
	protected $green;
	protected $blue;
	protected $grayscale;

	public function __construct($red, $green, $blue, $applyGrayscaleFirst) {
		$this->red       = $red;
		$this->green     = $green;
		$this->blue      = $blue;
		$this->grayscale = $applyGrayscaleFirst;
	}

	public function filter($image) {
		if ($this->grayscale) {
			imagefilter($image, IMG_FILTER_GRAYSCALE);
		}

		imagefilter($image, IMG_FILTER_COLORIZE, $this->red, $this->green, $this->blue);

		return $image;
	}
}
