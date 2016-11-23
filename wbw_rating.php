<?php header('Content-Type: text/html; charset=utf-8'); 

$is_debug = ($_REQUEST['debug']=="on" || $_REQUEST['debug']=="true" );
const NUMBER_OF_TOP_IMPROVEMENTS = 10;
$oldid = $_REQUEST['oldid']+0;
$unsorted = $_REQUEST['unsorted'];
include("shared_inc/wiki_functions.inc.php");
$server = "$lang.$project.org";
$article = "Wikipedia:Wartungsbausteinwettbewerb/".name_in_url($_REQUEST['edition']);
$wbw_page = "https://".$server."/w/index.php?title=".$article.'&oldid='.$oldid;

echo '<h1>Zwischenstände';
if($oldid > 0)
{
	echo ' aus <a href="'.$wbw_page.'">dieser Version</a> ';
}
else
{
	purge($server, $article, $is_debug);
}
echo '</h1>';
$points_per_team = rate_teams($server, $wbw_page);

sort_and_print_score_list($points_per_team, "Points", SORT_DESC);
sort_and_print_biggest_improvements($allImprovements);
sort_and_print_template_list($fixedTemplates, 'Bausteine');
sort_and_print_template_list($refereeRatings, 'Schiris');

if($oldid==0)
{
	print_form($wbw_page, update_paragraphs(update_summary_paragraph(get_source_code_paragraphs($server, $wbw_page)), $points_per_team), $article);
}

function rate_teams($server, $wbw_page)
{
	global $is_debug, $html_page, $allImprovements;
	$html_page = file_get_contents($wbw_page);
	$team_paragraphs = explode("h6>", $html_page);
	$points_per_team;//[] = array("Team"=> "Dummy", "Points"=>"-1");

	for($iTeam = 1;$iTeam<count($team_paragraphs);$iTeam++)
	{
		//team name
		$team_name = str_replace("[Quelltext bearbeiten]", "", strip_tags($team_paragraphs[$iTeam]));
		if($is_debug) echo "Team=$team_name <br>";
		$iTeam++; // ignore next part (end of h6-Tag)
		
		$columns = explode("</td>", $team_paragraphs[$iTeam]);
		
		$list_of_article_points = explode("(", remove_italic_parts($columns[2]));
		$numberOfTeamMembers = get_number_of_users($columns[0]);
		
		$improvementsBeforeThisTeam = count($allImprovements);
		$points_of_this_team = count_points_of_team($list_of_article_points);
		$improvementsByThisTeam = count($allImprovements) - $improvementsBeforeThisTeam;

		$totalPointsOfThisTeam = $points_of_this_team;
		if($numberOfTeamMembers>3)
		{
			$points_of_this_team = 3* ($points_of_this_team / $numberOfTeamMembers);
		}
		$points_per_team[] = array(	"Team" => $team_name, 
									"Points" => $points_of_this_team, 
									"NumberMembers" => $numberOfTeamMembers, 
									"TotalPoints" => $totalPointsOfThisTeam,
									"ImprovedArticles" => $improvementsByThisTeam);
		//echo "Team:" . $team_name . "Points:" . $points_of_this_team;
		//echo $points_per_team[count($points_per_team)-1]["Team"]."//".$points_per_team[count($points_per_team)-1]["Points"];
		if($is_debug) echo "<hr>";
	}
	return $points_per_team;
}

function remove_italic_parts($team_paragraph)
{
	$indexOfItalicStart = strpos($team_paragraph, '<i>');
		
	while($indexOfItalicStart > 0)
	{
		$indexOfItalicEnd = strpos($team_paragraph, '</i>', $indexOfItalicStart) + strlen('</i>');
		$team_paragraph = substr($team_paragraph, 0, $indexOfItalicStart) .  substr($team_paragraph,$indexOfItalicEnd);
		$indexOfItalicStart = strpos($team_paragraph, '<i>', $indexOfItalicStart); 
		//do not use end as offset, because it was measured before cutting the part out

	}
	//remove remaindings of the template parameter
	return str_replace('||', '|', $team_paragraph);
}

