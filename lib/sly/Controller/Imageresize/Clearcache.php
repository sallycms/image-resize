<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Imageresize_Clearcache extends sly_Controller_Imageresize {
	protected function index() {
		$c   = A2_Thumbnail::deleteCache();
		$msg = t('iresize_cache_files_removed', $c);
		if (!empty($msg)) print rex_info($msg);

		$this->render('addons/image_resize/views/index.phtml');
	}
}
