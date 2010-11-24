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
 * @package sally 0.3
 * @version 1.7.0
 */

class A2_Thumbnail {
	const ERRORFILE = 'error.png';
	const QUALITY   = 85;
	const USECACHE  = true;

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
	private $thumbQuality      = self::QUALITY;

	private $upscalingAllowed = false;

	public function __construct($imgfile) {
		$this->fileName = $imgfile;

		if (strpos($imgfile, 'http://') !== 0) {
			$this->fileName = SLY_MEDIAFOLDER.DIRECTORY_SEPARATOR.$this->fileName;

			if (!file_exists($this->fileName)) {
				throw new Exception('File '.$this->fileName.' does not exist.');
			}
		}

		$this->imageType = self::getImageType($this->fileName);

		if (!$this->imageType) {
			throw new Exception('File is not a supported image type.');
		}

		$data = file_get_contents($this->fileName);
		$this->imgsrc = imagecreatefromstring($data);

		if (!$this->imgsrc) {
			throw new Exception('Can not create valid Image Source.');
		}

		$this->origWidth  = imagesx($this->imgsrc);
		$this->origHeight = imagesy($this->imgsrc);
		$this->width      = $this->origWidth;
		$this->height     = $this->origHeight;

		$service = sly_Service_Factory::getAddOnService();

		$this->thumbQuality     = (int) $service->getProperty('image_resize', 'jpg_quality');
		$this->upscalingAllowed = (bool) $service->getProperty('image_resize', 'upscaling_allowed');
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

		if (function_exists('imagecreatetruecolor')) {
			$this->imgthumb = @imagecreatetruecolor($this->thumbWidth, $this->thumbHeight);
		}
		else {
			$this->imgthumb = @imagecreate($this->thumbWidth, $this->thumbHeight);
		}

		if (!$this->imgthumb) {
			throw new Exception('Can not create valid Thumbnail Image');
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
		$thumbLargerThanImage = $this->thumbWidth >= $this->width || $this->thumbHeight >= $this->height;
		$imageQualityTooLow   = $this->thumbQuality >= $this->quality;
		$noFilters            = empty($this->filters);

		// if no filter are applied, size is smaller or equal and quality is lower than desired
		if ($noFilters && (!$this->upscalingAllowed && $thumbLargerThanImage) && $imageQualityTooLow) {
			return false;
		}

		return true;
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
	 * Wendet alle konfigurierten Filter auf das Thumbnail an
	 */
	private function applyFilters() {
		foreach ($this->filters as $filter) {
			$filter = preg_replace('#[^a-z0-9_]#i', '', $filter);
			$file   = SLY_INCLUDE_PATH.'/addons/image_resize/filters/filter.'.$filter.'.inc.php';
			$fname  = 'image_resize_'.$filter;

			if (file_exists($file))      require_once $file;
			if (function_exists($fname)) $fname($this->imgthumb);
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
	public function addFilters() {
		$this->filters = array_unique(array_filter(sly_get('rex_filter', 'array', array())));
	}

	/**
	 * Sendet ein bearbeitetes Bild an den Browser
	 *
	 * @param string $rex_resize
	 */
	public static function getResizedImage($rex_resize) {
		$cachefile = self::getCacheFileName($rex_resize);

		if (!self::USECACHE || !file_exists($cachefile)) {
			// c100w__c200h__20r__20t__filename.jpg

			// separate filename and parameters (x and c in whaxc are just for backwards compatibility)
			preg_match('@((?:c?[0-9]{1,4}[whaxc]__){1,2}(?:\-?[0-9]{1,4}[orltb]?__){0,2})(.*)@', $rex_resize, $params);
			if (!isset($params[1]) || !isset($params[2])) return false;

			// get filename
			$imageFile = $params[2];

			// trim _ at the end
			$params = trim($params[1], '_');
			// split parameters
			$params = explode('__', $params);

			// iterate parameters
			$imgParams = array();

			foreach ($params as $param) {
				// check crop option
				$crop   = false;
				$prefix = substr($param, 0, 1);

				if ($prefix == 'c') {
					$crop = true;
					$param = substr($param, 1);
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

			if (empty($imageFile)) {
				self::sendError();
			}

			try {
				$thumb = new self($imageFile);
				$thumb->setNewSize($imgParams);
				$thumb->addFilters();
				$thumb->generateImage($cachefile);
			}
			catch (Exception $e) {
				self::sendError();
			}
		}

		$cachetime = filectime($cachefile);
		self::sendImage($cachefile, $cachetime);
	}

	/**
	 * Löscht den Cache
	 *
	 * @param  string $filename  um ein bestimmtes Bild zu löschen
	 * @return int               Anzahl der gelöschten Bilder
	 */
	public static function deleteCache($filename = '') {
		$service = sly_Service_Factory::getService('AddOn');
		$folder  = $service->publicFolder('image_resize');
		$c       = 0;
		$files   = glob($folder.'/cache__*');

		if ($files) {
			if (empty($filename)) {
				array_map('unlink', $files);
				return count($files);
			}

			foreach ($files as $file) {
				if ($filename == substr($file, -strlen($filename))) {
					unlink($file);
					++$c;
				}
			}
		}

		return $c;
	}

	/**
	 * @param  array  EP-Parameter
	 * @return int
	 */
	public static function mediaUpdated($params) {
		if (!isset($params['filename'])) return false;
		return self::deleteCache($params['filename']);
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
	 * @param  string $fileName
	 * @return string            image type
	 */
	private static function getImageType($fileName) {
		$allowedTypes = self::getSupportedTypes();

		if (!$fileName || !is_array($allowedTypes) || empty($allowedTypes)) {
			return false;
		}

		$imgInfo = getImageSize($fileName);

		if (!is_array($imgInfo) || empty($imgInfo) || !array_key_exists(2, $imgInfo) || !is_int($imgInfo[2])) {
			return false;
		}

		if (!in_array($imgInfo[2], $allowedTypes)) return false;
		return $imgInfo[2];
	}

	/**
	 * Gibt den Dateinamen eines Bildes anhand des rex_resize Parameters zurück
	 *
	 * @param string $rex_resize
	 * @return string Dateiname des Bildes im Cache
	 */
	private static function getCacheFileName($rex_resize) {
		$service = sly_Service_Factory::getService('AddOn');
		$folder  = $service->publicFolder('image_resize');
		return $folder.'/cache__'.str_replace(array('http://', 'https://', '/'), array('', '', '_'), $rex_resize);
	}

	/**
	 * Sendet die Fehlerdatei
	 *
	 * @return void
	 */
	private static function sendError() {
		header('HTTP/1.0 404 Not Found');
		self::sendImage(SLY_INCLUDE_PATH.'/addons/image_resize/media/'.self::ERRORFILE, true);
	}

	/**
	 * Sendet ein Bild an den Browser und beendet das Script
	 *
	 * @param string $fileName Dateiname des Bildes
	 * @param timestamp $lastModified wann wurde das Bild zuletzt modifiziert?
	 * @return void
	 */
	private static function sendImage($fileName, $error = false) {
		while (ob_get_level()) ob_end_clean();

		if (!$error) {
			$etag = md5($fileName.filectime($fileName));

			if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
				header('HTTP/1.0 304 Not Modified');
				exit();
			}
			if (!is_readable($fileName)) {
				trigger_error('The Image "'.$fileName.'" cannot be read.', E_USER_WARNING);
				header('HTTP/1.0 404 Not Found');
				exit();
			}

			header('ETag: '.$etag);
			header('Cache-Control: ');
			header('Pragma: ');
		}

		header('Content-Type: '.image_type_to_mime_type(self::getImageType($fileName)));

		readfile($fileName);
		exit();
	}
}
