<?php
/**
 * Image-Resize Addon
 *
 * @author office[at]vscope[dot]at Wolfgang Hutteger
 * @author <a href="http://www.vscope.at">www.vscope.at</a>
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 * @author zozi@webvariants.de
 *
 * @author memento@webvariants.de
 *
 * @package sally 0.4
 * @version 1.7.0
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

	private $thumbWidth        = 0;
	private $thumbHeight       = 0;
	private $thumbWidthOffset  = 0;
	private $thumbHeightOffset = 0;
	private $thumbQuality      = 85;
	private $compressJPG       = true;

	private $upscalingAllowed = false;

	public function __construct($imgfile) {
		$this->fileName = SLY_MEDIAFOLDER.DIRECTORY_SEPARATOR.$imgfile;

		if (!file_exists($this->fileName)) {
			throw new Exception('File '.$this->fileName.' does not exist.', 404);
		}

		$this->imageType = $this->getImageType();



		// avoid resizing animated gifs by sending the original image
		if ($this->imageType == IMAGETYPE_GIF && self::isAnimatedGIF($this->fileName)) {
			//throw new Exception('I will not resize animated GIFs, sorry.', 501);
		}

		if (!$this->imageType) {
				throw new Exception('File is not a supported image type.', 406);
		}

		$data = file_get_contents($this->fileName);
		$this->imgsrc = imagecreatefromstring($data);

		if (!$this->imgsrc) {
				throw new Exception('Can not create valid Image Source.', 500);
		}

		$this->origWidth  = imagesx($this->imgsrc);
		$this->origHeight = imagesy($this->imgsrc);
		$this->width      = $this->origWidth;
		$this->height     = $this->origHeight;

		$service = sly_Service_Factory::getAddOnService();

		$this->thumbQuality     = (int) $service->getProperty('image_resize', 'jpg_quality', $this->thumbQuality);
		$this->upscalingAllowed = (bool) $service->getProperty('image_resize', 'upscaling_allowed', $this->upscalingAllowed);
	}

	/**
	 * Baut das Thumbnail
	 */
	private function resampleImage() {
		// Originalbild selbst sehr klein und wuerde via resize vergrößert
		// => Das Originalbild ausliefern

		if (!$this->upscalingAllowed
			&& $this->thumbWidth >= $this->width
			&& $this->thumbHeight >= $this->height) {

			$this->thumbWidth  = $this->width;
			$this->thumbHeight = $this->height;
		}
		$this->thumbWidth  = max(1, $this->thumbWidth);
		$this->thumbHeight = max(1, $this->thumbHeight);

		$this->thumbWidth  = max(1, $this->thumbWidth);
		$this->thumbHeight = max(1, $this->thumbHeight);

		if (function_exists('imagecreatetruecolor')) {
			$this->imgthumb = @imagecreatetruecolor($this->thumbWidth, $this->thumbHeight);
		}
		else {
			$this->imgthumb = @imagecreate($this->thumbWidth, $this->thumbHeight);
		}

		if (!$this->imgthumb) {
			throw new Exception('Can not create valid Thumbnail Image', 500);
		}

		// Transparenz erhalten
		$this->keepTransparent();
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
	}

	/**
	 * Sorgt dafür, dass Bilder transparent bleiben
	 */
	private function keepTransparent() {
		if ($this->imageType == IMAGETYPE_PNG || $this->imageType == IMAGETYPE_GIF) {
			if ($this->imageType == IMAGETYPE_GIF) {
				imagepalettecopy($this->imgsrc, $this->imgthumb);
			}

			// get the original image's index for its transparent color
			$colorTransparent = imagecolortransparent($this->imgsrc);

			// if there is an index in the color index range -> the image has transparency
			if ($colorTransparent >= 0 && $colorTransparent < imagecolorstotal($this->imgsrc)) {

				// Get the original image's transparent color's RGB values
				$trnprt_color = imagecolorsforindex($this->imgsrc,  $colorTransparent);

				// Allocate the same color in the new image resource
				$colorTransparent = imagecolorallocate($this->imgthumb, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);

				// Completely fill the background of the new image with allocated color.
				imagefill($this->imgthumb, 0, 0, $colorTransparent);

				// Set the background color for new image to transparent
				imagecolortransparent($this->imgthumb, $colorTransparent);
			}
			elseif ($this->imageType == IMAGETYPE_PNG) {
				imagealphablending($this->imgthumb, false);

				// Create a new transparent color for image
				$color = imagecolorallocatealpha($this->imgthumb, 0, 0, 0, 127);

				// Completely fill the background of the new image with allocated color.
				imagefill($this->imgthumb, 0, 0, $color);

				imagesavealpha($this->imgthumb, true);
			}

			if ($this->imageType == IMAGETYPE_GIF) {
				imagetruecolortopalette($this->imgthumb, true, 256);
			}
		}
	}

	/**
	 * determine whether the image needs to be modified or not
	 *
	 * @return boolean
	 */
	private function imageGetsModified() {

		if ($this->imageType == IMAGETYPE_GIF && self::isAnimatedGIF($this->fileName)) {
			return false;
		}

		$thumbLargerThanImage = $this->thumbWidth >= $this->width || $this->thumbHeight >= $this->height;
		// compare image quality only for jpeg
		$imageQualityTooLow   = $this->imageType != IMAGETYPE_JPEG || !$this->compressJPG || $this->thumbQuality >= $this->quality;
		$noFilters            = empty($this->filters);

		// if no filter are applied, size is smaller or equal and quality is lower than desired
		if ($noFilters && (!$this->upscalingAllowed && $thumbLargerThanImage) && $imageQualityTooLow) {
			return false;
		}

		return true;
	}

	public function setJpgCompress($compress) {
		if (!((boolean) $compress)) $this->compressJPG = false;
	}

	/**
	 * Schreibt das Thumbnail an den durch $file definierten Platz
	 *
	 * @param string $file  Dateiname des zu generierenden Bildes
	 */
	public function generateImage($file) {
		if ($this->imageGetsModified()) {
			$this->resampleImage();
			$this->applyFilters();

			switch ($this->imageType) {
				case IMAGETYPE_JPEG:
					imageinterlace($this->imgthumb, true); // set to progressive mode
					imagejpeg($this->imgthumb, $file, $this->thumbQuality);
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
	public function setNewSize($params) {
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

	public static function scaleMediaImagesInHtml($html, $maxImageSize = 650) {
		// use imageresize to scale images instead of style width and height
		$html = preg_replace(
			'~style="width\:[ ]*([0-9]+)px;[ ]*height\:[ ]*([0-9]+)px;?"[ \r\n]*src="data/mediapool/([a-zA-Z0-9\.-_]+)"~',
			'src="imageresize/\1w__\2h__\3"',
			$html
		);

		// the same just height first
		$html = preg_replace(
			'~style="height\:[ ]*([0-9]+)px;[ ]*width\:[ ]*([0-9]+)px;?"[ \r\n]*src="data/mediapool/([a-zA-Z0-9\.-_]+)"~',
			'src="imageresize/\2w__\1h__\3"',
			$html
		);

		// resize the rest of the images to max resize value
		if ($maxImageSize) {
			$html = preg_replace(
				'~src="data/mediapool/([a-zA-Z0-9\.-_]+)(?<!\.bmp)"~',
				'src="imageresize/'.$maxImageSize.'a__\1"',
				$html
			);
		}

		return $html;
	}
}