function get_number_of_users($allUserNames)
{
	global $is_debug;
	if($is_debug) echo "allUserNames=".htmlspecialchars($allUserNames);
	$userLines = explode("<li><a href", $allUserNames);
	$numberOfTeamMembers = 0;
	foreach($userLines as $oneUserLine)
	{
		if ($is_debug) echo "line is $oneUserLine <br>";
		$oneLineLower = strtolower($oneUserLine);
		if(stristr($oneUserLine, "benutzer") ||stristr($oneUserLine, "user"))
		{
			$numberOfTeamMembers++;
		}
	}
	if($is_debug) echo "Team has " + $numberOfTeamMembers + " members";
	return $numberOfTeamMembers;
}

function count_points_of_team ($list_of_article_points)
{
	global $nextArticles;
	$points_of_this_team = 0;
	$nextArticles = "";
	$max = count($list_of_article_points);
	for($i=0;$i<$max;$i++)
	{
		$points_of_this_team+=extract_data_for_one_article($list_of_article_points, $i);
	}
	return $points_of_this_team;
}

function extract_data_for_one_article($list_of_article_points, $i)
{
	global $is_debug, 
	$fixedTemplates, 
	$refereeRatings,
	$nextArticles,
	$allImprovements;
	$fPoints = 0;
	$one_rated_article = $list_of_article_points[$i];
	if ($is_debug) echo "UU". $one_rated_article ."UU";

	$indexFirstPipe = strpos($one_rated_article, "|");
	
	if($indexFirstPipe > 0)
	{
		$indexFirstPipe++;
		$indexNextPipe = strpos($one_rated_article, "|", $indexFirstPipe);
		if($indexNextPipe >= 0)
		{
			if ($is_debug) echo "first:" . $indexFirstPipe . " next: " . $indexNextPipe;
			$fPoints = substr($one_rated_article, $indexFirstPipe, $indexNextPipe-$indexFirstPipe);
			
			if($fPoints>0)
			{
				if ($is_debug) echo "points: ". $fPoints;

				$templateName = substr($one_rated_article,0, $indexFirstPipe-1);				
				$referee = substr($one_rated_article, $indexNextPipe+1, 1);
				$refereeRatings[$referee]++;
				
				$numArticles = count( explode('title="', $nextArticles) )-1;
				$fixedTemplates[$templateName] = $fixedTemplates[$templateName]+$numArticles;
				
				$article = extract_article_names($nextArticles);
				$nextArticles = "";
	
				$allImprovements[$article] = $fPoints;
			}
		}
	}
	$nextArticles.='('.$list_of_article_points[$i]; //undo explosion to put together articles
	return $fPoints;
}

function extract_article_names($nextArticles)
{
	$article = strip_tags($nextArticles);
	$endOfRating = strpos($article, ')')+1;
	$article = substr($article, $endOfRating);
	if(substr($article, 0, 1)=='}')
	{
		$article = substr($article, 1);
	}

	if(substr($article, 0, 1)==',')
	{
		$article = substr($article, 1);
	}
	if(substr($article, 0, 1)=='{')
	{
		$article = substr($article, 1);
	}
	return $article;
}

function sort_and_print_score_list($points_per_team, $sortKey, $sortOrder)
{
	global $unsorted;
	$r = 'style="text-align: right;"';
	echo '<h2>Teams</h2>';
	foreach ($points_per_team as $nr => $inhalt)
	{
		$points[$nr]  = strtolower( $inhalt[$sortKey] );
	}
	

	if(!$unsorted==true)
	{
		array_multisort($points, $sortOrder, $points_per_team);
	}

	//print_r (  $points_per_team );

	echo '<table border="1">';
	echo "<tr>";
	echo "<td>#</td>";
	echo "<td>Team</td>";
	echo "<td $r>Punkte</td>";
	echo "<td $r>Artikel</td>";
	echo "<td $r>P/A</td>";
	echo "</tr>";
	$max = count($points_per_team);
	for($i=0;$i<$max;$i++)
	{
		$rate = round($points_per_team[$i]["Points"] / $points_per_team[$i]["ImprovedArticles"], 2);
		echo "<tr>";
		echo "<td>".($i+1)."</td>";
		echo "<td>".$points_per_team[$i]["Team"]."</td>";
		echo "<td $r>".number_format ( $points_per_team[$i]["Points"] , 2 , "," , "" )."</td>";
		echo "<td $r>".$points_per_team[$i]["ImprovedArticles"]."</td>";
		echo "<td $r>".number_format ( $rate , 2 , "," , "" )."</td>";
		echo "</tr>";
	}
	echo "</table>";
}

