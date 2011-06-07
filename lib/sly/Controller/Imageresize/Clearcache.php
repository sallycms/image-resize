<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Imageresize_Clearcache extends sly_Controller_Imageresize {
	protected function index() {
		sly_Service_Factory::getAssetService()->clearCache(array());
		print rex_info(t('iresize_cache_files_removed'));
		$this->render('addons/image_resize/views/index.phtml');
	}
}
