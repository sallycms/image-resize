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

		$filename    = Filename::parse($file);
		$container   = $this->getContainer();
		$dispatcher  = $container->getDispatcher();
		$service     = $container->get('sly-imageresize-service');
		$jsonService = $container->get('sly-service-json');
		$controlFile = $service->getControlFile($filename->getFilename());

		if ($filename->hasModifications()) {
			$dispatcher->addListener(AssetService::EVENT_PROCESS_ASSET, function($inputFile) use ($filename, $service, $jsonService, $controlFile) {
				$controlData = file_exists($controlFile) ? $jsonService->load($controlFile, false, true) : array();

				// prepare the thumbnail

				$config    = $service->getConfig(null);
				$thumbnail = $filename->getThumbnail($config, $inputFile);
				$realName  = $filename->getFilename();

				// remove old cache files

				if (count($controlData) >= $config['max_cachefiles'] && !in_array($realName, $controlData)) {
					// TODO
				}

				// process the image

				$tmpDir  = $service->getCacheDir();
				$tmpFile = $tmpDir.'/tmp_'.sha1($filename->getVirtualFilename()).'.'.$filename->getExtension();

				$thumb->generateImage($tmpFile);

				$controlData[] = $realName;
				$jsonService->dump($controlFile, $controlData);

				return $tmpFile;
			});
		}

		// send the requested file, we will perform the manipulations later in the
		// process events from the asset service

		return $this->sendFile('sly://media/'.$filename->getFilename(), true, true, true);
	}
}
