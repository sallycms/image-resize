<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImageResize\Thumbnail;

use sly\ImageResize\Exception;
use sly\ImageResize\GIF\Decoder;
use sly\ImageResize\GIF\Encoder;
use sly\ImageResize\Util;

/**
 * Image Thumbnail
 *
 * @author zozi@webvariants.de
 * @author memento@webvariants.de
 */
class Thumbnail {
	// input file
	protected $fileName = '';

	// manipulations
	protected $filters   = array();
	protected $imgParams = array();

	// options / flags
	protected $quality          = 100;
	protected $imageType        = null;
	protected $compressJPG      = true;
	protected $upscalingAllowed = false;

	// internal objects
	protected $imgsrc    = null;
	protected $imgthumb  = null;

	// dimensions
	protected $origWidth    = 0;
	protected $origHeight   = 0;
	protected $width        = 0;
	protected $height       = 0;
	protected $widthOffset  = 0;
	protected $heightOffset = 0;

	// thumbnail dimensions and stuff
	protected $thumbWidth        = 0;
	protected $thumbHeight       = 0;
	protected $thumbWidthOffset  = 0;
	protected $thumbHeightOffset = 0;
	protected $thumbQuality      = 85;
	protected $thumbType         = null;

	public function __construct($imgfile) {
		$this->fileName = $imgfile;

		if (!file_exists($this->fileName)) {
			throw new Exception('File '.$this->fileName.' does not exist.', 404);
		}

		// determine image type

		$this->imageType = Util::getSupportedImageType($imgfile);
		$this->thumbType = $this->imageType;

		if (!$this->imageType) {
			throw new Exception('File '.$this->fileName.' is not a supported image type.', 406);
		}
	}

	/**
	 * set if image upscaling is allowed
	 *
	 * @param  boolean $flag
	 * @return Thumbnail      reference to self
	 */
	public function setAllowUpscaling($flag) {
		$this->upscalingAllowed = (bool) $flag;
		return $this;
	}

	/**
	 * set if jpeg files should be recompressed
	 *
	 * @deprecated  use setJpegCompress() instead
	 *
	 * @param  boolean $flag
	 * @return Thumbnail      reference to self
	 */
	public function setJpgCompress($flag) {
		$this->setJpegCompress($flag);
		return $this;
	}

	/**
	 * set if jpeg files should be recompressed
	 *
	 * @param  boolean $flag
	 * @return Thumbnail      reference to self
	 */
	public function setJpegCompress($flag) {
		$this->compressJPG = (bool) $flag;
		return $this;
	}

	/**
	 * set the jpeg quality for resampling
	 *
	 * @param  int $quality
	 * @return Thumbnail     reference to self
	 */
	public function setJpegQuality($quality) {
		$this->thumbQuality = (int) $quality;
		return $this;
	}

	/**
	 * set the thumbnail image type
	 *
	 * @throws Exception
	 * @param  int $type  one of the IMAGETYPE_* constants
	 * @return Thumbnail  reference to self
	 */
	public function setThumbType($type) {
		if (!in_array($type, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WBMP))) {
			throw new Exception('The image type '.$type.' is not supported by this system.', 500);
		}

		$this->thumbType = $type;

