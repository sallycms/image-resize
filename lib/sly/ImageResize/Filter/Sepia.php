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

class Sepia extends Monochrome {
	public function __construct() {
		parent::__construct(1.01, 0.98, 0.90);
	}
}
