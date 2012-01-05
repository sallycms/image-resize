<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

sly_Core::getLayout()->addCSS('
.iresize-help dl { margin-left: 20px }
.iresize-help dl.ex dt { float: none; }
.iresize-help h3 { margin: 15px 0 10px 0; }
');

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

	<dl class="ex">
		<dt>100w__imagefile</dt>
		<dd>resize image to a length of 100px and calculate heigt to match ratio</dd>

		<dt>150h__imagefile</dt>
		<dd>resize image to a height of 150px and calculate width to match ratio</dd>

		<dt>200a__imagefile</dt>
		<dd>resize image on the longest side to 200px and calculate the other side to match ratio</dd>

		<dt>100w__200h__imagefile</dt>
		<dd>resize image to a width of 100px and a heigt of 200px</dd>

		<dt>c100w__200h__imagefile</dt>
		<dd>resize image to a heigt of 200px and crop to a width of 100px if nessessary</dd>

		<dt>100w__c200h__imagefile</dt>
		<dd>resize image to a width of 100px and crop to a height of 200px if nessessary</dd>

		<dt>c100w__c200h__imagefile</dt>
		<dd>crop image to a width of 100px and a height of 200px</dd>

		<dt>c100w__200h__50o__imagefile</dt>
		<dd>resize image to a heigt of 200px and crop to a width of 100px with an offset of 50px</dd>

		<dt>c100w__200h__-150o__imagefile</dt>
		<dd>resize image to a heigt of 200px and crop to a width of 100px with an offset of -150px</dd>

		<dt>c100w__200h__150r__imagefile</dt>
		<dd>resize image to a heigt of 200px and crop to a width of 100px with an offset of 150px from the right edge</dd>

		<dt>c100w__200h__50l__imagefile</dt>
		<dd>resize image to a heigt of 200px and crop to a width of 100px with an offset of 50px from the left edge</dd>

		<dt>c100a__imagefile</dt>
		<dd>resize and crop image to a square of 100x100px</dd>

		<dt>200a__fblur__fsepia__imagefile</dt>
		<dd>add filters: here blur and sepia</dd>

		<dt>200a__u__imagefile</dt>
		<dd>allow upscaling of smaller images</dd>

		<dt>200a__n__imagefile</dt>
		<dd>disable recompression of JPEGs</dd>

		<dt>200a__t2__imagefile</dt>
		<dd>set image type for thumbnail: here 2 for JPEG and 3 for PNG</dd>
	</dl>
</div>
