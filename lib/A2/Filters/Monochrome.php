<?php

class A2_Filters_Monochrome {
	public static function filter($src_im, $rFactor = 1.0, $gFactor = 1.0, $bFactor = 1.0) {
		$src_x  = ceil(imagesx($src_im));
		$src_y  = ceil(imagesy($src_im));
		$dst_x  = $src_x;
		$dst_y  = $src_y;
		$dst_im = imagecreatetruecolor($dst_x, $dst_y);

		if(function_exists('imageantialias')) imageantialias($dst_im, true); // PHP compiled with gd and > 4.3.2
		imagecopyresampled($dst_im, $src_im, 0, 0, 0, 0, $dst_x, $dst_y, $src_x, $src_y);

		// Change style of image pixelwise
		for ($y = 0; $y < $dst_y; ++$y) {
			for ($x = 0; $x < $dst_x; ++$x) {
				$col  = imagecolorat($dst_im, $x, $y);
				$r    = ($col & 0xFF0000) >> 16;
				$g    = ($col & 0x00FF00) >> 8;
				$b    = $col & 0x0000FF;
				$grey = (min($r, $g, $b) + max($r, $g, $b)) / 2;

				// Boost colors
				$boost       = 1.2;
				$boostborder = 250;

				for ($i = 0; $i < 25; ++$i) {
					if ($grey > $boostborder) {
						$grey *= $boost;
						break;
					}

					$boost       -= .01;
					$boostborder -= 10;
				}

				// Set sepia palette

				$r = $grey * $rFactor;
				$g = $grey * $gFactor;
				$b = $grey * $bFactor;

				// round to positive int values

				$r = abs(round($r));
				$g = abs(round($g));
				$b = abs(round($b));

				// Correct max values

				$r   = min($r, 255);
				$g   = min($g, 255);
				$b   = min($b, 255);
				$col = imagecolorallocate($dst_im, $r, $g, $b);

				imagesetpixel($dst_im, $x, $y, $col);
			}
		}

		return $dst_im;
	}
}