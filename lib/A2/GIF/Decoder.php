<?php
/*
 * based on GIFDecoder by László Zsidi
 * http://www.phpclasses.org/package/3163-PHP-Generate-GIF-animations-from-a-set-of-GIF-images.html
 * http://www.phpclasses.org/package/3234-PHP-Split-GIF-animations-into-multiple-images.html
 * http://www.gifs.hu
 *
 *
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 *
*/

class A2_GIF_Decoder {
	private $transparentR = -1;
	private $transparentG = -1;
	private $transparentB = -1;
	private $transparentI =  0;

	private $buffer = array();
	private $arrays = array();
	private $delays = array();
	private $dispos = array();
	private $offset = array();
	private $stream = '';
	private $string = '';
	private $bfseek = 0;
	private $anloop = 0;

	private $screen       = array();
	private $screenWidth  = null;
	private $screenHeight = null;
	private $global       = array();
	private $sorted;
	private $colorS;
	private $colorC;
	private $colorF;

	public function __construct($gifFileContent) {
		$this->stream = $gifFileContent;

		$this->getByte( 6 ); // GIF89a
		$this->getByte( 7 ); // Logical Screen Descriptor

		$packed = $this->buffer[4]; // Packed Fields

		$this->screen       = $this->buffer;

		$this->screenWidth  = $this->buffer[0]; // Logical Screen Width
		$this->screenHeight = $this->buffer[2]; // Logical Screen Height

		$this->colorF       = $packed & 0x80 ? 1 : 0; // Global Color Table Flag
		$this->colorC       = $packed & 0x07;         // Color Resulution
		$this->sorted       = $packed & 0x08 ? 1 : 0; // Sort Flag
		$this->colorS       = 2 << $this->colorC;     // Size of Global Color Table

		// read global color table
		if ($this->colorF == 1) {
			$this->getByte(3*$this->colorS);
			$this->global = $this->buffer;
		}

		for ($cycle = 1; $cycle;) {
			if ($this->getByte( 1 )) {
				switch ($this->buffer[0]) {
					case 0x21:
						$this->readExtensions();
						break;
					case 0x2C:
						$this->readDescriptor();
						break;
					case 0x3B:
						$cycle = 0;
						break;
				}
			}
			else {
				$cycle = 0;
			}
		}
	}

	private function readExtensions() {
		$this->getByte(1);

		// application extension
		if ($this->buffer [ 0 ] == 0xff) {
			for (; ;) {
				$this->getByte(1);
				// if byte size is zero, return
				if (($u = $this->buffer[0]) == 0x00) return false;

				// get bytes in length of byte size
				$this->getByte($u);

				// if byte size is 3
				if ($u == 0x03) {
					// get loop counter (0 to 65535), zero means infinite
					$this->anloop = ($this->buffer[1] | $this->buffer[2] << 8);
				}
			}
		}
		// other applications
		else {
			for (; ;) {
				$this->getByte(1);
				// if byte size is zero, return
				if (($u = $this->buffer[0]) == 0x00) {
					return false;
				}
				$this->getByte($u);

				// if graphics control extension
				if ($u == 0x04) {
					// disposal method
					if (isset($this->buffer[4]) && $this->buffer[4] & 0x80 ) {
						$this->dispos[] = ( $this->buffer[0] >> 2 ) - 1;
					}
					else {
						$this->dispos[] = ( $this->buffer[0] >> 2 ) - 0;
					}
					// delay
					$this->delays[] = ( $this->buffer[1] | $this->buffer[2] << 8 );

					// transparent color index
					if ($this->buffer[3]) {
						$this->transparentI = $this->buffer[3];
					}
				}
			}
		}
	}

