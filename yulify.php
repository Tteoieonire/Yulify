<?php
$overwrite = isset($_POST['d']);
$utf8 = isset($_POST['utf8']);
$stage = 0;

if (isset($_POST['s'])) { # if submitted
	$filename = $_FILES['f']['name'];
	$filename = substr($filename, strrpos($filename, "."));
	
	if ($filename != ".nwctxt")
		$err_msg = "<p>$filename 파일 형식은 현재 지원되지 않습니다.<br>.nwctxt 파일을 올려주세요.</p>";
	else {
		$filename = $_FILES['f']['tmp_name'];
		
		if (is_uploaded_file($filename)) $stage = 1;
		else
			$err_msg = "<p>파일을 미처 올리지 못했습니다.</p>";
	}
}
else $err_msg = "<p>아무런 정보도 전달받지 못했습니다.</p>";

if ($stage == 1) {
	$filearray = file($filename, FILE_IGNORE_NEW_LINES);
	if ($filearray !== false) $stage = 2;
}

if ($stage == 2) {
	$err = storeMusic($filearray, $overwrite, $utf8);
	if ($err)
		$err_msg .= "<p>$err</p>";
	else $stage = 3;
}

if ($stage == 3) {
	session_start();
	do {
		$rand_num = mt_rand(1,0x7fffffff);
	} while (isset($_SESSION["n_$rand_num"]) || isset($_SESSION["f_$rand_num"]));
	$_SESSION['f_'.$rand_num] = $filearray;
	$_SESSION['n_'.$rand_num] = $_FILES['f']['name'];
	// $stage = 4;
}
?>
<?php
/***  main procedure  ***/

function organize ($s) { return str_replace(' ', '', strtolower($s)); }

