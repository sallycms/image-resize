<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use sly\ImageResize\Filter;

$container['sly-classloader']->add('', __DIR__.'/lib');
$container['sly-i18n']->appendFile(__DIR__.'/lang');

$container['sly-imageresize-service'] = $container->share(function($container) {
	$cacheDir = $container['sly-service-addon']->getTempDirectory('sallycms/image-resize');
	$config   = $container['sly-service-addon']->getProperty('sallycms/image-resize', '', array());

	return new sly\ImageResize\Service($cacheDir, $config);
});

$container['sly-imageresize-listeners'] = $container->share(function($container) {
	return new sly\ImageResize\Listeners();
});

$container['sly-imageresize-filter-blur']       = function() { return new Filter\Blur(2);                    };
$container['sly-imageresize-filter-brand']      = function() { return new Filter\Brand();                    };
$container['sly-imageresize-filter-monochrome'] = function() { return new Filter\Colorize(0, 0, 0, true);    };
$container['sly-imageresize-filter-sepia']      = function() { return new Filter\Colorize(80, 50, 25, true); };
$container['sly-imageresize-filter-sharpen']    = function() { return new Filter\Sharpen();                  };

$container['sly-imageresize-filter-grey'] = $container->raw('sly-imageresize-filter-monochrome'); // just an alias

$dispatcher = $container['sly-dispatcher'];

$dispatcher->addListener('SLY_ASSETS_ROUTER',               array('%sly-imageresize-listeners%', 'assetsRouter'));
$dispatcher->addListener('SLY_ARTICLE_OUTPUT',              array('%sly-imageresize-listeners%', 'articleOutput'));
$dispatcher->addListener('SLY_SYSTEM_CACHES',               array('%sly-imageresize-listeners%', 'systemCacheList'));
$dispatcher->addListener('SLY_CACHE_CLEARED',               array('%sly-imageresize-listeners%', 'cacheCleared'));
$dispatcher->addListener('SLY_BACKEND_NAVIGATION_INIT',     array('%sly-imageresize-listeners%', 'backendNavigation'));
$dispatcher->addListener('SLY_BACKEND_MEDIAPOOL_THUMBNAIL', array('%sly-imageresize-listeners%', 'mediapoolThumbnail'));
$dispatcher->addListener('SLY_MODEL_MEDIUM_RESIZE',         array('%sly-imageresize-listeners%', 'resizeMedium'));

// notify other addOns that we have set our listeners
$dispatcher->notify('SLY_IMAGERESIZE_INIT', $container);
