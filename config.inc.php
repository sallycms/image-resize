<?php
/**
 * Image-Resize Addon
 *
 * @author office[at]vscope[dot]at Wolfgang Hutteger
 * @author <a href="http://www.vscope.at">www.vscope.at</a>
 *
 * @author markus.staab[at]redaxo[dot]de Markus Staab
 *
 * @author jan.kristinus[at]redaxo[dot]de Jan Kristinus
 * @author <a href="http://www.yakamara.de">www.yakamara.de</a>
 */

sly_Loader::addLoadPath(dirname(__FILE__).'/lib');

if (sly_Core::isBackend()) {
	sly_Core::dispatcher()->register('MEDIA_UPDATED', array('A2_Thumbnail', 'mediaUpdated'));
	sly_Core::getI18N()->appendFile(dirname(__FILE__).'/lang');
}
else {
	$rex_resize = sly_get('rex_resize', 'string', null);

	if (!empty($rex_resize)) { // start resizing
		A2_Thumbnail::getResizedImage(urldecode($rex_resize));
	}
	else { // try to make nicer html output
		require_once dirname(__FILE__).'/extensions/extension_wysiwyg.inc.php';
		sly_Core::dispatcher()->register('OUTPUT_FILTER', 'rex_resize_wysiwyg_output');
	}
}
