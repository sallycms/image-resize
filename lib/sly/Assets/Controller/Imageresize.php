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

class Imageresize extends Base {

	// c=crop before w=width|h=heigth|a=both followed by o=offset|r=right|l=left|t=top|b=bottom
	// followed by f=filter followed by u=upscaling
	private static $godRegex = '@((?:c?[0-9]{1,4}[whaxc]__){1,2}(?:\-?[0-9]{1,4}[orltb]?__){0,2}(?:f[a-z0-9]+__)*(?:u[01]?__)?(?:n__)?(?:t[0-9]+__)?)(.*)$@';


	public function resizeAction() {
		$request = $this->getRequest();
		$file    = $this->normalizePath(urldecode($request->get('file', 'string', null)));

		// validate the filename

		if (mb_strlen($file) === 0) {
			return new sly_Response('no file given', 400);
		}

		// and send the file

		return $this->sendFile('sly://media/'.$file, false, true, true);
	}





	/**
	 * Verarbeitet ein vom Asset Cache angegebene Bild
	 *
	 * @param  array $params
	 * @return string
	 */
	public static function resizeListener(array $params) {
		$filename = $params['subject'];

		if (!self::filePathOk($filename)) {
			return $filename;
		}
		// data/mediapool/c100w__c200h__20r__20t__filename.jpg

		// separate filename and parameters (x and c in whaxc are just for backwards compatibility)
		$result = self::parseFilename($filename);
		if ($result === null) return $filename;

		// get parts
		$imageFile = self::getImageFile($result['filename']);
		$params    = $result['params'];

		$intDir      = A2_Util::getInternalDirectory();
		$controlFile = self::getControlFile($imageFile);
		$controlData = file_exists($controlFile) ? json_decode(file_get_contents($controlFile), true) : array();

		// handle max_cachefiles for this image
		if (count($controlData) >= A2_Util::getProperty('max_cachefiles') && !in_array($filename, $controlData)) {
			$assetService = sly_Service_Factory::getAssetService();

			if (is_callable(array($assetService, 'removeCacheFiles'))) {
				//remove first created rezized file from asset service
				$assetService->removeCacheFiles(array_shift($controlData));
			}
			else {
				//this is a fallback for pre Sally 0.6.7 only
				//remove all resized files
				$controlData = array();
				unlink($controlFile);
				$assetService->validateCache();
			}
		}

		// iterate parameters
		$imgParams        = array();
		$filters          = array();
		$upscalingAllowed = (bool) A2_Util::getProperty('upscaling_allowed', false);
		$recompress       = (bool) A2_Util::getProperty('recompress', true);
		$jpegQuality      = (int) A2_Util::getProperty('jpg_quality', 85);
		$type             = null;

		foreach ($params as $param) {
			// check crop option
			$crop   = false;
			$prefix = substr($param, 0, 1);

			if ($prefix == 'c') {
				$crop = true;
				$param = substr($param, 1);
			}
			// check filter option
			elseif ($prefix == 'f') {
				$filters[] = substr($param, 1);
				continue;
			}
			// check upscale option
			elseif ($prefix == 'u') {
				if (strlen($param) < 2 || substr($param, 1, 1) === '1') {
					$upscalingAllowed = true;
				}
				continue;
			}
			// n for no compression
			elseif ($prefix == 'n') {
				$recompress = false;
				continue;
			}
			elseif ($prefix == 't') {
				$imageType = substr($param, 1);
				if (in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WBMP))) {
					$type = $imageType;
				}
				continue;
			}

			// identify type
			$suffix = substr($param, strlen($param)-1);
			// get value
			$value = substr($param, 0, strlen($param)-1);

			// set parameters for resizing (x and c just for backwards compatibility)
			if (in_array($suffix, array('w', 'h', 'a', 'x', 'c'))) {
				switch ($suffix) {
					case 'w':
						$suffix = 'width';
						break;

					case 'h':
						$suffix = 'height';
						break;

					case 'a':
						$suffix = 'auto';
						break;

					case 'x':
					case 'c':
						$suffix = 'width';
						$crop = true;
						break;
				}

				$imgParams[$suffix] = array('value' => $value, 'crop' => ($crop));
			}

			// set parameters for crop offset
			if (in_array($suffix, array('o', 'r', 'l', 't', 'b'))) {
				switch ($suffix) {
					case 'o':
						$imgParams['width']['offset']['left'] = $value;
						$imgParams['height']['offset']['top'] = $value;
						break;

					case 'r':
						$imgParams['width']['offset']['right'] = $value;
						break;

					case 'l':
						$imgParams['width']['offset']['left'] = $value;
						break;

					case 't':
						$imgParams['height']['offset']['top'] = $value;
						break;

					case 'b':
						$imgParams['height']['offset']['bottom'] = $value;
						break;
				}
			}
		}

		$time = time();

		//clean up old tmp_ files
		$tmp_files = glob($intDir.'/tmp_*');
		if ($tmp_files)
			foreach ($tmp_files as $filename_i) {
				preg_match('#/tmp_(\d+)_[^/]+#', $filename_i, $matches);
				if ($matches && $matches[1] < ($time - 120)) {
					@unlink ($filename_i);
				}
			}

		try {
			$thumb = new A2_Thumbnail($imageFile);
			$thumb->setAllowUpscaling($upscalingAllowed);
			$thumb->setImgParams($imgParams);
			$thumb->setNewSize();
			$thumb->addFilters($filters);
			$thumb->setJpgCompress($recompress);
			$thumb->setJpegQuality($jpegQuality);
			if ($type !== null) $thumb->setThumbType($type);

			$tmpFile = $intDir.'/tmp_' . $time . '_' .md5($filename).'.'.sly_Util_String::getFileExtension($imageFile);

			$thumb->generateImage($tmpFile);

			$controlData[] = $filename;
			file_put_contents($controlFile, json_encode($controlData));
		}
		catch (Exception $e) {
			switch ($e->getCode()) {
				case 404:
					// file not found (...)
					header('HTTP/1.0 404 Not Found');
					break;

				case 406:
					// unsupported file type
					header('HTTP/1.0 406 Not Acceptable');
					break;

				case 500:
					// bad image file
					header('HTTP/1.0 500 Internal Server Error');
					break;

				case 501:
					// animated GIF -> return the original filename
					return $imageFile;
			}

			die;
		}

		return $tmpFile;
	}
}
