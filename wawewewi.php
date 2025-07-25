<?php header('Content-Type: text/html; charset=utf-8');

if (isset($_REQUEST['debug'])) {
	error_reporting(E_ALL);
	ini_set("display_errors", 1);
}
require_once("shared_inc/wiki_functions.inc.php");
$comment_choices = array("keine", "Text eingeben", "Diskussionsseite", "Doppelbewertung wünschen");

?><!-- checks the similarity of two revisions and helps to rate articles and maintenance template contest, called by https://de.wikipedia.org/wiki/Benutzer:Flominator/WaWeWeWi.js -->
<html>

<head>
	<title><?php echo $article ?></title>
	<link rel="stylesheet" type="text/css" href="https://wawewewi.toolforge.org/wbwstyles.css">
</head>

<body>
	<h2>Willkommen beim wirklich wundervollen webbasierten Wartungsbaustein-Wegmach-Wertungs-Wizzard!</h2>
	<script>
		function SetComment(selectedComment) {
			var commentBox = document.getElementById('commentText');
			commentBox.value = "";
			switch (selectedComment) {
				case "<?php echo $comment_choices[1] ?>": //enter text
				{
					commentBox.readOnly = false;
					break;
				}
				case "<?php echo $comment_choices[2] ?>": //talk page
				{
					commentBox.readOnly = true;
					commentBox.value = "[[Wikipedia Diskussion:WBWA#<?php echo $articleenc; ?>]]";
					break;
				}
				case "<?php echo $comment_choices[3] ?>": {
					commentBox.readOnly = true;
					commentBox.value = "Doppelbewertung erwünscht";
					break;
				}
				default: //"keine"
				{
					commentBox.readOnly = true;
					break;
				}
			}
		}

		function RemoveTableAttributesBoth() {
			var oldTextArea = document.getElementById('old_cut');
			var newextArea = document.getElementById('new_cut');
			oldTextArea.value = RemoveTableAttributesOne(oldTextArea.value);
			newextArea.value = RemoveTableAttributesOne(newextArea.value);
		}

		function RemoveTableAttributesOne(SrcText) {
			var TableParts = SrcText.replace(/\n!/g, "\n|").split("|");
			var TableSyntaxElements = new Array("style=", "class=", "width=", "height=", "align=", "bgcolor=", "rowspan=", "colspan=", "valign=");
			var SyntaxTerminators = new Array("\n");

			for (var i = 0; i < TableParts.length; i++) {
				for (var j = 0; j < TableSyntaxElements.length; j++) {
					if (TableParts[i].toLowerCase().search(new RegExp(TableSyntaxElements[j])) != -1) {
						TableParts[i] = "";
					}
				}
			}
			return TableParts.join("|");
		}
	</script>
	<?php

	$src_old_cut = $_REQUEST['old_cut'];
	$src_new_cut = $_REQUEST['new_cut'];
	echo "<!--" . strlen($src_old_cut) . " -->";;

	$template_names = array("Überarbeiten", "Belege fehlen", "Lückenhaft", "Neutralität", "Übersetzungshinweis", "NurListe", "Unverständlich", "Defekte Weblinks", "Geographische Lage gewünscht", "Veraltet", "Widerspruch", "Internationalisierung (Deutschlandlastig, Österreichlastig, Schweizlastig)", "(Portal-)Qualitätssicherung", "Redundanz", "Gemeinfreie Quellen (Meyers, Pierer-1857, Brockhaus, &hellip;)", "Bilderwunsch", "Überbildert", "Fachbereichs-Wartungsliste");
	$template_shortcuts = array("ü", "q", "lü", "pov", "üb", "nl", "uv", "dw", "geo", "alt", "ws", "inter", "qs", "red", "gq", "bw", "übb", "fwl");
	$rater = $_REQUEST['rater'];
	$server = $lang . "." . $project . ".org";

	$bonus_cats = $_REQUEST['bonus_cats'];

	$oldid = getint('oldid');
	$diff = getint('diff');

	order_old_and_diff($oldid, $diff);

	$difflink = "https://$lang.$project.org/w/index.php?title=$articleenc&diff=$diff&oldid=$oldid";
	$difflink = "https://$lang.$project.org/w/index.php?title=$articleenc&diff=$diff&oldid=$oldid";
	$afterlink = "https://$lang.$project.org/w/index.php?title=$articleenc&oldid=$diff";
	$beforelink = "https://$lang.$project.org/w/index.php?title=$articleenc&oldid=$oldid";
	echo "[<a href=\"$beforelink\">vorher</a>]&nbsp;";
	echo "[<a href=\"$difflink\">Diff-Link</a>]&nbsp;";
	echo "[<a href=\"$afterlink\">nachher</a>]&nbsp;";
	echo "<br>";

	if ($src_old_cut == "") {
		ask_to_cut_org($oldid, $diff);
	} else {
		$src_old = remove_textarea_overhead($src_old_cut);
		//compare_old_from_form_with_original($src_old, $oldid, $article);
		//$src_new = removeheaders(get_source_code($article, $diff));
		$src_new = remove_textarea_overhead($src_new_cut);

		$len_old = strlen($src_old);
		$len_new = strlen($src_new);

		$len_diff = $len_new - $len_old;
		$virtualFactor = 1;

		//similar_text($src_old , $src_new , $percent_case_sensitive);

		$similarity = get_similarity($src_old, $src_new);
		$changedPercentDecimal = ((100 - $similarity) / 100);
		$changeSummand = ($len_new  / 1000) * $changedPercentDecimal;
		$additionSummand  = 0;


		$v = "";
		if ($len_diff > 20) {
			$v = $len_old;
			$additionSummand  = $len_diff  / 300;
		}

		$nodiff = "";
		$removalSummand = 0;
		if ($v == "") {
			$nodiff = "|nodiff=x";
			$removalSummand = ($len_new / 500) * $changedPercentDecimal;
		}

		$template = $_REQUEST['template'] - 1;
		$virt = "";
		if ($template_shortcuts[$template] == "virt") {
			$virtualFactor = 2;
			$virt = "|virt=x";
		}

		$quality = getint('percent_quality');
		$qualityFactor = 1;
		$ql = "";
		if ($quality != 100) {
			$qualityFactor = $quality / 100;
			$ql = "|ql=" . $qualityFactor;
		}

		$comments = $_REQUEST['commentText'];
		$anm = "";
		if ($comments != "") {
			$anm = "|anm=" . $comments;
		}
		$freeSummand = get_additional_points();

		$expectedPointResult = (($changeSummand + $additionSummand + $removalSummand + $freeSummand) / $virtualFactor) * $qualityFactor;

		echo "Ähnlichkeit ohne Groß- und Kleinschreibung: " . $similarity  . "&nbsp;%\n<br>";
		echo "Differenz: " . $len_diff . " Bytes<br>";
		echo "zu erwartende Punktzahl: " . round($expectedPointResult, 2);

		echo "<textarea cols=\"150\">{{WBWB|wb=" . $template_shortcuts[$template] . "|v=$v|n=$len_new|ä=" . $similarity . "|frei=" . $freeSummand . "|sr=$rater" . $nodiff . $virt . $ql . $anm . "}}</textarea><br>";
	}
	echo '[<a href="https://de.wikipedia.org/wiki/Benutzer:Flominator">by Flominator</a>]&nbsp;';
	echo '[<a href="https://de.wikipedia.org/wiki/Benutzer_Diskussion:Flominator/WaWeWeWi.js">Feedback/Hilfe</a>]&nbsp;';
	echo '[<a href="https://de.wikipedia.org/wiki/Wikipedia:Wartungsbausteinwettbewerb">WBW</a>]&nbsp;';
	echo '[<a href="https://de.wikipedia.org/wiki/Wikipedia:WBWA">WBW/A</a>]&nbsp;';
	echo '[<a href="https://de.wikipedia.org/wiki/Wikipedia:Wartungsbausteinwettbewerb/Hinweise_f%C3%BCr_Schiedsrichter">Schiri-Hinweise</a>]';

	function getint($field)
	{
		if (isset($_REQUEST[$field])) {
			return intval($_REQUEST[$field]);
		} else {
			return 0;
		}
	}

	function get_additional_points()
	{
		$add = 0;
		$add += getint('num_sources') * 0.5;
		$add += coord_rating(getint('num_coord'));
		$add += dw_rating(getint('num_dw'));
		$add += image_rating(getint('num_upload'));
		$add += getint('bonus_points');

		return $add;
	}

	function image_rating($num_cases)
	{
		$points_return = 0.0;
		switch ($num_cases) {
			case "":
			case "0": {
					break;
				}
			case "1": {
					$points_return +=  4;
					break;
				}
			case "2": {
					$points_return +=  7;
					break;
				}
			case "3": {
					$points_return +=  9;
					break;
				}
			default: {
					$points_return += 9;
					$points_return += ($num_cases - 3);
					break;
				}
		}
		return $points_return;
	}


	function coord_rating($num_cases)
	{
		return get_degressive_rating($num_cases, 0.5, 0.25, 0.125);
	}

	function dw_rating($num_cases)
	{
		return get_degressive_rating($num_cases, 0.5, 0.25, 0.125);
	}
	function get_degressive_rating($num_cases, $pointsFirstOne, $pointsFirstTen, $pointsStartingEleven)
	{
		$points_return = 0.0;

		if ($num_cases == 0) {
			$points_return = 0;
		} else if ($num_cases == 1) {
			$points_return = $pointsFirstOne;
		} else if ($num_cases <= 10) {
			$points_return = ($num_cases - 1) * $pointsFirstTen + $pointsFirstOne;
		} else {
			$points_return = ($num_cases - 10) * $pointsStartingEleven + $pointsFirstTen * 9 + $pointsFirstOne;
		}
		return $points_return;
	}



	function get_similarity($src_old, $src_new)
	{
		similar_text(strtolower($src_old), strtolower($src_new), $percent_case_insensitive);
		return sprintf("%01.2f", $percent_case_insensitive);
	}

	function compare_old_from_form_with_original($src_old, $oldid, $article)
	{
		$src_old_reload = get_source_code($article, $oldid);
		echo "old form: " . strlen($src_old) . "reloaded: " . strlen($src_old_reload);

		$hex_src = hex_chars($src_old);
		$hex_src_reload = hex_chars($src_old_reload);

		$hex_src_hex_ex = explode("|{", $hex_src['hex']);
		$hex_src_reloaded_hex_ex = explode("|{", $hex_src_reload['hex']);

		$hex_src_chr_ex = explode("|{", $hex_src['chars']);
		$hex_src_reloaded_chr_ex = explode("|{", $hex_src_reload['chars']);

		echo "<pre>";

		for ($i = 0; $i < count($hex_src_chr_ex); $i++) {
			if ($hex_src_chr_ex[$i] != $hex_src_reloaded_chr_ex[$i]) {
				echo "<b>";
			}
			echo "#" . $i . "   " . $hex_src_chr_ex[$i] . "(" . $hex_src_hex_ex[$i] . ")";
			echo        "   " . $hex_src_reloaded_chr_ex[$i] . "(" . $hex_src_reloaded_hex_ex[$i] . ")<br>\n";
		}
		echo "</pre>";

		echo "<h1>old</h1> $src_old ";
		echo "<h1>old reloaded</h1> $hex_src_reload";
	}

	function order_old_and_diff(&$oldid, &$diff)
	{
		if ($diff < $oldid) {
			$tempId = $diff;
			$diff = $oldid;
			$oldid = $tempId;
		}
	}

	function remove_textarea_overhead($text)
	{
		$text = preg_replace('(\r\n|\r|\n)', chr(10), $text);
		$text = stripslashes($text);
		return $text;
	}
	function ask_to_cut_org($oldid, $diff)
	{
		global $src_old, $article, $diff, $lang, $project, $template_names, $rater, $comment_choices, $server, $bonus_cats, $is_debug;
		$this_url = "article=$article&oldid=$oldid&diff=$diff&rater=$rater&project=$project&lang=$lang";
		$src_old = get_source_code($article, $oldid);
		$src_new = get_source_code($article, $diff);

		if (max(strlen($src_old), strlen($src_new)) > 110000) {
			echo "<br><br>Mindestens eine der beiden Versionen ist zu lang, um sie per WaWeWeWi auswerten zu lassen. 
		Bitte kürze die Versionen auf die veränderten Abschnitte zusammen und beantrage weitere Bewertungen durch andere Schiedsrichter.";
		} else {

			echo "<form method=\"post\" action=\"wawewewi.php\"  enctype=\"multipart/form-data\">
		Entferne zunächst eventuelle Wartungsbausteine aus dieser mangelhaften Version:<br />
		
		<textarea id=\"old_cut\" name=\"old_cut\" cols=\"80\" rows=\"25\">" . ($src_old) . "</textarea><br/>"
				. "<a href='#' onclick=\"javascript:document.getElementById('new_cut').style['display']='block'\">Hier klicken, um die verbesserte Version zu bearbeiten, um z.&nbsp;B. Nichtteilnehmer-Beiträge zu entfernen&nbsp;</a><br>"
				. "<textarea style=\"display: none;\" id=\"new_cut\" name=\"new_cut\" cols=\"80\" rows=\"25\">" . ($src_new) . "</textarea>";

			echo '<table><tr><td valign="top">';
			// check_bonus_categories($src_old, $bonus_cats);
			check_bonus_categories_reverse_tree($bonus_cats);
			echo '</td><td valign="top">';
			show_removed_templates($article, $src_old, $src_new);
			echo '</td></tr></table>';

			// echo "<!-- <input name=\"old_cut\" value=\"" . htmlentities($src_old) . "\">-->
			if ($is_debug) {
				echo " <input type=\"hidden\" name=\"debug\" value=\"" . $_REQUEST["debug"] . "\">";
			}

			echo "<input type=\"hidden\" name=\"diff\" value=\"$diff\">
		<input type=\"hidden\" name=\"article\" value=\"$article\">
		<input type=\"hidden\" name=\"lang\" value=\"$lang\">
		<input type=\"hidden\" name=\"project\" value=\"$project\">
		<input type=\"hidden\" name=\"oldid\" value=\"$oldid\">"
				. "<label for=\"template\">Wartungsbaustein&nbsp;</label>" .	 array_drop("template",  $template_names) . "<br>"
				. "<label for=\"num_sources\">Anzahl verschiedener Belege&nbsp;</label>"
				. "<input name=\"num_sources\" id=\"num_sources\"><br>"
				. "<label for=\"num_dw\">Anzahl reparierter Weblinks&nbsp;</label>"
				. "<input name=\"num_dw\" id=\"num_dw\"><br>"
				. "<label for=\"num_upload\">Anzahl neu hochgeladener Bilder&nbsp;</label>"
				. "<input name=\"num_upload\" id=\"num_upload\"><br>"
				. "<label for=\"num_coord\">Anzahl hinzugefügter Koordinaten&nbsp;</label>"
				. "<input name=\"num_coord\" id=\"num_coord\"><br>"
				. "<label for=\"bonus_points\">Bonuspunkte (z.&nbsp;B. für Kategorien)&nbsp;</label>"
				. "<input name=\"bonus_points\" id=\"bonus_points\"><br>"
				. "<label for=\"percent_quality\">Korrekturfaktor (in Prozent)&nbsp;</label>"
				. "<input name=\"percent_quality\" id=\"percent_quality\" value=\"100\">"
				. "<button name=\"alt5\" id=\"alt5\" type=\"button\" onclick=\"javascript:document.getElementById('percent_quality').value='110';document.getElementById('commentText').value='alt5';\" text=\"alt5\">alt5</button>"
				. "<button name=\"alt10\" id=\"alt10\" type=\"button\" onclick=\"javascript:document.getElementById('percent_quality').value='120';document.getElementById('commentText').value='alt10';\">alt10</button>"
				. "<button name=\"alt15\" id=\"alt15\" type=\"button\" onclick=\"javascript:document.getElementById('percent_quality').value='130';document.getElementById('commentText').value='alt15';\">alt15</button><br>"
				. 'Tabellensyntax in obigen Textfeldern <a href="#" onclick="javascript:RemoveTableAttributesBoth();"> entfernen</a><br />'
				. "<label for=\"comment\">Anmerkung&nbsp;</label>" .	 array_drop("comment", $comment_choices, "", "", "SetComment(this.options[this.selectedIndex].text)", $comment_choices[1]) . "<br>"
				. "<input name=\"commentText\"  id=\"commentText\" size=\"100\"><br>"
				. "<label for=\"rater\">Schiedsrichter&nbsp;</label><br>"
				. "<input name=\"rater\"  id=\"rater\" value=\"$rater\"><br>"
				. "<input type=\"submit\" value=\"Auswerten\"></form>";
		}
	}
	function check_bonus_categories_reverse_tree($bonus_cats)
	{
		global $articleenc, $src_old, $cat_article, $cat_bonus;
		$urlSvg = "https://tools.wmflabs.org/catscan2/reverse_tree.php?doit=1&language=de&project=wikipedia&namespace=0&title=" . $articleenc;

		echo "<h3>Bonus-<a href=\"" . $urlSvg . "\">Kategorien</a></h3>";
		echo "<ul>";
		$cats = extract_categories($src_old);

		if ($svg = curl_request($urlSvg)) {
			foreach ($bonus_cats as $cat_bonus) {
				if (stristr($svg, 'de.wikipedia.org/wiki/Category:' . $cat_bonus . "'")) {
					echo "<li>$cat_article => $cat_bonus";
					echo "</li>";
				}
			}
		} else {
			echo "nicht verfügbar ($cat_bonus)<!-- getting $urlSvg failed -->";
		}

		echo "</ul>";
	}

	function extract_categories($src)
	{
		$cats = [];
		$catBeginning = '[[Kategorie:';
		$startIndex = strpos($src, $catBeginning);
		while ($startIndex > 0) {
			$startIndex += strlen($catBeginning);
			$endIndex = strpos($src, ']]', $startIndex);
			$endIndexPipe =  strpos($src, '|', $startIndex);
			if ($endIndexPipe > -1 && $endIndexPipe < $endIndex) {
				$endIndex = $endIndexPipe;
			}
			$len = $endIndex - $startIndex;
			$oneCat = substr($src, $startIndex, $len);
			$cats[] = $oneCat;
			$startIndex = strpos($src, $catBeginning, $endIndex);
		}
		return $cats;
	}

	function show_removed_templates($article, $src_old, $src_new)
	{
		echo "<h3>Entfernte Bausteine</h3>";
		$removedTemplates = find_removed_markers($src_new, scan_for_marker_templates($src_old));
		echo "<ul>";
		foreach ($removedTemplates as $rem) {
			echo "<li>$rem ";
			echo " <small>" . link_to_wikiblame($article, $rem, 5, "alt5?", false) . "</small>";
			echo " <small>" . link_to_wikiblame($article, $rem, 10, "alt10?", false) . "</small>";
			echo " <small>" . link_to_wikiblame($article, $rem, 15, "alt15?", false) . "</small>";
			echo " <small>" . link_to_wikiblame($article, $rem, 10, "wann?", true) . "</small>";
			echo "</li>";
		}
		echo "</ul>";
	}

	function link_to_wikiblame($articleenc, $needle, $years, $alias, $binary_search)
	{
		$day = $_REQUEST['start-day'];
		$mon = $_REQUEST['start-month'];
		$currentYear = $_REQUEST['start-year'];
		$targetYear = $currentYear - $years;
		echo '<a href="//blame.toolforge.org/wikiblame.php?project=wikipedia&article=' . $articleenc . '&needle=' . urlencode($needle) . '&lang=de&force_wikitags=on';

		if ($binary_search) {
			echo '&searchmethod=bin';
		} else {
			echo "&limit=1&offjahr=$targetYear&offmon=$mon&offtag=$day&offhour=23&offmin=59&searchmethod=lin";
		}
		echo "\">$alias</a>";
	}
	function find_removed_markers($src, $templates_in_old)
	{
		$templatesStillPresent = [];
		foreach ($templates_in_old as $oneTemplate) {
			if (!stristr($src, $oneTemplate)) {
				$templatesStillPresent[] = $oneTemplate;
			}
		}
		return $templatesStillPresent;
	}
	function scan_for_marker_templates($src)
	{
		$templatesAvailable[] = "überarbeiten";
		$templatesAvailable[] = "Überarbeiten";
		$templatesAvailable[] = "Belege fehlen";
		$templatesAvailable[] = "Quelle";
		$templatesAvailable[] = "Quellen";
		$templatesAvailable[] = "Belege";
		$templatesAvailable[] = "Lückenhaft";
		$templatesAvailable[] = "Unvollständig";
		$templatesAvailable[] = "Übersetzungshinweis";
		$templatesAvailable[] = "Neutralität";
		$templatesAvailable[] = "POV";
		$templatesAvailable[] = "NPOV";
		$templatesAvailable[] = "Nur Liste";
		$templatesAvailable[] = "NurListe";
		$templatesAvailable[] = "Liste";
		$templatesAvailable[] = "Nur Zitate";
		$templatesAvailable[] = "Unverständlich";
		$templatesAvailable[] = "Veraltet";
		$templatesAvailable[] = "Zukunft";
		$templatesAvailable[] = "Widerspruch";
		$templatesAvailable[] = "Staatslastig";
		$templatesAvailable[] = "Deutschlandlastig";
		$templatesAvailable[] = "Schweizlastig";
		$templatesAvailable[] = "Österreichlastig";
		$templatesAvailable[] = "QS";
		$templatesAvailable[] = "Qualitätssicherung";
		$templatesAvailable[] = "Redundanztext";
		$templatesAvailable[] = "Meyers";
		$templatesAvailable[] = "Hinweis Meyers 1888–1890";
		$templatesAvailable[] = "Hinweis Pierer 1857–1865";
		$templatesAvailable[] = "Hinweis Brockhaus 1893–1897";
		$templatesAvailable[] = "Bilderwunsch";
		$templatesAvailable[] = "Überbildert";
		$templatesAvailable[] = "Toter Link";
		$templatesAvailable[] = "Dead link";

		$templatesFound = [];
		$partsWithTemplates = explode("{{", $src);

		foreach ($partsWithTemplates as $templateStart) {
			$templateStart = ltrim($templateStart);
			foreach ($templatesAvailable as $templ) {
				$startCut = substr($templateStart, 0, strlen($templ));
				if (strtolower($startCut) == strtolower($templ)) {
					$templateEnd = strpos($templateStart, "}");
					$templatesFound[] = '{{' . substr($templateStart, 0, $templateEnd) . '}}';
				}
			}
		}
		return $templatesFound;
	}

	function hex_chars($data)
	{
		$mb_chars = '';
		$mb_hex = '';
		for ($i = 0; $i < mb_strlen($data, 'UTF-8'); $i++) {
			$c = mb_substr($data, $i, 1, 'UTF-8');
			$mb_chars .= '{' . ($c) . '}';

			$o = unpack('N', mb_convert_encoding($c, 'UCS-4BE', 'UTF-8'));
			$mb_hex .= '{' . hex_format($o[1]) . '}';
		}
		$chars = '';
		$hex = '';
		for ($i = 0; $i < strlen($data); $i++) {
			$c = substr($data, $i, 1);
			$chars .= '|{' . ($c) . '}';
			$hex .= '|{' . hex_format(ord($c)) . '}';
		}
		return array(
			'data' => $data,
			'chars' => $chars,
			'hex' => $hex,
			'mb_chars' => $mb_chars,
			'mb_hex' => $mb_hex,
		);
	}
	function hex_format($o)
	{
		$h = strtoupper(dechex($o));
		$len = strlen($h);
		if ($len % 2 == 1)
			$h = "0$h";
		return $h;
	}
	function get_source_code($article, $rev)
	{
		$articleenc = name_in_url($article);
		global $server;
		$url = "https://" . $server . "/w/index.php?title=" . $articleenc . "&action=raw&oldid=$rev";
		//echo $url;

		//echo "<br><br>Suche nach $needle in $article";
		if (!$article_text = file_get_contents($url)) {
			//echo "error";
			die("klappt nicht");
		}
		return $article_text;
	}
	?>
	<p><a href="https://admin.toolforge.org/" title="Powered by Toolforge"><img src="https://tools-static.wmflabs.org/toolforge/banners/Powered-by-Toolforge.png" alt="Banner Toolforge"></a></p>
</body>

</html>