function storeMusic(array &$data, $overwrite, $utf8) {
	$lyric = array();
	$d = array_map("organize", $data);
	$lines_added = 0;
	$x = -1;						# x:staff y:time z:bar
	
	foreach ($d as $line_index => $line) {
		$line = explode("|", $line);
		while ($line[0] == "")
			array_shift($line);		# trim
		
		switch ($word = array_shift($line)) {
			
			case "addstaff":		#initialize
				# n & N for lyric proc, the rest for staff margin adjustment
				$L = array('n'=>0, 'N'=>0, 'b'=>0, 'B'=>0, 'M'=>5, 'm'=>5);
				if ($x >= 0) {
					if (! empty($grace)) $pending[] = join("", $grace);
					$lyric[] = join("", $pending);
					$lines_added += addLyric($data, join(" ", $lyric), $L, $lines_added, $line_index, $overwrite);
				}
				$x++;
				$y = $z = 0;
				$clef = -6;
				$slur = false;
				$acc = $key = array_fill(0, 7, 0);
				$lyric = array();
				$pending = array();
				$grace = array();
				$tie = array();
				break;
				
			case "staffproperties":
				if ($L['b'] == 0) $L['b'] = $line_index;
				if ($L['B'] == 0) $L['B'] = $line_index;
				foreach ($line as $word) {
					$t = explode(":", $word);
					if ($t[0] == "boundarybottom") {
						$L['b'] = $line_index;
						break;
					} elseif ($t[0] == "boundarytop") {
						$L['B'] = $line_index;
						break;
					}
				}
				break;
				
			case "clef":
				foreach ($line as $word) {
					$t = explode(":", $word);
					if ($t[0] == "type") {
						switch ($t[1]) {	# position of C4 in the staff
							case "treble": $clef = -6; break;
							case "percussion":
							case "bass": $clef = +6; break;
							case "alto": $clef = 0; break;
							case "tenor": $clef = +2; break;
							default:
								return "Clef type not supported: $t[1]";
						}
					}
				}
				break;
				
			case "key" :
				foreach ($line as $word) {
					$t = explode(":", $word);
					if ($t[0] == "signature") {
						$key = array_fill(0, 7, 0);
						if (trim($t[1]) == "c") break;
						
						$r = "/[a-g][#b]/"; #'a'=97==6(%7)
						$t = explode(",", $t[1]);
						foreach ($t as $k) {
							if(!preg_match($r, $k))
								return "Check key signature: $k";
							$key[(ord($k)-1) % 7] = ($k[1] == 'b'? -1: 1);	#cdefgab
						}
						break;
					}
				}
				$acc = $key;
				break;
				
			case "bar":
				if (! empty($lyric))	# 본격적으로 시작 안했으면 줄바꿈 넣지 말기
					$pending[] = "\\r\\n";
				$acc = $key;
				$z++; break;
				
			case "rest":
			case "note":
			case "chord":
			case "restchord":
				$err = "Check notes on staff ".($x+1)." bar ".($z+1);
				$dur = false;		# duration
				$pitch = array();	# pitch(es)
				$flag = ($word == "rest"? 8: 0);	# other infos
				$visible = 0;
				
				#parse
				foreach ($line as $word) {
					$t = explode(":", $word);
					switch ($t[0]) {
					case "dur":
						if ($dur !== false) return $err;
						$dur = getDuration($t[1]);
						if ($dur === false) return $err;
						if (strpos($t[1], "grace") !== false) $flag |= 1;
						if (strpos($t[1], "slur") !== false) $flag |= 2;
						break;
					case "pos2":
					case "pos":
						$t = explode(",", $t[1]);
						foreach($t as $k) {
							$r = '/^([x#nbv]?)([+-]?\d+)(\^?)/';
							if (!preg_match($r, $k, $match)) return $err;
							$j = getPitch($match, $clef, $acc, $tie);
							
							if ($j[0]) $flag |= 4;	# if tie_in, dismiss!
							else $pitch[] = $j;
							
							# staff height adjust
							if ($L['M'] < $j['pos']) $L['M'] = $j['pos'];
							if ($L['m'] < -$j['pos']) $L['m'] = -$j['pos'];
						}
						break;
					case "opts":
						$t = explode(",", $t[1]);
						$visible = (in_array("lyric=never", $t)? -1:
								(in_array("lyric=always", $t)? 1: $visible));
						break;
					}
				}
				if ($dur === false) return $err;
				#parse end
				
				$content = array ();
				foreach ($pitch as $j) {
					$content[] = $j['chr'];
					if ($j['acc'] !== "") $acc[$j['dia']] = $j['acc'];
				}
				
				# Skip lyric when grace(1), tie in(4), slur in, or rest(8)!
				if ($visible == 0)
					$visible = (!$slur && !($flag & 13));
				
				# flush out pent-up lyrics at the last savepoint
				if ($visible == 1) {
					$t = join("", $pending);
					$lyric[] = ($t? $t: (empty($lyric)? "": "_"));
					$pending = array();
				}
				
				# pile up lyrics, starting from this one
				$content = lyrProc( $content, $utf8 );
				if ($flag & 1 && $visible <= 0) {
					$grace[] = $content;
				} else {
					$pending[] = join("", $grace) . $content;
					$grace = array();
				}
				
				if (! ($flag & 1)) $slur = ($flag & 2);
				$y += $dur;
				break;
				
			case "lyrics":
				$L['N'] = $line_index+1;	// line index to insert new line
				$L['n'] = 0;
				break;
			case "lyric1":
				$L['N'] = $overwrite ? $line_index : $line_index+1;
				$L['n'] = $overwrite ? 1 : 2;	// '#' in "lyric#" to be added/modified
				break;
			case "lyric2":
			case "lyric3":
			case "lyric4":
			case "lyric5":
			case "lyric6":
			case "lyric7":
			case "lyric8":
				$word = (int) $word[5];
				if($overwrite && $L['n'] == $word-1) {//overwrite
					$L['N'] = $line_index; $L['n'] = $word;
				} elseif (!$overwrite && $L['n'] == $word && $word < 8) {//postpone
					$L['N'] = $line_index+1; $L['n']++;
				}
				break; //Assume sorted
		}
	}
	
	if ($x >= 0) {
		if (! empty($grace)) $pending[] = join("", $grace);
		$lyric[] = join("", $pending);
		addLyric($data, join(" ", $lyric), $L, $lines_added, $line_index, $overwrite);
	}
	return 0;
}

