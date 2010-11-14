<?php
/**
 * Image-Resize Addon
 *
 * @author office[at]vscope[dot]at Wolfgang Hutteger
 * @author <a href="http://www.vscope.at">www.vscope.at</a>
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 *
 *
 * @package redaxo4
 * @version svn:$Id$
 */

// Resize WYSIWYG Editor Images
function rex_resize_wysiwyg_output($params) {
	$content = $params['subject'];

	preg_match_all('#<img [^\>]*src=\"(data\/mediapool\/([^\"]*))[^\>]*>#is', $content, $matches);
	if (is_array($matches[0])) {
		foreach ($matches[0] as $key => $var) {
			preg_match('/width="(.*?)"/is', $var, $width);
			if (!$width) preg_match('/width: (.*?)px/is', $var, $width);
			if ($width) {
				if (file_exists(SLY_BASE.'/data/mediapool/'.$matches[2][$key])) {
					$realsize = getimagesize(SLY_BASE.'/data/mediapool/'.$matches[2][$key]);

					if ($realsize[0] != $width[1]) {
						$newsrc   = 'imageresize/'.$width[1].'w__'.$matches[2][$key];
						$newimage = str_replace($matches[1][$key], $newsrc, $var);
						$content  = str_replace($var, $newimage, $content);
					}
				}
			}
		}
	}

	return $content;
}