function sort_and_print_template_list($fixedTemplates, $headline)
{
	echo '<h2>'.$headline.'</h2>';
	arsort($fixedTemplates, SORT_NUMERIC);
	echo '<table border="1">';
	echo "<tr>";
	foreach(array_keys($fixedTemplates) as $templ)
	{
		echo "<th>$templ</th>";
	}
	echo "</tr>";
	echo "<tr>";
	foreach(array_keys($fixedTemplates) as $templ)
	{
		echo '<td style="text-align: center">'.$fixedTemplates[$templ]."</td>";
	}
	echo "</tr>";
	echo "</table>";
}

function sort_and_print_biggest_improvements($allImprovements)
{
	echo '<h2>Umfangreichste Bearbeitungen</h2>';
	echo "<ol>";
	arsort($allImprovements, SORT_NUMERIC);
	$keys = array_keys($allImprovements);
	for($i=0;$i<NUMBER_OF_TOP_IMPROVEMENTS;$i++)
	{
		echo "<li>". $keys[$i] .' ('.$allImprovements[$keys[$i]].")</li>";
	}
	echo "</ol>";
}

function get_source_code_paragraphs($server, $wbw_page)
{
	$wbw_page_raw = $wbw_page. "&action=raw";
	$source_code_page = file_get_contents($wbw_page_raw); 

	$paragraphs = explode("\n======", $source_code_page);
	return $paragraphs;
}

function update_summary_paragraph($paragraphs)
{
	global $is_debug;
	if($is_debug) echo "<h1>Para 0 before</h1>".htmlspecialchars($paragraphs[0])."<hr>";
	$tableHeadLine ='Wettbewerbstabelle ==';
	$commentEnd = "-->";
	$endTableHeadLine = strpos($paragraphs[0], $tableHeadLine) + strlen($tableHeadLine);
	$endComment =   strpos($paragraphs[0], $commentEnd) + strlen($commentEnd);
	$endTableHeadLine = max($endTableHeadLine, $endComment);
	
	$tableTemplate = '{{Wikipedia:Wartungsbausteinwettbewerb/Vorlage Tabelle}}';
	$beginningOfTableTemplate = strpos($paragraphs[0], $tableTemplate, $endTableHeadLine);
	
	$templatesTable = build_templates_table_wiki();
	
	$paragraphs[0] = substr($paragraphs[0], 0, $endTableHeadLine) . $templatesTable . substr($paragraphs[0], $beginningOfTableTemplate);
	if($is_debug) echo "<h1>Para 0 after</h1>".htmlspecialchars($paragraphs[0])." <hr>";
	return $paragraphs;
}

function build_templates_table_wiki()
{
	global $fixedTemplates;
	$table = "\n" . '{| class="wikitable" width="100%" ' ."\n" .'|- class="hintergrundfarbe8"';
	$icons = get_template_icons();
	foreach(array_keys($icons) as $templ)
	{
		if($fixedTemplates[$templ]>0)
		{
			$table.="\n! " . $fixedTemplates[$templ] . ' ' . $icons[$templ];
		}
		else
		{
			$table.="\n! " . 0 . ' ' . $icons[$templ];
		}
		
	}
	$table.="\n|}\n";
	return $table;
}

