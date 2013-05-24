<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Imageresize_Clearcache extends sly_Controller_Imageresize {
	public function indexAction() {
		$container = $this->getContainer();

		$container['sly-imageresize-service']->flushCache();
		$container['sly-flash-message']->appendInfo(t('iresize_cache_files_removed'));

		return $this->redirectResponse(array(), 'imageresize');
	}
}
