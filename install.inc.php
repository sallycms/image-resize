<?php
/**
 * Image-Resize Addon
 *
 * @author office[at]vscope[dot]at Wolfgang Hutteger
 * @author <a href="http://www.vscope.at">www.vscope.at</a>
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 *
 * @author zozi@webvariants.de
 * 
 *
 * @package sally 0.3
 */

$error   = '';
$service = sly_Service_Factory::getService('AddOn');

if (!extension_loaded('gd')) {
	throw new Exception('GD-LIB-extension not available! See <a href="http://www.php.net/gd">http://www.php.net/gd</a>');
}

$pubDir = $service->publicFolder('image_resize');
if (($state = rex_is_writable($pubDir)) !== true) {
	throw new Exception($state);
}