function update_paragraphs($paragraphs, $points_per_team)
{
	global $is_debug;
	$max = count($paragraphs );
	for($iParagraph=1;$iParagraph<$max;$iParagraph++)
	{
		if($is_debug) echo "<h1>Para $iParagraph before</h1>".htmlspecialchars($paragraphs[$iParagraph])."<hr>";

		for($i=0;$i<count($points_per_team);$i++)
		{
			$i_team_length = strlen($points_per_team[$i]["Team"]);
			if($is_debug) echo "i_team_length=$i_team_length";
																											// no offset last time ... strange
			$clean_team_name_from_source_code = substr(htmlspecialchars(strip_tags($paragraphs[$iParagraph])), 1, $i_team_length);
			if($is_debug) echo "comparing \"" . $clean_team_name_from_source_code  . "\" to  \"<b>" . $points_per_team[$i]["Team"]."\"</b><br>";
			if($clean_team_name_from_source_code==$points_per_team[$i]["Team"])
			{
				if($is_debug) echo "found team " . $points_per_team[$i]["Team"];
				$paragraphs[$iParagraph] = update_one_team_paragraph($paragraphs[$iParagraph], $points_per_team[$i]);
				$i = count($points_per_team);
			}
		}
		if($is_debug) echo "<h1>Para $iParagraph after</h1>".htmlspecialchars($paragraphs[$iParagraph])." <hr>";
	}
	return $paragraphs;
}
	
function print_form($wbw_page, $paragraphs, $article)
{
	echo '<form method="post" action="' . $wbw_page . '&action=submit">';
	set_up_media_wiki_input_fields("Zwischenstände aktualisiert", "Aktualisieren", $article);
	
	if(!$is_debug) $style="style=\"display: none;\"";
	echo "<textarea autocomplete=\"off\" name=\"wpTextbox1\" cols=\"80\" rows=\"25\" $style >" . implode($paragraphs, "\n======"). "</textarea><br>";
	echo '</form>';
}

function remove_existing_result_template($one_paragraph)
{
	global $is_debug;
	$result_template_name = "{{Wikipedia:Wartungsbausteinwettbewerb/Vorlage Ergebnis";
	$clean_paragraph = $one_paragraph;
	if(stristr($one_paragraph, $result_template_name))
	{
		if($is_debug) echo "vorlage gefunden";
		$beginning_of_template = strpos($one_paragraph, $result_template_name);
		if(substr($one_paragraph,$beginning_of_template-1, 1)=="\n")
		{
			if($is_debug) echo "newline found and removed";
			$beginning_of_template--;
		}
		$end_template_marker = "}}";
		$end_of_template = strpos($one_paragraph, $end_template_marker, $beginning_of_template)+ strlen($end_template_marker);
		$clean_paragraph = substr($one_paragraph, 0, $beginning_of_template) .  substr($one_paragraph, $end_of_template);
		
		if($is_debug)
		{
			echo "before removing: II" . $one_paragraph . "II<br>";
			echo "after removing: II".  $clean_paragraph  . "II<br>";
		}
	}
	return $clean_paragraph;
}

function get_place_for_template_insertion($one_paragraph)
{
	global $is_debug;
	$end_of_wbw_table = "\n<!-- ############" ;
	$index_to_insert_result_template = strrpos($one_paragraph, "-")-2; //= end of the table row is \n|-
	
	if(stristr($one_paragraph, $end_of_wbw_table))
	{
		$index_to_insert_result_template =  strpos($one_paragraph, $end_of_wbw_table);
	}
	if ($is_debug) echo "insertion at  $index_to_insert_result_template";
	return $index_to_insert_result_template;
}

function get_result_template($point_set_this_team)
{
	global $wbw_page;
	$oldid = get_old_id();
	$anchor = sprintf("%04d",ceil($point_set_this_team["Points"]));
	$permalink = "[".$wbw_page."&oldid=".$oldid . strftime(" %d.%m.")."]";

	$points_of_this_team =  $point_set_this_team["Points"];
	$result_param = "Ergebnis=$points_of_this_team";
	if($point_set_this_team["NumberMembers"] >= 4)
	{
		$result_param = "Rechnung+Ergebnis=<math>3\cdot \\frac{". $point_set_this_team["TotalPoints"] ."}{". $point_set_this_team["NumberMembers"]."}=".sprintf("%0.1f", $points_of_this_team)."</math>";
	}
	
	$text_to_insert = "\n{{Wikipedia:Wartungsbausteinwettbewerb/Vorlage Ergebnis\n|Anker=$anchor|Zwischenergebnis=$permalink|".$result_param."}}";
	return $text_to_insert;
}

