<?php
/*
 * based on GIFEncoder Version 3.0 by László Zsidi
 * http://www.phpclasses.org/package/3163-PHP-Generate-GIF-animations-from-a-set-of-GIF-images.html
 * http://www.phpclasses.org/package/3234-PHP-Split-GIF-animations-into-multiple-images.html
 * http://www.gifs.hu
 *
 *
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 *
*/

class A2_GIF_Encoder {

	var $GIF = "GIF89a";            // GIF header 6 bytes
	var $VER = "GIFEncoder V3.00";  // Encoder version

	var $BUF = array();
	var $OFS = array();
	var $SIG =  0;
	var $LOP =  0;
	var $DIS =  2;
	var $COL = -1;
	var $IMG = -1;

	var $ERR = array(
			'ERR00'=>"Does support animated GIF images only!",
			'ERR01'=>"Source is not a GIF image!",
			'ERR02'=>"Unintelligible flag",
			'ERR03'=>"Does not make animation from animated GIF source",
	);


	function __construct($GIF_src, $GIF_dly, $GIF_lop, $GIF_dis,
						 $GIF_red, $GIF_grn, $GIF_blu,
						 $GIF_ofs, $GIF_mod) {

		if (!is_array($GIF_src) && !is_array($GIF_dly)) {
			printf  ("%s: %s", $this->VER, $this->ERR ['ERR00']);
			exit    (0);
		}
		$this->LOP = $GIF_lop === false ? false : (($GIF_lop > -1) ? $GIF_lop : 0);
		$this->COL = ($GIF_red > -1 && $GIF_grn > -1 && $GIF_blu > -1) ?
										($GIF_red | ($GIF_grn << 8) | ($GIF_blu << 16)) : -1;
		#var_dump($GIF_red, $GIF_grn, $GIF_blu, $this->COL);die;

		for ($i = 0; $i < count ($GIF_src); $i++) {
			if (strToLower ($GIF_mod) == "url") {
				$this->BUF [] = fread (fopen ($GIF_src [$i], "rb"), filesize ($GIF_src [$i]));
			}
			else if (strToLower ($GIF_mod) == "bin") {
				$this->BUF [] = $GIF_src [$i];
			}
			else {
				printf  ("%s: %s (%s)!", $this->VER, $this->ERR ['ERR02'], $GIF_mod);
				exit    (0);
			}

			if (substr ($this->BUF [$i], 0, 6) != "GIF87a" && substr ($this->BUF [$i], 0, 6) != "GIF89a") {
				printf  ("%s: %d %s", $this->VER, $i, $this->ERR ['ERR01']);
				exit    (0);
			}

			for ($j = $this->getLocalsStrLength($i), $k = TRUE; $k; $j++) {
				switch ($this->BUF [$i] { $j }) {
					case "!":
						if ((substr ($this->BUF [$i], ($j + 3), 8)) == "NETSCAPE") {
								printf  ("%s: %s (%s source)!", $this->VER, $this->ERR ['ERR03'], ($i + 1));
								exit    (0);
						}
						break;
					case ";":
						$k = FALSE;
						break;
				}
			}
		}
		if (!is_array($GIF_ofs)) $GIF_ofs = array();

		for ($i = 0; $i < count ($this->BUF); $i++) {
			if (!isset($GIF_dis[$i])) $GIF_dis[$i] = $this->DIS;
			else $GIF_dis[$i] = ($GIF_dis[$i] > -1) ? (($GIF_dis[$i] < 3) ? $GIF_dis[$i] : 3) : 2;

			if (!isset($GIF_ofs[$i])) $GIF_ofs[$i] = 0;
		}

		if (is_array($GIF_ofs) && count ($GIF_ofs) > 1) {
			$this->SIG = 1;
			$this->OFS = $GIF_ofs;
		}


		$this->addHeader();
		for ($i = 0; $i < count($this->BUF); $i++) {
			$this->addFrames ($i, $GIF_dly [$i], $GIF_dis[$i]);
		}
//		die;
		$this->addFooter();
	}

	private function addHeader() {
		$cmap = 0;

		if (ord ($this->BUF[0][10]) & 0x80) {
			$cmap = $this->getColorTableLength(0);

			$this->GIF .= substr($this->BUF[0],  6, 7);
			$this->GIF .= substr($this->BUF[0], 13, $cmap);
			if ($this->LOP !== false) {
				$this->GIF .= "!\377\13NETSCAPE2.0\3\1".$this->intToWord($this->LOP)."\0";
			}
		}
	}

	/**
	 *
	 * @param int     $delay             delay in seconds to next image
	 * @param int     $disposal          disposal method type (0-7)
	 * @param int     $transpColorIndex  color index for transparency
	 * @return string in hex code
	 */
	private function getGraphicalControlExtension($delay, $disposal, $transpColorIndex = null) {

//var_dump($transpColorIndex);
		$transpFlag = 0;
		if (!is_int($transpColorIndex) || $transpColorIndex < 0) $transpColorIndex = 0;
		else $transpFlag = 1;

//var_dump($transpFlag, $transpColorIndex);

		return "\x21"                    // extension introducer (always 21)
		     . "\xF9"                    // graphic control label (F9 means graphic control extension)
		     . "\x04"                    // block size (fixed value 4)
		     . chr(                      // packed fields
		           ($disposal << 2)         // disposal method
		         + $transpFlag              // transparency flag
		     )
		     . chr(($delay >> 0) & 0xFF) // delay time
		     . chr(($delay >> 8) & 0xFF) // delay time
		     . chr($transpColorIndex)    // transparent color index
		     . "\x0";                    // block terminator (always zero)
	}

