<?php

class A2_GIF_Frame {

	private $disposalMethod   = 0;
	private $userInputFlag    = 0;
	private $transpColorFlag  = 0;
	private $delay            = 0;
	private $transpColorIndex = 0;

	private $imageLeft   = 0;
	private $imageTop    = 0;
	private $imageWidth  = null;
	private $imageHeight = null;

	private $localColorTableFlag = 0;
	private $localColorTableSize = 0;
	private $sortFlag            = 0;
	private $interlaceFlag       = 0;

	public function __construct($streamData) {

	}

	/**
	 *
	 * @param int     $delay             delay in seconds to next image
	 * @param int     $disposal          disposal method type (0-7)
	 * @param int     $transpColorIndex  color index for transparency
	 * @return string in hex code
	 */
	private function getGraphicalControlExtension() {

		return "\x21"                            // extension introducer (always 21)
		     . "\xF9"                            // graphic control label (always F9)
		     . "\x04"                            // block size (fixed value 4)
		     . chr(                              // packed fields
		           ($this->disposalMethod << 2)
		         + ($this->userInputFlag  << 1)
		         + $this->transpColorFlag
		     )
		     . chr(($this->delay >> 0) & 0xFF)
		     . chr(($this->delay >> 8) & 0xFF)
		     . chr($this->transpColorIndex)
		     . "\x0";                            // block terminator (always zero)
	}

	private function getImageDescriptor() {

		return "\x2C"                            // image seperator (always 2C)
		     . chr(($this->imageLeft >> 0) & 0xFF)
		     . chr(($this->imageLeft >> 8) & 0xFF)

		     . chr(($this->imageTop  >> 0) & 0xFF)
		     . chr(($this->imageTop  >> 8) & 0xFF)

		     . chr(($this->imageWidth  >> 0) & 0xFF)
		     . chr(($this->imageWidth  >> 8) & 0xFF)

		     . chr(($this->imageHeight  >> 0) & 0xFF)
		     . chr(($this->imageHeight  >> 8) & 0xFF)

		     . chr(                              // packed fields
		           ($this->localColorTableFlag << 7)
		         + ($this->interlaceFlag       << 6)
		         + ($this->sortFlag            << 5)
		         + $this->localColorTableSize
		     );
	}

	private function getColorTable() {
		return false;
	}

	private function getImageData() {
		return false;
	}

	public function toString() {

		// GIF data begin
		$string = 'GIF';

		// version
		if ($this->transpColorFlag) $string .= '89a';
		else $string .= '87a';

		$string .= $this->getGraphicalControlExtension();

		$string .= $this->getImageDescriptor();

		$string .= $this->getColorTable();

		$string .= $this->getImageData();

		// GIF data end
		$string .= chr(0x3B);

		return $string;
	}
}
