<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

sly_Core::getLayout()->addCSS('
.iresize-help dl { margin-left: 20px }
.iresize-help h3 { margin: 15px 0 10px 0; }
.iresize-help .ex.last { margin-right: 0; }
.iresize-help .ex div { text-align: center; }
.iresize-help .ex {
	float: left;
	width: 240px;
	height: 200px;
	margin-right: 10px;
	margin-bottom: 20px;
	border: 1px solid #4d4646;
	padding: 5px;
}
.iresize-help .ex .img {
	line-height: 180px;
}
.iresize-help .ex .caption {
	font-weight: bold;
}
.iresize-help .ex img {
	vertical-align: middle;
}
');

$file        = sly_Core::config()->get('instname').'.jpg';
$isAvailable = sly_Util_AddOn::isAvailable('sallycms/image-resize');

?>
<div class="iresize-help">
	<p>Dieses AddOn erlaubt es, Bilder aus dem Medienpool über speziell präparierte
	URLs auf eine bestimmte Größe zu skalieren oder Effekte auf sie anzuwenden.</p>

	<h3>Anwendung</h3>

	<p>Eine Datei namens <strong>test.jpg</strong> muss, um von ImageResize verarbeitet
	zu werden, über <strong>/imageresize/XXX__test.jpg</strong> aufgerufen werden.
	<strong>XXX</strong> wird mit den gewünschten Verarbeitungsparametern ersetzt,
	wie zum Beispiel <strong>100w</strong>, um das Bild auf 100px zu verkleinern:
	</strong>/imageresize/100w__test.jpg</strong>.<br />
	Mehrere Aktionen und Filter können über <strong>__</strong> getrennt werden.</p>

	<h3>Aktionen</h3>

	<dl>
		<dt>w (width)</dt>
		<dd>maximale Breite</dd>

		<dt>h (height)</dt>
		<dd>maximale Höhe</dd>

		<dt>a (automatic)</dt>
		<dd>maximale Breite &amp; Höhe sind identisch (<strong>100a</strong> equals <strong>100w__100h</strong>)</dd>

		<dt>c (crop)</dt>
		<dd>Bild auf die angegebenen Maße zuschneiden (Präfix für <strong>w</strong>, <strong>h</strong> und <strong>a</strong>)</dd>
	</dl>

	<p>Statt des gesamten Bildes kann auch nur ein Ausschnitt verarbeitet werden.</p>

	<dl>
		<dt>o (offset)</dt>
		<dd>allgemeiner Rahmen</dd>

		<dt>l (left)</dt>
		<dd>Abstand von links</dd>

		<dt>r (right)</dt>
		<dd>Abstand von rechts</dd>

		<dt>t (top)</dt>
		<dd>Abstand von oben</dd>

		<dt>b (bottom)</dt>
		<dd>Abstand von unten</dd>
	</dl>

	<h3>Filter</h3>

	<dl>
		<dt>blur</dt>
		<dd>Bild verwischen</dd>

		<dt>brand</dt>
		<dd>Wasserzeichen hinzufügen</dd>

		<dt>sepia</dt>
		<dd>Sepia-Filter</dd>

		<dt>sharpen</dt>
		<dd>Schärfe erhöhen</dd>
	</dl>

	<h3>Schalter</h3>

	<dl>
		<dt>u</dt>
		<dd>Hochskalieren erlauben</dd>

		<dt>n</dt>
		<dd>JPEG-Rekompression deaktivieren</dd>

		<dt>t2</dt>
		<dd>JPEG-Datei ausgeben</dd>

		<dt>t3</dt>
		<dd>PNG-Datei ausgeben</dd>
	</dl>

	<h3>Beispiele</h3>

	<?php
	if ($isAvailable) {
		$examples = array(
			'100w', '150h', '200a', '100w__200h', 'c100w__150h', '100w__c150h', 'c100w__c150h',
			'c100w__150h__50o', 'c100w__150h__-50o', 'c100w__150h__50r', 'c100w__150h__50l',
			'c100a', '200a__fblur__fsepia', '200a__u', '200a__n', '200a__t2'
		);

		foreach ($examples as $idx => $ex) {
			?>
			<div class="ex<?php if ($idx > 0 && ($idx+1) % 3 === 0) echo ' last'; ?>">
				<div class="img"><img src="../imageresize/<?php echo $ex ?>__<?php echo $file ?>" alt="" /></div>
				<div class="caption"><?php echo $ex ?>__imagefile</div>
			</div>
			<?php
		}
	}
	else {
		print sly_Helper_Message::info('Bitte aktivieren Sie das AddOn, um die Beispiele in Aktion zu sehen.');
	}
	?>
	<div style="clear:left"></div>
</div>
