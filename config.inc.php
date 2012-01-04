<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

sly_Loader::addLoadPath(dirname(__FILE__).'/lib');

if (!sly_Core::isBackend()) {
	$dispatcher = sly_Core::dispatcher();

	$dispatcher->register(sly_Service_Asset::EVENT_PROCESS_ASSET, array('A2_Extensions', 'resizeListener'));
	$dispatcher->register(sly_Service_Asset::EVENT_REVALIDATE_ASSETS, array('A2_Extensions', 'translateListener'));
	$dispatcher->register(sly_Service_Asset::EVENT_REVALIDATE_ASSETS, array('A2_Extensions', 'translateListener'));
	$dispatcher->register('SLY_ARTICLE_OUTPUT', array('A2_Extensions', 'articleOutput'));
}
else {
	sly_Core::getI18N()->appendFile(dirname(__FILE__).'/lang');
}
