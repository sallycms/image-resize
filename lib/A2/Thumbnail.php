<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Image-Resize AddOn
 *
 * @author office[at]vscope[dot]at Wolfgang Hutteger
 * @author <a href="http://www.vscope.at">www.vscope.at</a>
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 * @author zozi@webvariants.de
 *
 * @author memento@webvariants.de
 */
class A2_Thumbnail {
	private $fileName = '';

	private $imgsrc   = null;
	private $imgthumb = null;

	private $filters = array();

	private $origWidth    = 0;
	private $origHeight   = 0;
	private $width        = 0;
	private $height       = 0;
	private $widthOffset  = 0;
	private $heightOffset = 0;
	private $quality      = 100;
	private $imageType    = null;

	private $imgParams = array();

	private $gifObject    = null;
	private $isAnimated   = false;

	private $iccProfile   = null;

	private $thumbWidth        = 0;
	private $thumbHeight       = 0;
	private $thumbWidthOffset  = 0;
	private $thumbHeightOffset = 0;
	private $thumbQuality      = 85;
	private $thumbType         = null;
	private $compressJPG       = true;

	private $upscalingAllowed = false;

	public function __construct($imgfile) {
		$this->fileName = $imgfile;

		if (!file_exists($this->fileName)) {
			throw new Exception('File '.$this->fileName.' does not exist.', 404);
		}

		$this->imageType = $this->getImageType();
		$this->thumbType = $this->imageType;

		if (!$this->imageType) {
			throw new Exception('File is not a supported image type.', 406);
		}

		$data = file_get_contents($this->fileName);

		if ($this->imageType == IMAGETYPE_GIF) {
			// special handling for animated gifs
			if (self::isAnimatedGIF($this->fileName)) {
				$this->isAnimated = true;

				$this->gifObject = new A2_GIF_Decoder($data);
				$frames = $this->gifObject->getFrames();
				if (!is_array($frames) || count($frames) <= 0) self::sendError();
				$data = $frames[0];
			}
			// set quality to zero, so that gifs are not recompressed, if not resized or filtered
			$this->quality = 0;
		}
		elseif ($this->imageType === IMAGETYPE_JPEG) {
			$this->iccProfile = new A2_ICC_JPEG();
			$this->iccProfile->LoadFromJPEG($this->fileName);
		}

		$this->imgsrc = imagecreatefromstring($data);

		if (!$this->imgsrc) {
				throw new Exception('Can not create valid Image Source.', 500);
		}

		$this->origWidth  = imagesx($this->imgsrc);
		$this->origHeight = imagesy($this->imgsrc);

		$this->width      = $this->origWidth;
		$this->height     = $this->origHeight;

	}

	/**
	 * Baut das Thumbnail
	 */
	private function resampleImage() {
		// Originalbild selbst sehr klein und wuerde via resize vergrößert
		// => Das Originalbild ausliefern

		if (!$this->upscalingAllowed
			&& ($this->thumbWidth >= $this->width || $this->thumbHeight >= $this->height)) {

			$this->thumbWidth  = $this->width;
			$this->thumbHeight = $this->height;
		}
		$this->thumbWidth  = max(1, $this->thumbWidth);
		$this->thumbHeight = max(1, $this->thumbHeight);

		if (function_exists('imagecreatetruecolor')/* && $this->imageType != IMAGETYPE_GIF && !$this->isAnimated*/) {
			$this->imgthumb = @imagecreatetruecolor($this->thumbWidth, $this->thumbHeight);
		}
		else {
			$this->imgthumb = @imagecreate($this->thumbWidth, $this->thumbHeight);
		}

		if (!$this->imgthumb) {
			throw new Exception('Can not create valid Thumbnail Image', 500);
		}

		// Transparenz erhalten

		$this->keepTransparent($this->imageType, $this->imgsrc, $this->imgthumb);

		imagecopyresampled(
			$this->imgthumb,
			$this->imgsrc,
			$this->thumbWidthOffset,
			$this->thumbHeightOffset,
			$this->widthOffset,
			$this->heightOffset,
			$this->thumbWidth,
			$this->thumbHeight,
			$this->width,
			$this->height
		);
//		var_dump($this->imgthumb);
	}

