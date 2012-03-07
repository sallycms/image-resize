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
sly_Core::getI18N()->appendFile(dirname(__FILE__).'/lang');

if (!sly_Core::isBackend()) {
	$dispatcher = sly_Core::dispatcher();

	$dispatcher->register(sly_Service_Asset::EVENT_PROCESS_ASSET, array('A2_Extensions', 'resizeListener'));
	$dispatcher->register(sly_Service_Asset::EVENT_REVALIDATE_ASSETS, array('A2_Extensions', 'translateListener'));

	require_once dirname(__FILE__).'/extensions/extension_wysiwyg.inc.php';
	$dispatcher->register('OUTPUT_FILTER', 'rex_resize_wysiwyg_output');
}