function get_old_id()
{
	global $is_debug, $html_page;
	$revision_prefix = "\"wgCurRevisionId\":";
	$index_of_revision_id = strpos($html_page, $revision_prefix );
	$end_of_revision_id = strpos($html_page, ",", $index_of_revision_id);

	$oldid = substr($html_page, $index_of_revision_id + strlen($revision_prefix ), $end_of_revision_id -$index_of_revision_id - strlen($revision_prefix ) );
	if($is_debug) echo "oldid=". $oldid;
	return $oldid;
}
function update_one_team_paragraph($one_paragraph, $point_set_this_team)
{
	global $is_debug;
	$one_paragraph = remove_existing_result_template($one_paragraph);
	$index_for_insertion = get_place_for_template_insertion($one_paragraph);
	$result_template = get_result_template($point_set_this_team);
		
	//$text_before = substr($one_paragraph, 0, $index_for_insertion);
	//$text_after = substr($one_paragraph, $index_for_insertion);
	return str_insert($result_template, $one_paragraph, $index_for_insertion);
	if($is_debug) 
	{
		$separator = "II";
	}
	return  $text_before .  $separator . $result_template .  $separator  .$text_after;
}

function str_insert($insertstring, $intostring, $offset) 
{
   $part1 = substr($intostring, 0, $offset);
   $part2 = substr($intostring, $offset);
 
   $part1 = $part1 . $insertstring;
   $whole = $part1 . $part2;
   return $whole;
}
 
 function get_template_icons()
 {
	 $icons['ü'] = '[[Datei:Qsicon Ueberarbeiten.svg|Überarbeiten|verweis=Kategorie:Wikipedia:Überarbeiten|15px]]';
	 $icons['q'] = '[[Datei:Qsicon Quelle.svg|Belege fehlen|verweis=Kategorie:Wikipedia:Belege fehlen|15px]]';
	 $icons['lü'] = '[[Datei:Qsicon Lücke.svg|Lückenhaft|verweis=Kategorie:Wikipedia:Lückenhaft|15px]]';
	 $icons['pov'] = '[[Datei:Qsicon Achtung.svg|Neutralität|verweis=Kategorie:Wikipedia:Neutralität|15px]]';
	 $icons['nl'] = '[[Datei:QSicon Formatierung.svg|NurListe|verweis=Kategorie:Wikipedia:Nur Liste|15px]]';
	 $icons['uv'] = '[[Datei:Qsicon Unverstaendlich.svg|15x15px|link=Kategorie:Wikipedia:Unverständlich]]';
	 
	 $icons['ws'] = '[[Datei:Split-arrows.svg|Widerspruch|verweis=Kategorie:Wikipedia:Widerspruch|15px]]';
	 $icons['inter'] = '[[Datei:German-Language-Flag.svg|Internationalisierung|verweis=Kategorie:Wikipedia:Internationalisierung|15px]]';
	 $icons['qs'] = '[[Datei:Icon tools.svg|Qualitätssicherung|verweis=Kategorie:Wikipedia:Qualitätssicherung|15px]]';
	 $icons['red'] = '[[Datei:Merge-arrows.svg|Redundanz|verweis=Kategorie:Wikipedia:Redundanz|15px]]';
	 $icons['gq'] = '[[Datei:Meyerskonvlexikon.jpg|Meyers|verweis=Kategorie:Wikipedia:Meyers|15px]]';
	 $icons['alt'] = '[[Datei:QSicon rot Uhr.svg|veraltet|verweis=Kategorie:Wikipedia:veraltet|15px]]';
	 $icons['dw'] = '[[Datei:Qsicon Weblink red.svg|15x15px|link=Kategorie:Wikipedia:Defekte Weblinks|15px]]';
	 $icons['geo'] = '[[Datei:Gnome-globe.svg|Lagewunsch|verweis=Kategorie:Wikipedia:Lagewunsch|15px]]';
	 $icons['fwl'] = 'Wartungsliste';
	return $icons;
 }	

?>