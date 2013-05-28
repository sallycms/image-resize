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

use Gaufrette\Util\Path;
use sly_Response;
use sly\ImageResize\Filename;
use sly\ImageResize\Service;
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

		$filename = Filename::parse($file);

		if ($filename->hasModifications()) {
			$container  = $this->getContainer();
			$dispatcher = $container->getDispatcher();
			$listeners  = $container->get('sly-imageresize-listeners');

			$dispatcher->addListener(AssetService::EVENT_PROCESS_ASSET, array($listeners, 'processAsset'), compact('filename'));
		}

		// send the requested file, we will perform the manipulations later in the
		// process event from the asset service

		return $this->sendFile('sly://media/'.$filename->getFilename(), true, true, true);
	}
}