		return $this;
	}

	/**
	 * set image manipulation parameters
	 *
	 * @param  array $params
	 * @return Thumbnail      reference to self
	 */
	public function setImgParams(array $params) {
		$this->imgParams = $params;
		return $this;
	}

	/**
	 * Set the list of filters to apply to the image
	 */
	public function setFilters(array $filters) {
		$this->filters = array_unique(array_filter($filters));
		return $this;
	}

	/**
	 * Generate the thumbnail
	 *
	 * @param  string $outputFile  full path to the output file
	 * @return string
	 */
	public function generate($outputFile) {
		// detemine whether we need to handle an animated GIF or can go the easy route
		$imgData  = file_get_contents($this->fileName);
		$animated = false;
		$gif      = null;

		if ($this->imageType === IMAGETYPE_GIF) {
			// set quality to zero, so that GIFs are not recompressed, if not resized or filtered
			// not to be confused with the thumbnail's JPEG quality
			$this->quality = 0;

			if (Decoder::isAnimated($this->fileName)) {
				$animated = true;
				$gif      = new Decoder($imgData);
				$frames   = $gif->getFrames();

				if (empty($frames)) {
					throw new Exception('Could not read GIF file.', 406);
				}

				// use the first frame to determine the image size
				$imgData = $frames[0];
			}
		}

		// open image file and determine dimentions
		$image  = imagecreatefromstring($imgData);
		$width  = imagesx($image);
		$height = imagesy($image);

		// create all the sizes, offsets and stuff
		$resizer = new Resizer($width, $height, $this->upscalingAllowed);
		$sizes   = $resizer->calculateSizes($this->imgParams);

		// NOP
		if (!$this->hasModifications($sizes)) {
			copy($this->fileName, $outputFile);
			return $outputFile;
		}

		$resampler = new Resampler();

		// export animated GIF frame by frame
		if ($animated) {
			$this->processAnimatedGif($gif, $resampler, $image, $sizes, $outputFile);
		}
		// process normal file
		else {
			$outputFile = $this->processImage($resampler, $image, $sizes, $outputFile);
		}

		return $outputFile;
	}

	/**
	 * Resample and filter a regular, non animated image
	 *
	 * @param  Resampler $resampler
	 * @param  resource  $image
	 * @param  stdClass  $sizes
	 * @param  string    $outputFile
	 * @return string
	 */
	protected function processImage(Resampler $resampler, $image, \stdClass $sizes, $outputFile) {
		$thumbnail = $resampler->resample($image, $sizes, $this->imageType);
		$thumbnail = $this->applyFilters($thumbnail);
		$ext       = \sly_Util_File::getExtension($outputFile);

		switch ($this->thumbType) {
			case IMAGETYPE_JPEG:
				if ($ext !== 'jpg' && $ext !== 'jpeg') $outputFile .= '.jpg';
				imageinterlace($thumbnail, true); // set to progressive mode
				imagejpeg($thumbnail, $outputFile, $this->thumbQuality);
				break;

			case IMAGETYPE_PNG:
				if ($ext !== 'png') $outputFile .= '.png';
				imagepng($thumbnail, $outputFile);
				break;

			case IMAGETYPE_GIF:
				if ($ext !== 'gif') $outputFile .= '.gif';
				imagegif($thumbnail, $outputFile);
				break;

			case IMAGETYPE_WBMP:
				if ($ext !== 'bmp') $outputFile .= '.bmp';
				imagewbmp($thumbnail, $outputFile);
				break;
		}

		imagedestroy($thumbnail);

		return $outputFile;
	}

	/**
	 * Resample and filter an animated GIF frame by frame
	 *
	 * @param Decoder   $gif
	 * @param Resampler $resampler
	 * @param resource  $image
	 * @param stdClass  $sizes
	 * @param string    $outputFile
	 */
	public function processAnimatedGif(Decoder $gif, Resampler $resampler, $image, \stdClass $sizes, $outputFile) {
		$gifDelays   = $gif->getDelays();
		$gifFrames   = $gif->getFrames();
		$gifLoop     = $gif->getLoop();
		$gifDisposal = $gif->getDisposal();
		$gifOffsets  = $gif->getOffset();
		$gifTransR   = $gif->getTransparentR();
		$gifTransG   = $gif->getTransparentG();
		$gifTransB   = $gif->getTransparentB();

		if (!is_array($gifFrames) || count($gifFrames) <= 0) {
			throw new Exception('Could not read GIF file.', 406);
		}

		if ($sizes->widthOffset > 0) {
			$scalingFactor = $sizes->thumbHeight / $sizes->origHeight;
		}
		else {
			$scalingFactor = $sizes->thumbWidth / $sizes->origWidth;
		}

		$widthOffset  = (int) round($scalingFactor * $sizes->widthOffset);
		$heightOffset = (int) round($scalingFactor * $sizes->heightOffset);

		$gifData = array();

		for ($i = 0; $i < count($gifFrames); ++$i) {
			// get layer as image stream
			$frame = imagecreatefromstring($gifFrames[$i]);

			if (!$frame) {
				throw new Exception('Could not create a valid image source for GIF frame #'.$i.'.');
			}

			$sLWidth   = imagesx($frame);
			$sLHeight  = imagesy($frame);
			$gifOffset = isset($gifOffsets[$i]) ? $gifOffsets[$i] : null;

			// if layer has an offset relative to gif canvas, set image offsets to zero
			if ($gifOffset && ($sLWidth < $sizes->origWidth || $sLHeight < $sizes->origHeight)) {
				$frame = $resampler->copyImageArea($resampler, $frame,
					/*   width */ $sLThumbWidth,
					/*  height */ $sLThumbHeight,
					/*  destXY */ $gifOffset[0], $gifOffset[1],
					/*   srcXY */ 0, 0,
					/*   srcWH */ $sLWidth, $sLHeight,
					/*    type */ $this->imageType,
					/* destroy */ true
				);

				// resize layer
				$frame = $resampler->resample($frame, $sizes, $this->imageType);

				// calculate offsets and size of resized layer part
				$sLThumbWidth  = max(1, (int) round($scalingFactor * $sLWidth));
				$sLThumbHeight = max(1, (int) round($scalingFactor * $sLHeight));

				$gifOffset[0] = (int) round($scalingFactor * $gifOffset[0]);
				$gifOffset[1] = (int) round($scalingFactor * $gifOffset[1]);

				// adjust width offset if image gets cropped
				if ($widthOffset > 0) {
					// if layer gets cropped
					if ($widthOffset > $gifOffset[0]) {
						$sLThumbWidth = max(1, $sLThumbWidth - ($widthOffset - $gifOffset[0]));
					}

					$gifOffset[0] = max(0, $gifOffset[0] - $widthOffset);
				}

				// adjust height offset if image gets cropped
				if ($heightOffset > 0) {
					// if layer gets cropped
					if ($heightOffset > $gifOffset[1]) {
						$sLThumbHeight = max(1, $sLThumbHeight - ($heightOffset - $gifOffset[1]));
					}

					$gifOffset[1] = max(0, $gifOffset[1] - $heightOffset);
				}

				$frame = $resampler->copyImageArea($resampler, $frame,
					/*   width */ $sLThumbWidth,
					/*  height */ $sLThumbHeight,
					/*  destXY */ 0, 0,
					/*   srcXY */ $gifOffset[0], $gifOffset[1],
					/*   srcWH */ $sLThumbWidth, $sLThumbHeight,
					/*    type */ $this->imageType,
					/* destroy */ true
				);

				// remember the changes to the offsets
				$gifOffsets[$i] = $gifOffset;
			}

			// no offsets, just resample image
			else {
				$frame = $resampler->resample($frame, $sizes, $this->imageType);
			}

			$frame = $this->applyFilters($frame);

			ob_start();
			imagegif($frame);
			$gifData[] = ob_get_clean();
			imagedestroy($frame);
		}

		$output = new Encoder(
			$gifData,
			$gifDelays,
			$gifLoop,
			$gifDisposal,
			$gifTransR, $gifTransG, $gifTransB,
			$gifOffsets,
			'bin'
		);

		file_put_contents($outputFile, $output->getAnimation());
	}

	/**
	 * apply all requested filters to the image
	 *
	 * @param  resource $image
	 * @return resource
	 */
	protected function applyFilters($image) {
		$container = sly_Core::getContainer();

		foreach ($this->filters as $filter) {
			$filter = preg_replace('#[^a-z0-9-]#i', '', $filter);
			$filter = 'sly-imageresize-filter-'.str_replace('_', '-', $filter);

			if (isset($container[$filter])) {
				$filter = $container[$filter];
				$return = $filter->filter($image);

				if ($return) {
					$image = $return;
				}

				unset($return);
			}
		}

		return $image;
	}

	/**
	 * determine whether the image needs to be modified or not
	 *
	 * @return boolean
	 */
	protected function hasModifications(\stdClass $sizes) {
		$sameImageType        = $this->imageType == $this->thumbType;
		$thumbLargerThanImage = $sizes->thumbWidth > $sizes->width || $sizes->thumbHeight > $sizes->height;

		// compare image quality only for JPEGs
		$imageQualityTooLow   = $this->imageType != IMAGETYPE_JPEG || !$this->compressJPG || $this->thumbQuality >= $this->quality;
		$noFilters            = empty($this->filters);

		// if no filter are applied, size is smaller or equal and quality is lower than desired
		if ($sameImageType && $noFilters && (!$this->upscalingAllowed && $thumbLargerThanImage) && $imageQualityTooLow) {
			return false;
		}

		return true;
	}
}
