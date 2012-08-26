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
$dispatcher = sly_Core::dispatcher();

$dispatcher->register(sly_Service_Asset::EVENT_REVALIDATE_ASSETS, array('A2_Extensions', 'translateListener'));

if (!sly_Core::isBackend()) {
	$dispatcher->register(sly_Service_Asset::EVENT_PROCESS_ASSET, array('A2_Extensions', 'resizeListener'));
	$dispatcher->register('SLY_ARTICLE_OUTPUT',                   array('A2_Extensions', 'articleOutput'));
}
else {
	sly_Core::getI18N()->appendFile(dirname(__FILE__).'/lang');
	$dispatcher->register('SLY_SYSTEM_CACHES',      array('A2_Extensions', 'systemCacheList'));
	$dispatcher->register('SLY_CACHE_CLEARED',      array('A2_Extensions', 'cacheCleared'));
	$dispatcher->register('SLY_ADDONS_LOADED',      array('A2_Extensions', 'backendNavigation'));
	$dispatcher->register('ADDONS_INCLUDED',        array('A2_Extensions', 'backendNavigation')); // compat for sally 0.6
}
