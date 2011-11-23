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
			'ERR00'=>"Does not supported function for only one image!",
			'ERR01'=>"Source is not a GIF image!",
			'ERR02'=>"Unintelligible flag ",
			'ERR03'=>"Does not make animation from animated GIF source",
	);


	function __construct($GIF_src, $GIF_dly, $GIF_lop, $GIF_dis,
						 $GIF_red, $GIF_grn, $GIF_blu,
						 $GIF_ofs, $GIF_mod) {

		if (!is_array($GIF_src) && ! is_array($GIF_dly)) {
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

			for ($j = (13 + 3 * (2 << (ord ($this->BUF [$i] { 10 }) & 0x07))), $k = TRUE; $k; $j++) {
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

		// WV-HACK: $GIF_dis is an array not an int
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
		for ($i = 0; $i < count ($this->BUF); $i++) {
			$this->addFrames ($i, $GIF_dly [$i], $GIF_dis[$i]);
		}
//		die;
		$this->addFooter ();
	}

	private function addHeader() {
		$cmap = 0;

		if (ord ($this->BUF [0] { 10 }) & 0x80) {
			$cmap = 3 * (2 << (ord ($this->BUF [0] { 10 }) & 0x07));

			$this->GIF .= substr ($this->BUF [0], 6, 7          );
			$this->GIF .= substr ($this->BUF [0], 13, $cmap     );
			if ($this->LOP !== false) {
				$this->GIF .= "!\377\13NETSCAPE2.0\3\1" . $this->intToWord ($this->LOP) . "\0";
			}
		}
	}

	private function addFrames($i, $d, $disposal) {

		$Locals_str = 13 + 3 * (2 << (ord ($this->BUF [$i] { 10 }) & 0x07));

		$Locals_end = strlen ($this->BUF [$i]) - $Locals_str - 1;
		$Locals_tmp = substr ($this->BUF [$i], $Locals_str, $Locals_end);

		$Global_len = 2 << (ord ($this->BUF [0] { 10 }) & 0x07);
		$Locals_len = 2 << (ord ($this->BUF [$i] { 10 }) & 0x07);

		$Global_rgb = substr ($this->BUF [0], 13,
												3 * (2 << (ord ($this->BUF [0][10]) & 0x07)));
		$Locals_rgb = substr ($this->BUF [$i], 13,
												3 * (2 << (ord ($this->BUF [$i][10]) & 0x07)));

		$Locals_ext = "!\xF9\x04" . chr (($disposal << 2) + 0) .
										chr (($d >> 0) & 0xFF) . chr (($d >> 8) & 0xFF) . "\x0\x0";

		if ($this->COL > -1 && ord($this->BUF [$i][10]) & 0x80) {
			for ($j = 0; $j < (2 << (ord ($this->BUF [$i][10]) & 0x07)); $j++) {

				if (ord ($Locals_rgb { 3 * $j + 0 }) == (($this->COL >> 16) & 0xFF)
				 && ord ($Locals_rgb { 3 * $j + 1 }) == (($this->COL >>  8) & 0xFF)
				 && ord ($Locals_rgb { 3 * $j + 2 }) == (($this->COL >>  0) & 0xFF)) {

					$Locals_ext = "!\xF9\x04"
					            . chr(($disposal << 2) + 1)
					            . chr(($d >> 0) & 0xFF)
					            . chr(($d >> 8) & 0xFF)
					            . chr($j)
					            . "\x0";
					break;
				}
			}
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
		if (ord ($this->BUF [$i] { 10 }) & 0x80 && $this->IMG > -1) {
			if ($Global_len == $Locals_len) {
				if ($this->blockCompare ($Global_rgb, $Locals_rgb, $Global_len)) {
					$this->GIF .= ($Locals_ext . $Locals_img . $Locals_tmp);
				}
				else {
					// XY Padding...

					if ($this->SIG == 1) {
						$Locals_img { 1 } = chr ($this->OFS [$i] [0] & 0xFF);
						$Locals_img { 2 } = chr (($this->OFS [$i] [0] & 0xFF00) >> 8);
						$Locals_img { 3 } = chr ($this->OFS [$i] [1] & 0xFF);
						$Locals_img { 4 } = chr (($this->OFS [$i] [1] & 0xFF00) >> 8);
					}
					$byte  = ord ($Locals_img { 9 });
					$byte |= 0x80;
					$byte &= 0xF8;
					$byte |= (ord ($this->BUF [0] { 10 }) & 0x07);
					$Locals_img { 9 } = chr ($byte);
					$this->GIF .= ($Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp);
				}
			}
			else {
				// XY Padding...

				if ($this->SIG == 1) {
					$Locals_img { 1 } = chr ($this->OFS [$i] [0] & 0xFF);
					$Locals_img { 2 } = chr (($this->OFS [$i] [0] & 0xFF00) >> 8);
					$Locals_img { 3 } = chr ($this->OFS [$i] [1] & 0xFF);
					$Locals_img { 4 } = chr (($this->OFS [$i] [1] & 0xFF00) >> 8);
				}
				$byte  = ord ($Locals_img { 9 });
				$byte |= 0x80;
				$byte &= 0xF8;
				$byte |= (ord ($this->BUF [$i] { 10 }) & 0x07);
				$Locals_img { 9 } = chr ($byte);
				$this->GIF .= ($Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp);
			}
		}
		else {
			$this->GIF .= ($Locals_ext . $Locals_img . $Locals_tmp);
		}
		$this->IMG  = 1;
	}

	private function addFooter() {
		$this->GIF .= ";";
	}

	private function blockCompare($GlobalBlock, $LocalBlock, $Len) {
		for ($i = 0; $i < $Len; $i++) {
			if ($GlobalBlock { 3 * $i + 0 } != $LocalBlock { 3 * $i + 0 }
			 || $GlobalBlock { 3 * $i + 1 } != $LocalBlock { 3 * $i + 1 }
			 || $GlobalBlock { 3 * $i + 2 } != $LocalBlock { 3 * $i + 2 }) {

				return (0);
			}
		}
		return (1);
	}

	private function intToWord($int) {
		return (chr ($int & 0xFF) . chr (($int >> 8) & 0xFF));
	}

	public function getAnimation() {
		return ($this->GIF);
	}

}