	private function readDescriptor() {
		$GIF_screen	= array();

		$this->getByte ( 9 );
		$GIF_screen = $this->buffer;

		$imageLeft = $this->getInt(array($this->buffer[0], $this->buffer[1]));
		$imageTop  = $this->getInt(array($this->buffer[2], $this->buffer[3]));

		$this->offset[] = array($imageLeft, $imageTop);

		$GIF_colorF = $this->buffer [ 8 ] & 0x80 ? 1 : 0;
		if ($GIF_colorF) {
			$GIF_code = $this->buffer [ 8 ] & 0x07;
			$GIF_sort = $this->buffer [ 8 ] & 0x20 ? 1 : 0;
		}
		else {
			$GIF_code = $this->colorC;
			$GIF_sort = $this->sorted;
		}
		$GIF_size = 2 << $GIF_code;
		$this->screen [ 4 ] &= 0x70;
		$this->screen [ 4 ] |= 0x80;
		$this->screen [ 4 ] |= $GIF_code;
		if ($GIF_sort) {
			$this->screen [ 4 ] |= 0x08;
		}
		/*
		 *
		 * GIF Data Begin
		 *
		 */
		if ($this->transparentI) {
			$this->string = "GIF89a";
		}
		else {
			$this->string = "GIF87a";
		}

		$this->putByte($this->screen);
		if ($GIF_colorF == 1) {
			$this->getByte(3 * $GIF_size);
			if ($this->transparentI) {
				if (isset($this->buffer[3 * $this->transparentI + 0])) $this->transparentR = $this->buffer[3 * $this->transparentI + 0];
				if (isset($this->buffer[3 * $this->transparentI + 1])) $this->transparentG = $this->buffer[3 * $this->transparentI + 1];
				if (isset($this->buffer[3 * $this->transparentI + 2])) $this->transparentB = $this->buffer[3 * $this->transparentI + 2];
			}
			$this->putByte($this->buffer);
		}
		else {
			if ($this->transparentI) {
				if (isset($this->global[3 * $this->transparentI + 0])) $this->transparentR = $this->global[3 * $this->transparentI + 0];
				if (isset($this->global[3 * $this->transparentI + 1])) $this->transparentG = $this->global[3 * $this->transparentI + 1];
				if (isset($this->global[3 * $this->transparentI + 2])) $this->transparentB = $this->global[3 * $this->transparentI + 2];
			}
			$this->putByte ($this->global);
		}
		if ($this->transparentI) {
			$this->string .= "!\xF9\x04\x1\x0\x0". chr ( $this->transparentI ) . "\x0";
		}
		// \x1 == 000 000 0 1
		$this->string .= chr ( 0x2C );
		$GIF_screen [ 8 ] &= 0x40;
		$this->putByte ( $GIF_screen );
		$this->getByte ( 1 );
		$this->putByte ( $this->buffer );
		for (; ;) {
			$this->getByte ( 1 );
			$this->putByte ( $this->buffer );
			if ( ( $u = $this->buffer [ 0 ] ) == 0x00 ) {
				break;
			}
			$this->getByte ( $u );
			$this->putByte ( $this->buffer );
		}
		$this->string .= chr ( 0x3B );
		/*
		 *
		 * GIF Data End
		 *
		 */
		$this->arrays [ ] = $this->string;
	}

	private function getByte($len) {
		$this->buffer = array();

		for ($i = 0; $i < $len; $i++) {
			if ( $this->bfseek > strlen ( $this->stream ) ) {
				return 0;
			}
			$this->buffer[ ] = ord( $this->stream { $this->bfseek++ } );
		}
		return 1;
	}

	private function putByte($bytes) {
		foreach ($bytes as $byte) {
			$this->string .= chr ( $byte );
		}
	}

	private function getInt(array $bytes) {
		if (count($bytes) === 1) return (int) reset($bytes);

		$skew  = count($bytes)-1;
		$bytes = array_reverse($bytes);

		foreach ($bytes as $idx => $byte) {
			$bytes[$idx] = ($byte << ($skew*8));
			$skew--;
		}

		return array_sum($bytes);
	}

	public function getFrames()       { return $this->arrays; }
	public function getScreenWidth()  { return $this->screenWidth; }
	public function getScreenHeight() {	return $this->screenHeight; }
	public function getDelays()       { return $this->delays; }
	public function getLoop()         { return $this->anloop; }
	public function getDisposal()     { return $this->dispos; }
	public function getOffset()       { return $this->offset; }
	public function getTransparentR() { return $this->transparentR; }
	public function getTransparentG() { return $this->transparentG; }
	public function getTransparentB() { return $this->transparentB; }
}
