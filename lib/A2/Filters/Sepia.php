<?php

class A2_Filters_Sepia {
	public static function filter($src_im) {
		return A2_Filters_Monochrome::filter($src_im, 1.01, 0.98, 0.90);
	}
}
