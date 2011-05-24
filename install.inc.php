<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

if (!extension_loaded('gd')) {
	throw new Exception('GD-LIB-extension not available! See <a href="http://www.php.net/gd">http://www.php.net/gd</a>');
}

if (version_compare(sly_Core::getVersion('X.Y.Z'), '0.4.2', '<')) {
	throw new Exception('This version requires at least Sally 0.4.2.');
}

$service = sly_Service_Factory::getAddOnService();
$pubDir  = $service->publicFolder('image_resize');
$state   = is_writable($pubDir);

if ($state !== true) {
	throw new Exception('The cache directory ('.$pubDir.') has no writing permissions.');
}