/*** sub-procedures ***/
# getDuration: string => integer (duration in 768th)
function getDuration($t){
	$r = "/whole|half|(?:4|8|16|64)th|32nd/";
	if(!preg_match($r, $t, $match)) return false;
	
	$r = array("64th" => 0,"32nd" => 1,"16th" => 2,
			"8th" => 3,"4th" => 4,"half" => 5,"whole" => 6);
	$p = $r[$match[0]];
	
	$r = strpos($t, "triple");
	$p = ($r === false ? 3 : 2) << $p;
	
	$t = explode(",", $t);
	if(in_array("dbldotted", $t)) $p *= 7;
	elseif (in_array("dotted", $t)) $p *= 6;
	else $p <<= 2;	 # d *3/2 dd *7/4
	return $p;
}

# getPitch: 파싱된 문자열 및 제반 정보 => 음높이 정보
function getPitch ($match, $clef, $acc, &$tie) {
	if ($match[1])	# accidental
		$a = strpos("vbn#x", $match[1]) - 2;
		else $a = "";
		
		# position in staff (3rd line = 0)
		$p = (int) $match[2];
		
		# diatonic distance from C (0~6)
		$d = ($p - $clef) % 7;
		if ($d < 0) $d += 7;
		
		# tie[pos] = [acc,]
		if ($a !== "") $accfinal = $a;	# explicit accidental
		elseif (isset($tie[$p]) && ! empty($tie[$p]))		# connected to precedent tie
			$accfinal = $tie[$p][array_keys($tie[$p])[0]];	# (inherit)
		else $accfinal = $acc[$d];		# use current setting
		
		# calculate the pitch
		$r = array(0, 2, 4, 5, 7, 9, 11);	# distances of CDEFGAB from C
		$c = ( $r[$d] + $accfinal ) % 12;
		
		# dealing with tie
		$tie_out = $match[3];
		$tie_in = false;
		if (isset($tie[$p])) {
			$tie_idx = array_search($accfinal, $tie[$p]);
			if ($tie_idx !== false) {
				$tie_in = true;
				if (! $tie_out) unset ($tie[$p][$tie_idx]);
			}
		}
		if (! $tie_in && $tie_out) $tie[$p][] = $accfinal;
		
		return array($tie_in, 'acc' => $a, 'pos' => $p, 'dia' => $d, 'chr' => $c);
}
	
// lyrProc: array of integer (midi pitch) => string (율명)
function lyrProc(array $q, $utf8) {
	//rsort ( $q, SORT_NUMERIC );
	$q = array_reverse($q);
	if ($utf8)
		$q = array_map ( function ($p) {
			$p %= 12;
			if ($p < 0) $p += 12;
			$t = array (
					"\xEB\x82\xA8",
					"\xEB\xAC\xB4",
					"\xEC\x9D\x91",
					"\xED\x99\xA9",
					"\xEB\x8C\x80",
					"\xED\x83\x9C",
					"\xED\x98\x91",
					"\xEA\xB3\xA0",
					"\xEC\xA4\x91",
					"\xEC\x9C\xA0",
					"\xEC\x9E\x84",
					"\xEC\x9D\xB4" 
			);
			return $t [$p];
			// "남", "무", "응", "황", "대", "태", "협", "고", "중", "유", "임", "이"//utf8
		}, $q );
	else
		$q = array_map ( function ($p) {
			$p %= 12;
			if ($p < 0) $p += 12;
			$t = array (
					"\xb3\xb2",
					"\xb9\xab",
					"\xc0\xc0",
					"\xc8\xb2",
					"\xb4\xeb",
					"\xc5\xc2",
					"\xc7\xf9",
					"\xb0\xed",
					"\xc1\xdf",
					"\xc0\xaf",
					"\xc0\xd3",
					"\xc0\xcc" 
			);
			return $t [$p];
			// "남", "무", "응", "황", "대", "태", "협", "고", "중", "유", "임", "이"//euc-kr
		}, $q );
	$l = count ( $q );
	if ($l > 1)
		return "(" . join ( "", $q ) . ")";
	elseif ($l == 1)
		return $q [0];
	else
		return ""; // ???
}

