<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImageResize;

use sly_Core;
use sly\ImageResize\Thumbnail\Thumbnail;

/**
 * Helper class for building/parsing virtual imageresize filenames
 */
class Filename {
	protected $filename     = null;
	protected $resizes      = array();
	protected $timestamp    = null;
	protected $uncompressed = null;
	protected $upscaling    = null;
	protected $filters      = array();
	protected $offsets      = array();
	protected $type         = null;

	public function __construct($filename) {
		$this->filename = $filename;
	}

	public static function fromMedium(\sly_Model_Medium $medium, $addUpdatedate = false) {
		$instance = new self($medium->getFilename());

		if ($addUpdatedate) {
			$instance->setTimestamp($medium->getUpdateDate());
		}

		return $instance;
	}

	public static function parse($virtualFilename) {
		$parts    = explode('/', $virtualFilename);  // 'foo/100w__mein__bar.jpg' => ['foo', '100w__mein__bar.jpg']
		$basename = array_pop($parts);               // '100w__mein__bar.jpg'
		$params   = explode('__', $basename);        // ['100w', 'mein', 'bar.jpg']
		$fnStack  = array(array_pop($params));       // ['bar.jpg']
		$params   = array_reverse($params);          // ['mein', '100w']
		$regex    = self::buildRegex();
		$found    = false;
		$file     = new self(null);

		// go through all params; as long as no known param was found, prepend the
		// chunk to the filename stack; discard unknown params within known params.

		foreach ($params as $param) {
			$match = null;

			if (preg_match($regex, $param, $match)) {
				// remove empty matches (that only occured due to their order in the regex)
				$match = array_filter($match);

				if (isset($match['resize'])) {
					$file->addResize($match['resize']);
				}

				if (isset($match['filter'])) {
					$file->addFilter($match['filter']);
				}

				if (isset($match['offset'])) {
					$file->addOffset($match['offset']);
				}

				if (isset($match['upscaling'])) {
					$file->setUpscaling($match['upscaling'] !== 'u0');
				}

				if (isset($match['uncompressed'])) {
					$file->setUncompressed(true);
				}

				if (isset($match['timestamp'])) {
					$file->setTimestamp((int) $match['timestamp']);
				}

				if (isset($match['type'])) {
					$file->setType($match['type']);
				}

				$found = true;
			}
			elseif (!$found) {
				array_unshift($fnStack, $param);
			}
		}

		// Determine the actual file from the filename stack. We cannot be sure
		// whether any of the elements in the stack are actual filename parts or
		// just typos in the param string. So we must test all combinations until
		// a good one was found, starting with the longest possibility.
		// We can skip all this if there is only one element in the stack.

		if (count($fnStack) === 1) {
			$filename = reset($fnStack);
		}
		else {
			$mediumService = sly_Core::getContainer()->get('sly-service-medium');

			do {
				$filename = implode('__', $fnStack);

				if ($mediumService->findByFilename($filename)) {
					break;
				}

				array_shift($fnStack);
			}
			while (!empty($fnStack));
		}

		$parts[] = $filename;
		$file->setFilename(implode('/', $parts));

		return $file;
	}

	public function isSpecial() {
		$filename = $this->getFilename();
		$id       = sly_Core::getContainer()->get('sly-config')->get('instname');
		$ext      = \sly_Util_File::getExtension($filename);

		return $filename === $id.'.'.$ext;
	}

	public function hasModifications() {
		return
			!empty($this->resizes) || !empty($this->filters) || !empty($this->offsets) ||
			$this->upscaling !== null || $this->uncompressed !== null || $this->timestamp !== null
		;
	}

	public function getFilename() {
		return $this->filename;
	}

	public function getExtension() {
		return \sly_Util_File::getExtension($this->getFilename());
	}

	public function getResizes() {
		return $this->resizes;
	}

	public function getTimestamp() {
		return $this->timestamp;
	}

	public function isUncompressed() {
		return $this->uncompressed;
	}

	public function getUpscaling() {
		return $this->upscaling;
	}

	public function getFilters() {
		return $this->filters;
	}

	public function getOffsets() {
		return $this->offsets;
	}

	public function getType() {
		return $this->type;
	}

	public function setFilename($filename) {
		$this->filename = $filename;

		return $this;
	}

	public function addResize($resizeCode) {
		$this->resizes[] = $resizeCode;
		$this->resizes   = array_unique($this->resizes);

		return $this;
	}

	public function clearResizes() {
		$this->resizes = array();

		return $this;
	}

	public function setTimestamp($timestamp) {
		if ($timestamp === null) {
			$this->timestamp = null;
		}
		else {
			$this->timestamp = \sly_Util_String::isInteger($timestamp) ? (int) $timestamp : strtotime($timestamp);
		}

		return $this;
	}

	public function setUncompressed($uncompressed) {
		$this->uncompressed = ($uncompressed === null) ? null : (!!$uncompressed);

		return $this;
	}

	public function setUpscaling($upscaling) {
		$this->upscaling = ($upscaling === null) ? null : (!!$upscaling);

		return $this;
	}

