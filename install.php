<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

if (!extension_loaded('gd')) {
	throw new Exception('GD-LIB-extension not available! See <a href="http://www.php.net/gd">http://www.php.net/gd</a>');
}

$service = sly_Service_Factory::getAddOnService();

if (sly_Core::getVersion('X.Y') === '0.6') {
	$internalDir = $service->internalFolder('image_resize');
}
else {
	$internalDir = $service->internalDirectory('sallycms/image-resize');
}

$state = is_writable($internalDir);

if ($state !== true) {
	throw new Exception('The cache directory ('.$internalDir.') has no writing permissions.');
}