	/**
	 * fills destination image with transparent color of source image
	 *
	 * @param type $imageType  image type constant
	 * @param type $imgsrc     source image
	 * @param type $imgthumb   destination image
	 */
	private function keepTransparent($imageType, $imgsrc, $imgthumb) {
		if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
			if ($imageType == IMAGETYPE_GIF) {
				imagepalettecopy($imgsrc, $imgthumb);
			}

			// get the original image's index for its transparent color
			$colorTransparent = imagecolortransparent($imgsrc);
			$numOfTotalColors = imagecolorstotal($imgsrc);

			// if there is an index in the color index range -> the image has transparency
			if ($colorTransparent >= 0 && $colorTransparent < $numOfTotalColors) {

				// Get the original image's transparent color's RGB values
				$trnprt_color = imagecolorsforindex($imgsrc,  $colorTransparent);

				// Allocate the same color in the new image resource
				$colorTransparent = imagecolorallocate($imgthumb, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);

				// Completely fill the background of the new image with allocated color.
				imagefill($imgthumb, 0, 0, $colorTransparent);

				// Set the background color for new image to transparent
				imagecolortransparent($imgthumb, $colorTransparent);
			}
			elseif ($imageType == IMAGETYPE_PNG) {
				imagealphablending($imgthumb, false);

				// Create a new transparent color for image
				$color = imagecolorallocatealpha($imgthumb, 0, 0, 0, 127);

				// Completely fill the background of the new image with allocated color.
				imagefill($imgthumb, 0, 0, $color);

				imagesavealpha($imgthumb, true);
			}

			if ($imageType == IMAGETYPE_GIF) {
				imagetruecolortopalette($imgthumb, true, $numOfTotalColors);
			}
		}
	}

	/**
	 * determine whether the image needs to be modified or not
	 *
	 * @return boolean
	 */
	private function imageGetsModified() {

		// if ($this->imageType == IMAGETYPE_GIF && self::isAnimatedGIF($this->fileName)) {
		// 	return false;
		// }

		$sameImageType        = $this->imageType == $this->thumbType;
		$thumbLargerThanImage = $this->thumbWidth > $this->width || $this->thumbHeight > $this->height;
		// compare image quality only for jpeg
		$imageQualityTooLow   = $this->imageType != IMAGETYPE_JPEG || !$this->compressJPG || $this->thumbQuality >= $this->quality;
		$noFilters            = empty($this->filters);

		// if no filter are applied, size is smaller or equal and quality is lower than desired
		if ($sameImageType && $noFilters && (!$this->upscalingAllowed && $thumbLargerThanImage) && $imageQualityTooLow) {
			return false;
		}

		return true;
	}

	/**
	 * set if image upscaling is allowed
	 *
	 * @param boolean $value
	 */
	public function setAllowUpscaling($value) {
		$this->upscalingAllowed = (bool) $value;
	}

	/**
	 * set if jpeg files should be recompressed
	 *
	 * @param boolean $value
	 */
	public function setJpgCompress($value) {
		$this->compressJPG = (bool) $value;
	}

	/**
	 * set the jpeg quality for resampling
	 *
	 * @param int $value
	 */
	public function setJpegQuality($value) {
		$this->thumbQuality = (int) $value;
	}

	/**
	 * set the type of this thumnail
	 *
	 * @param int $type
	 * @throws Exception
	 */
	public function setThumbType($type) {
		if (!in_array($type, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WBMP))) {
			throw new Exception('The Imagetype: '.$type.' is not supported by A2_Thumbnail.', 500);
		}
		$this->thumbType = $type;
	}

	public function setImgParams($params) {
		if (is_array($params)) $this->imgParams = $params;
	}

	public function saveAnimatedGIF($file) {

		$gifDelays   = $this->gifObject->getDelays();
		$gifFrames   = $this->gifObject->getFrames();
		$gifLoop     = $this->gifObject->getLoop();
		$gifDisposal = $this->gifObject->getDisposal();
		$gifOffsets  = $this->gifObject->getOffset();
		$gifTransR   = $this->gifObject->getTransparentR();
		$gifTransG   = $this->gifObject->getTransparentG();
		$gifTransB   = $this->gifObject->getTransparentB();


		$scalingFactor = $this->thumbWidth / $this->origWidth;
		if ($this->widthOffset > 0) $scalingFactor = $this->thumbHeight / $this->origHeight;

		$widthOffset  = (int) round($scalingFactor * $this->widthOffset);
		$heightOffset = (int) round($scalingFactor * $this->heightOffset);


		if (!is_array($gifFrames) || count($gifFrames) <= 0) self::sendError();

		$gifData = array();
		for ($i = 0; $i < count($gifFrames); $i++) {

			// get layer as image stream
			$this->imgsrc = imagecreatefromstring($gifFrames[$i]);

			if (!$this->imgsrc) {
				throw new Exception('Can not create valid Image Source.');
			}

			$smallLayer = $this->imgsrc;
			$sLWidth    = imagesx($smallLayer);
			$sLHeight   = imagesy($smallLayer);

			// if layer has an offset relative to gif canvas, set image offsets to zero
			if (isset($gifOffsets[$i]) && ($sLWidth < $this->origWidth || $sLHeight < $this->origHeight)) {

				// create empty image which has size of whole image
				if (function_exists('imagecreatetruecolor')) {
					$this->imgsrc = @imagecreatetruecolor($this->origWidth, $this->origHeight);
				}
				else {
					$this->imgsrc = @imagecreate($this->origWidth, $this->origHeight);
				}

				$this->keepTransparent($this->imageType, $smallLayer, $this->imgsrc);

				// copy layer into empty image
				imagecopy($this->imgsrc, $smallLayer, $gifOffsets[$i][0], $gifOffsets[$i][1], 0, 0, $sLWidth, $sLHeight);
				imagedestroy($smallLayer);

				// resize layer
				$this->resampleImage();


				// calculate offsets and size of resized layer part

				$sLThumbWidth  = max(1, (int) round($scalingFactor * $sLWidth));
				$sLThumbHeight = max(1, (int) round($scalingFactor * $sLHeight));

				$gifOffsets[$i][0] = (int) round($scalingFactor * $gifOffsets[$i][0]);
				$gifOffsets[$i][1] = (int) round($scalingFactor * $gifOffsets[$i][1]);

				// adjust width offset, if image gets cropped
				if ($widthOffset > 0) {
					// if layer gets cropped
					if ($widthOffset > $gifOffsets[$i][0]) {
						$sLThumbWidth = max(1, $sLThumbWidth - ($widthOffset - $gifOffsets[$i][0]));
					}
					$gifOffsets[$i][0] = max(0, $gifOffsets[$i][0] - $widthOffset);
				}

				// adjust height offset, if image gets cropped
				if ($heightOffset > 0) {
					// if layer gets cropped
					if ($heightOffset > $gifOffsets[$i][1]) {
						$sLThumbHeight = max(1, $sLThumbHeight - ($heightOffset - $gifOffsets[$i][1]));
					}
					$gifOffsets[$i][1] = max(0, $gifOffsets[$i][1] - $heightOffset);
				}

				// remember resized layer
				$smallLayerThumb = $this->imgthumb;

				// create image which has size of resized layer part
				if (function_exists('imagecreatetruecolor')) {
					$this->imgthumb = @imagecreatetruecolor($sLThumbWidth, $sLThumbHeight);
				}
				else {
					$this->imgthumb = @imagecreate($sLThumbWidth, $sLThumbHeight);
				}

				$this->keepTransparent($this->imageType, $smallLayerThumb, $this->imgthumb);

				// copy layer part of resized image into empty image
				imagecopy($this->imgthumb, $smallLayerThumb, 0, 0, $gifOffsets[$i][0], $gifOffsets[$i][1], $sLThumbWidth, $sLThumbHeight);
				imagedestroy($smallLayerThumb);

			}
			// else just resample image
			else {
				$this->resampleImage();
			}

			$this->applyFilters();

			ob_start();
			imagegif($this->imgthumb);
			$gifData[] = ob_get_clean();
			imagedestroy($this->imgthumb);

		}
		$gifmerge = new A2_GIF_Encoder(
			$gifData,
			$gifDelays,
			$gifLoop,
			$gifDisposal,
			$gifTransR, $gifTransG, $gifTransB,
			$gifOffsets,
			"bin"
		);

		fwrite(fopen($file, 'wb'), $gifmerge->getAnimation());
	}

	/**
	 * Schreibt das Thumbnail an den durch $file definierten Platz
	 *
	 * @param string $file  Dateiname des zu generierenden Bildes
	 */
	public function generateImage($file) {
		if ($this->imageGetsModified()) {
			if ($this->imageType == IMAGETYPE_GIF && $this->isAnimated) {
				$this->saveAnimatedGIF($file);
			}
			else {
				$this->resampleImage();
				$this->applyFilters();

				switch ($this->imageType) {
					case IMAGETYPE_JPEG:
						imageinterlace($this->imgthumb, true); // set to progressive mode
						imagejpeg($this->imgthumb, $file, $this->thumbQuality);
						if ($this->iccProfile && $this->iccProfile->GetProfile()) {
							$this->iccProfile->SaveToJPEG($file);
						}
						break;

					case IMAGETYPE_PNG:
						imagepng($this->imgthumb, $file);
						break;

 					case IMAGETYPE_GIF:
						imagegif($this->imgthumb, $file);
						break;

					case IMAGETYPE_WBMP:
						imagewbmp($this->imgthumb, $file);
						break;
				}

				imagedestroy($this->imgthumb);
			}
		}
		// just copy the image
		else {
			copy($this->fileName, $file);
		}

		if ($file) {
			$perm = sly_Core::config()->get('FILEPERM');
			@chmod($file, $perm);
		}
	}

	/**
	 * use all requested filters on the thumb
	 */
	private function applyFilters() {
		foreach ($this->filters as $filter) {
			$filter = preg_replace('#[^a-z0-9]#i', '', $filter);
			$filterClass = 'A2_Filters_'.ucfirst($filter);
			if (method_exists($filterClass, 'filter')) {
				$return = call_user_func(array($filterClass, 'filter'), $this->imgthumb);
				if ($return) $this->imgthumb = $return;
				unset($return);
			}
		}
	}

	/**
	 * set height and width of thumbnail
	 *
	 * @param int $width   Breite des Thumbs
	 * @param int $height  Höhe des Thumbs
	 */
	private function resizeBoth($width, $height) {
		if (!is_array($width) || !isset($width['value']) || !is_array($height) || !isset($height['value'])) {
			return false;
		}

		$imgRatio    = $this->origWidth / $this->origHeight;
		$resizeRatio = $width['value'] / $height['value'];

		// if image ratio is wider than thumb ratio
		if ($imgRatio > $resizeRatio) {
			// if image should be cropped
			if (isset($width['crop']) && $width['crop']) {
				// resize height
				$this->resizeHeight($height);

				// crop width

				// set new cropped width from original image
		  		$this->width = (int) round($resizeRatio * $this->origHeight);

				// set width to crop width
				$this->thumbWidth = (int) $width['value'];

				// if original height is smaller than resize height
				if ($this->origHeight < $height['value']) {
					// and image get not upscaled in height
					if ($this->thumbHeight < $height['value']) {
						// and original width is larger than resize width
						if ($this->origWidth >= $width['value']) {
							// set crop window width to resize width
							$this->width = $width['value'];
						}
						// else do not crop width
						else {
							$this->width = $this->origWidth;
							$this->thumbWidth = $this->width;
						}
					}
				}

				// right offset
				if (isset($width['offset']['right']) && is_numeric($width['offset']['right'])) {
					$this->widthOffset = (int) ($this->origWidth - $this->width - ($this->origHeight / $this->thumbHeight * $width['offset']['right']));
				}
				// left offset
				elseif (isset($width['offset']['left']) && is_numeric($width['offset']['left'])) {
					$this->widthOffset = (int) $width['offset']['left'];
				}
				// set offset to center image
				else {
					$this->widthOffset = (int) (floor($this->origWidth - $this->width) / 2);
				}
			}
			// else resize into bounding box
			else {
				$this->resizeWidth($width);
			}
		}
		// else image ratio is less wide than thumb ratio
		else {
			// if image should be cropped
			if (isset($height['crop']) && $height['crop']) {

				// resize width
				$this->resizeWidth($width);

				// crop height

				// set new cropped width from original image
		  		$this->height = (int) round($this->origWidth / $resizeRatio);

				// set height to crop height
				$this->thumbHeight = (int) $height['value'];

				// if original width is smaller than resize width
				if ($this->origWidth < $width['value']) {
					// and image get not upscaled in width
					if ($this->thumbWidth < $width['value']) {
						// and original height is larger than resize height
						if ($this->origHeight >= $height['value']) {
							// set crop window height to resize height
							$this->height = $height['value'];
						}
						// else do not crop height
						else {
							$this->height = $this->origHeight;
							$this->thumbHeight = $this->height;
						}
					}
				}

				// bottom offset
				if (isset($height['offset']['bottom']) && is_numeric($height['offset']['bottom'])) {
					$this->heightOffset = (int) ($this->origHeight - $this->height - ($this->origWidth / $this->thumbWidth * $height['offset']['bottom']));
				}
				// top offset
				elseif (isset($height['offset']['top']) && is_numeric($height['offset']['top'])) {
					$this->heightOffset = (int) $height['offset']['top'];
				}
				// set offset to center image
				else {
					$this->heightOffset = (int) (floor($this->origHeight - $this->height) / 2);
				}

			}
			// else resize into bounding box
			else {
				$this->resizeHeight($height);
			}
		}
	}

	/**
	 * Setzt die Höhe und Breite des Thumbnails
	 *
	 * @param int $size
	 */
	private function resizeHeight($size) {
		if (!is_array($size) || !isset($size['value'])) {
			return false;
		}

		if ($this->origHeight < $size['value'] && !$this->upscalingAllowed) {
			$size['value'] = $this->origHeight;
		}

		$this->thumbHeight = (int) $size['value'];
		$this->thumbWidth  = (int) round($this->origWidth / $this->origHeight * $this->thumbHeight);
	}

	/**
	 * Setzt die Höhe und Breite des Thumbnails
	 *
	 * @param int $size
	 */
	private function resizeWidth($size) {
		if (!is_array($size) || !isset($size['value'])) {
			return false;
		}

		if ($this->origWidth < $size['value'] && !$this->upscalingAllowed) {
			$size['value'] = $this->origWidth;
		}

		$this->thumbWidth  = (int) $size['value'];
		$this->thumbHeight = (int) ($this->origHeight / $this->origWidth * $this->thumbWidth);
	}

	/**
	 * set height and width of thumbnail
	 *
	 * @param  array $params  Width, Height, Crop and Offset parameters
	 */
	public function setNewSize() {

		$params = $this->imgParams;

		// resize to square
		if (isset($params['auto'])) {
			$this->resizeBoth($params['auto'], $params['auto']);
		}
		// resize width
		elseif (isset($params['width'])) {
			// and resize height
			if (isset($params['height'])) {
				$this->resizeBoth($params['width'], $params['height']);
			}
			// just resize width
			else {
				$this->resizeWidth($params['width']);
			}
		}
		// resize height
		elseif (isset($params['height'])) {
			$this->resizeHeight($params['height']);
		}
	}

	/**
	 * Wendet Filter auf das Thumbnail an
	 */
	public function addFilters($filters) {
		if (!is_array($filters) || empty($filters)) return false;
		$this->filters = array_unique(array_filter($filters));
		return true;
	}

 	/**
	 * return the image types supported by this PHP build
	 *
	 * @return array  supported types as strings
	 */
	public static function getSupportedTypes() {
		$aSupportedTypes = array();

		$aPossibleImageTypeBits = array(
			'GIF'  => IMAGETYPE_GIF,
			'JPEG' => IMAGETYPE_JPEG,
			'PNG'  => IMAGETYPE_PNG,
			'WBMP' => IMAGETYPE_WBMP
		);

		foreach ($aPossibleImageTypeBits as $sImageTypeString => $iImageTypeBits) {
			if (imagetypes() & $iImageTypeBits) {
				$aSupportedTypes[$sImageTypeString] = $iImageTypeBits;
			}
		}

		return $aSupportedTypes;
	}

	/**
	 * Thanks to ZeBadger for original example, and Davide Gualano for pointing me to it
	 * Original at http://it.php.net/manual/en/function.imagecreatefromgif.php#59787
	 */
	private static function isAnimatedGIF($filename) {
		$raw    = file_get_contents($filename);
		$offset = 0;
		$frames = 0;

		while ($frames < 2) {
			$where1 = strpos($raw, "\x00\x21\xF9\x04", $offset);

			if ($where1 === false) {
				break;
			}
			else {
				$offset = $where1 + 1;
				$where2 = strpos($raw, "\x00\x2C", $offset);

				if ($where2 === false) {
					break;
				}
				else {
					if ($where1 + 8 == $where2) {
						++$frames;
					}
					$offset = $where2 + 1;
				}
			}
		}

		return $frames > 1;
	}

	/**
	 * @return string  image type
	 */
	private function getImageType() {
		$allowedTypes = self::getSupportedTypes();

		if (!is_array($allowedTypes) || empty($allowedTypes)) {
			return false;
		}

		$imgInfo = getimagesize($this->fileName);
		if ($imgInfo === false) return false;

		if (!in_array($imgInfo[2], $allowedTypes)) return false;
		return $imgInfo[2];
	}

}
