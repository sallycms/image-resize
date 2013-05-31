<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Assets\Controller;

use sly_Response;
use sly\ImageResize\Filename;
use sly\Assets\Service as AssetService;

class Imageresize extends Base {
	public function resizeAction() {
		$request = $this->getRequest();
		$file    = $this->normalizePath(urldecode($request->get('file', 'string', null)));

		// validate the filename

		if (mb_strlen($file) === 0) {
			return new sly_Response('no file given', 400);
		}

		// add our process listener (do it here to remember the requested filename)

		$container = $this->getContainer();
		$service   = $container['sly-imageresize-service'];
		$filename  = Filename::parse($file);
		$fullName  = $filename->isSpecial() ? $service->getSpecialImage() : 'sly://media/'.$filename->getFilename();

		if ($filename->hasModifications()) {
			$dispatcher = $container->getDispatcher();
			$listeners  = $container['sly-imageresize-listeners'];

			$dispatcher->addListener(AssetService::EVENT_PROCESS_ASSET, array($listeners, 'processAsset'), compact('filename'));
		}

		// send the requested file, we will perform the manipulations later in the
		// process event from the asset service

		return $this->sendFile($fullName, true, true, true);
	}
}
