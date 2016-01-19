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
class Blur {
	protected $amount;

	public function __construct($amount = 1) {
		$this->amount = (int) $amount;
	}

	public function filter($img) {
		for ($i = 0; $i < $this->amount; ++$i) {
			imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
		}

		return $img;
	}
}
