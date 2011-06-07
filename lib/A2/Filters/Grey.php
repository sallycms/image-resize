<?php
/**
 * Alias class for monochrome with default values
 */
class A2_Filters_Grey {
	public static function filter($src_im) {
		return A2_Filters_Monochrome::filter($src_im);
	}
}