	public function addFilter($filter) {
		$this->filters[] = $filter;
		$this->filters   = array_unique($this->filters);

		return $this;
	}

	public function clearFilters() {
		$this->filters = array();

		return $this;
	}

	public function addOffset($offsetCode) {
		$this->offsets[] = $offsetCode;
		$this->offsets   = array_unique($this->offsets);

		return $this;
	}

	public function clearOffsets() {
		$this->offsets = array();

		return $this;
	}

	public function setType($type) {
		$types = array(
			'jpg'  => IMAGETYPE_JPEG,
			'jpeg' => IMAGETYPE_JPEG,
			'png'  => IMAGETYPE_PNG,
			'gif'  => IMAGETYPE_GIF,
			'bmp'  => IMAGETYPE_WBMP,
			'wbmp' => IMAGETYPE_WBMP
		);

		// support imagetype constants as $type
		if (is_int($type) && in_array($type, $types)) {
			$this->type = $type;
		}
		// support image name has $type
		elseif (is_string($type) && isset($types[$type])) {
			$this->type = $types[$type];
		}

		return $this;
	}

	public function getVirtualFilename() {
		$params = $this->resizes;

		foreach ($this->filters as $filter) {
			$params[] = 'f'.$filter;
		}

		foreach ($this->offsets as $offset) {
			$params[] = $offset;
		}

		if ($this->upscaling !== null) {
			$params[] = 'u'.($this->upscaling ? 1 : 0);
		}

		if ($this->uncompressed === true) {
			$params[] = 'n';
		}

		if ($this->timestamp !== null) {
			$params[] = 't'.$this->timestamp;
		}

		if ($this->type !== null) {
			$types = array(
				IMAGETYPE_JPEG => 'jpg',
				IMAGETYPE_PNG  => 'png',
				IMAGETYPE_GIF  => 'gif',
				IMAGETYPE_WBMP => 'bmp'
			);

			$params[] = 't'.$types[$this->type];
		}

		if (empty($params)) {
			return $this->getFilename();
		}

		$params = implode('__', $params);

		// Even if it's not currently possible, prepare for having sub directories
		// and hence filenames like 'mydir/foo.jpg'. In this case, prepend the
		// params to the basename: 'mydir/100w__foo.jpg'.

		$parts = explode('/', $this->getFilename());
		$last  = count($parts) - 1;

		$parts[$last] = $params.'__'.$parts[$last];

		return implode('/', $parts);
	}

	public function getUri() {
		return 'mediapool/resize/'.$this->getVirtualFilename();
	}

	public function getAbsoluteUri() {
		$request = sly_Core::getContainer()->get('sly-request');
		$baseUri = $request->getBaseUrl(true);

		return $baseUri.'/'.$this->getUri();
	}

	public function getThumbnail(array $config, $filename = null) {
		$thumb       = new Thumbnail($filename === null ? ('sly://media/'.$this->filename) : $filename);
		$upscaling   = $this->upscaling    === null ? $config['upscaling_allowed'] : $this->upscaling;
		$recompress  = $this->uncompressed === null ? $config['recompress']        : !$this->uncompressed;
		$jpegQuality = $config['jpg_quality'] ?: 85;

		if (count($this->filters) > $config['max_filters']) {
			$filters = array_slice($this->filters, 0, $config['max_filters']);
		}
		else {
			$filters = $this->filters;
		}

		$thumb->setAllowUpscaling($upscaling);
		$thumb->setFilters($filters);
		$thumb->setJpegCompress($recompress);
		$thumb->setJpegQuality($jpegQuality);

		if ($this->type !== null) {
			$thumb->setThumbType($this->type);
		}

		// construct image parameters

		$imgParams = array();

		foreach ($this->resizes as $resizeParam) {
			// check crop option
			$crop   = false;
			$prefix = substr($resizeParam, 0, 1);

			if ($prefix === 'c') {
				$crop        = true;
				$resizeParam = substr($resizeParam, 1);
			}

			$suffix = $resizeParam[strlen($resizeParam)-1];
			$value  = substr($resizeParam, 0, -1);

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
					$crop   = true;
					break;
			}

			$imgParams[$suffix] = array('value' => $value, 'crop' => $crop);
		}

		foreach ($this->offsets as $offsetParam) {
			$suffix = $offsetParam[strlen($offsetParam)-1];
			$value  = substr($offsetParam, 0, -1);

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

		$thumb->setImgParams($imgParams);

		return $thumb;
	}

	protected static function buildRegex() {
		$params = array(
			/*      resizes */ '(?P<resize>c?[0-9]{1,4}[whaxc])',
			/*      filters */ '(f(?P<filter>[a-z0-9]+))',
			/*      offsets */ '(?P<offset>-?[0-9]{1,4}[orltb])',
			/*    upscaling */ '(?P<upscaling>u[01]?)',
			/* uncompressed */ '(?P<uncompressed>n)',
			/*    timestamp */ '(t(?P<timestamp>[0-9]+))',
			/*   thumb type */ '(t(?P<type>jpg|jpeg|gif|png|bmp|wbmp))'
		);

		return '/^('.implode('|', $params).')$/';
	}
}