# addLyric: 파싱된 파일에 완성된 율명 가사를 적어넣는다.
#  $d: data to modify; array of strings
#  $content: lyric content to insert or overwrite into $d
#  $L: 가사와 관련한 기타 정보.
#    $L['N']: index (of original file) of line to write the content
#    $L['n']: 가사 줄번호
#    $L['M']: 음표가 위치한 줄 가운데 가장 높은 줄 위치
#    $L['m']: 음표가 위치한 줄 가운데 가장 낮은 줄 위치
#    $L['B']: 보표 윗너비를 기록한 문자열의 index
#    $L['b']: 보표 아랫너비를 기록한 문자열의 index
#  $ln_added: 원본에 대해 현재 위치까지 추가/삭제된 줄수
#  $ln_idx: 가사를 적어넣을 줄 위치 (원본 기준)
#  $overwrite: 덮어쓰기 여부
function addLyric(array &$d, $content, $L, $ln_added, $ln_idx, $overwrite) {
	# 가사 content를 적절한 위치에 삽입/덮어쓰기 
	if ($L ['N'] <= 0) { // Lyrics고 뭐고 아무것도 없다
		array_splice ( $d, $ln_idx + $ln_added, 0, array (
				"|Lyrics",
				"|Lyric1|Text:\"$content\"" 
		) );
		$further_added = 2;
		$L ['n'] = 1;
		$L ['N'] = $ln_idx;
	} elseif ($L ['n'] <= 0) { // Lyrics만 있고 후속 x
		array_splice ( $d, $L ['N'] + $ln_added, 0, "|Lyric1|Text:\"$content\"" );
		$further_added = 1;
		$L ['n'] = 1;
	} else { // Lyric# 있다
		array_splice ( $d, $L ['N'] + $ln_added, ($overwrite ? 1 : 0), "|Lyric${L['n']}|Text:\"$content\"" );
		$further_added = ($overwrite ? 0 : 1);
	}
	
	# 여기부터는 각 staff 위아래 너비 조정해 가사가 잘리지 않게 하는 부분
	$M = $L ['M'] + 2;
	$m = $L ['m'] + 2 + 4 * $L ['n'];
	
	if ($L ['b'] <= 0) { // both not found
		if ($L ['B'] <= 0) {
			array_splice ( $d, $ln_idx + $ln_added + $further_added, 0, "|StaffProperties|BoundaryTop:$M|BoundaryBottom:$m" );
			return $further_added + 1;
		} else
			$L ['b'] = $L ['B'];
	} elseif ($L ['B'] <= 0)
		$L ['B'] = $L ['b'];
	
	$r = '/BoundaryTop:(\d+)/';
	$content = $L ['B'] + $ln_added + ($L ['B'] < $L ['N'] ? 0 : $further_added);
	
	if (preg_match ( $r, $d [$content], $overwrite ))
		$d [$content] = preg_replace ( $r, "BoundaryTop:$M", $d [$content] );
	else
		$d [$content] .= "|BoundaryTop:$M";
	
	$r = '/BoundaryBottom:(\d+)/';
	$content = $L ['b'] + $ln_added + ($L ['b'] < $L ['N'] ? 0 : $further_added);
	
	if (preg_match ( $r, $d [$content], $overwrite ))
		$d [$content] = preg_replace ( $r, "BoundaryBottom:$m", $d [$content] );
	else
		$d [$content] .= "|BoundaryBottom:$m";
	
	return $further_added;
}

//////////////////////////////////////
?>

<!doctype html>
<html lang="ko-KR">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="./yulify.css">
</head>
<body>
	<div class='box'>
<?php
if ($stage < 3) {
	echo "<h3>죄송합니다.</h3><p>";
	switch ($stage) {
		case 0:
			echo "파일 전송에 실패하였습니다."; break;
		case 1:
			echo "전송된 파일을 열지 못했습니다."; break;
		case 2:
			echo "파일 해석에 실패했습니다."; break;
	}
	echo "</p>$err_msg"; 
}
elseif ($rand_num) {?>
		<h3>성공했습니다!</h3>
		<p>시간이 지나도 자동으로 파일이 다운로드 되지 않으면 
		<a href="download.php?rn=<?php echo $rand_num;?>">이 링크</a>를 클릭해 직접 내려받아 주세요.</p>
		<iframe class='hide' width="1" height="1" src="download.php?rn=<?php echo $rand_num;?>"></iframe>
<?php
}
?>
		<a class='btn right' href='yulify.html'>&lt; 돌아가기</a>
	</div>
</body>
</html>