	private function getColorLength($imageNumber) {
		return 2 << (ord($this->BUF[$imageNumber][10]) & 0x07);
	}

	private function getColorTableLength($imageNumber) {
		return 3 * $this->getColorLength($imageNumber);
	}

	private function getLocalsStrLength($imageNumber) {
		return 13 + $this->getColorTableLength($imageNumber);
	}

	private function getXYPadding($i, $Locals_ext, $Locals_img, $Locals_rgb, $Locals_tmp, $local=true) {

		if ($this->SIG == 1) {
			$Locals_img[1] = chr( $this->OFS[$i][0] & 0xFF);
			$Locals_img[2] = chr(($this->OFS[$i][0] & 0xFF00) >> 8);
			$Locals_img[3] = chr( $this->OFS[$i][1] & 0xFF);
			$Locals_img[4] = chr(($this->OFS[$i][1] & 0xFF00) >> 8);
		}

		$byte  = ord($Locals_img[9]);
		$byte |= 0x80;
		$byte &= 0xF8;

		if ($local) $byte |= (ord($this->BUF[$i][10]) & 0x07);
		else        $byte |= (ord($this->BUF[0] [10]) & 0x07);

		$Locals_img[9] = chr($byte);

		return $Locals_ext.$Locals_img.$Locals_rgb.$Locals_tmp;
	}

	private function addFrames($i, $d, $disposal) {

		$Locals_str = $this->getLocalsStrLength($i);

		$Locals_end = strlen($this->BUF[$i]) - $Locals_str - 1;
		$Locals_tmp = substr($this->BUF[$i], $Locals_str, $Locals_end);

		$Global_len = $this->getColorLength(0);
		$Locals_len = $this->getColorLength($i);

		$Global_rgb = substr($this->BUF[0], 13, $this->getColorTableLength(0));
		$Locals_rgb = substr($this->BUF[$i], 13, $this->getColorTableLength($i));

		$Locals_ext = $this->getGraphicalControlExtension($d, $disposal);


//		print(substr($this->BUF[$i], 6, 7)).'x';
		if ($this->COL > -1 && ord($this->BUF[$i][10]) & 0x80) {
//			print 'maximum color index: '.$this->getColorLength($i).'<br>';
//			for ($j = 0; $j < $this->getColorLength($i); $j++) {
//
//				if (ord($Locals_rgb[3 * $j + 0]) == (($this->COL >> 16) & 0xFF)
//				 && ord($Locals_rgb[3 * $j + 1]) == (($this->COL >>  8) & 0xFF)
//				 && ord($Locals_rgb[3 * $j + 2]) == (($this->COL >>  0) & 0xFF)) {
//
//					$Locals_ext = $this->getGraphicalControlExtension($d, $disposal, $j);
//					break;
//				}
//			}
			// hack to get index 1 transparent
			if ($i > 0) $Locals_ext = $this->getGraphicalControlExtension($d, $disposal, 1);
		}

//		print "$i: ".urlencode($Locals_ext)."<br>\n";
		switch ($Locals_tmp[0]) {
			case "!":
				$Locals_img = substr ($Locals_tmp, 8, 10);
				$Locals_tmp = substr ($Locals_tmp, 18, strlen ($Locals_tmp) - 18);
				break;
			case ",":
				$Locals_img = substr ($Locals_tmp, 0, 10);
				$Locals_tmp = substr ($Locals_tmp, 10, strlen ($Locals_tmp) - 10);
				break;
		}

		if (ord($this->BUF[$i][10]) & 0x80 && $this->IMG > -1) {

			// global and local color table have same length
			if ($Global_len == $Locals_len) {

				// global and local color table are equal
				if ($this->blockCompare($Global_rgb, $Locals_rgb, $Global_len)) {
					$this->GIF .= $Locals_ext.$Locals_img.$Locals_tmp;
				}
				else {
					$this->GIF .= $this->getXYPadding($i, $Locals_ext, $Locals_img, $Locals_rgb, $Locals_tmp, false);
				}
			}
			// special local color table
			else {
				$this->GIF .= $this->getXYPadding($i, $Locals_ext, $Locals_img, $Locals_rgb, $Locals_tmp);
			}
		}
		else {
			$this->GIF .= $Locals_ext.$Locals_img.$Locals_tmp;
		}
		$this->IMG = 1;
//		print '<br />';
	}

	private function addFooter() {
		$this->GIF .= ";";
	}

	private function blockCompare($GlobalBlock, $LocalBlock, $Len) {

		for ($i = 0; $i < $Len; $i++) {

			if ($GlobalBlock[3 * $i + 0] != $LocalBlock[3 * $i + 0]
			 || $GlobalBlock[3 * $i + 1] != $LocalBlock[3 * $i + 1]
			 || $GlobalBlock[3 * $i + 2] != $LocalBlock[3 * $i + 2]) {

				return false;
			}
		}
		return true;
	}

	private function intToWord($int) {
		return chr($int & 0xFF).chr(($int >> 8) & 0xFF);
	}

	public function getAnimation() {
		return $this->GIF;
	}

}

