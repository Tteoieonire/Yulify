<?php
$bd = isset($_POST['d']);
$bp = $br = $bm = false;
$be = ""; $bf = $rn = 0;

if (isset($_POST['s'])) {
	$fn = $_FILES['f']['name'];//ext check- nwctxt? mid? others? if the last, err!
	$bp = substr($fn, strrpos($fn, "."));
	if ($bp != ".nwctxt") {
		$be = "<p>Unsupported file type: $bp<br>Please upload .nwctxt file.</p>";
		$bp = false;
	} else {
		$fn = $_FILES['f']['tmp_name'];
		$bp = is_uploaded_file($fn);
		if (!$bp) $be = "<p>Sorry; we couldn't receive your file.</p>";
	}
} else $be = "<p>Sorry; we don't recognize your submission.</p>";

if ($bp) {//if file uploaded
	$fa = file($fn, FILE_IGNORE_NEW_LINES);
	$br = ($fa !== false);
}

if ($br) {//if file read
	$bm = true;
	$err = storeMusic($fa, $bd);
	if ($err){
		$bm = false; $bf = 2;
		$be .= "<p>$err</p>";
	}
}

if ($bm) {//if parsed
	session_start();
	do {
		$rn = mt_rand(1,0x7fffffff);
	} while (isset($_SESSION["n_$rn"]) || isset($_SESSION["f_$rn"]));
	$_SESSION['f_'.$rn] = $fa;
	$_SESSION['n_'.$rn] = $_FILES['f']['name'];
}
?><?php
  /*****************************/
 /****     For .nwctxt     ****/
/*****************************/
function sortTempo(array $a, array $b) {
	$r = array();
	$i = $j = 0; $k = count($a); $l = count($b);
	while ($i < $k && $j < $l) {
		if ($a[$i][0] <= $b[$j][0]) {
			$r[] = $a[$i++];
		} else {
			$r[] = $b[$j++]; //$r에 같은 번호가 있으면 어쩌냐
		}
	}
	if ($i < $k){
		return array_merge($r, array_slice($a, $i));
	} elseif ($j < $l){
		return array_merge($r, array_slice($b, $j));
	}
	return $r;
}

function getPitch($t, $b, $o, array &$a){
	$r = '/^[x#nbv]?[+-]?\d+\^?$/';
	if(!preg_match($r, $t)) return false;
	preg_match("/[+-]?\d+/",$t,$m);
	$p = (int) $m[0];
	$q = ($p - $b) % 7;//distance from C
	if ($q < 0) $q += 7;
	if(preg_match("/[x#nbv]/",$t,$m))
		$a[$q] = strpos("vbn#x",$m[0]) - 2;
	$r = array(0, 2, 4, 5, 7, 9, 11);
	return (int) floor(($p - $b) / 7)*12 + $o + $r[$q] + $a[$q] + 60;
}

function getDuration($t){
	$r = "/whole|half|(?:4|8|16|64)th|32nd/";
	if(!preg_match($r, $t, $m)) return false; //
	$p = array("64th" => 0,"32nd" => 1,"16th" => 2,
			"8th" => 3,"4th" => 4,"half" => 5,"whole" => 6)[$m[0]];
	$r = strpos($t, "triple");
	$p = ($r === false ? 3 : 2) << $p; //d *1.5 dd *1.75
	
	$t = explode(",", $t);
	if(in_array("dbldotted", $t)) $p *= 7;
	elseif (in_array("dotted", $t)) $p *= 6;
	else $p <<= 2;
	return $p;
}

function lyrProc(array $q){
	rsort($q, SORT_NUMERIC);
	$q = array_map(function ($p) {
		$p %= 12; if ($p < 0) $p += 12;
		return array("\xb3\xb2", "\xb9\xab", "\xc0\xc0", "\xc8\xb2", "\xb4\xeb", "\xc5\xc2",
				"\xc7\xf9", "\xb0\xed", "\xc1\xdf", "\xc0\xaf", "\xc0\xd3", "\xc0\xcc")[$p];
		//"남", "무", "응", "황", "대", "태", "협", "고", "중", "유", "임", "이"//^^euc-kr
	}, $q);
	$l = count($q);
	if ($l > 1) return "(".join("", $q).")";
	elseif ($l == 1) return $q[0];
	else return "";//???
}

function addLyric(array &$d, $h, $l1, $l2, $e, $k, $b) {//danger here..T^T
	if($l1 <= 0) {
		array_splice($d, $k + $e, 0,
				array("|Lyrics","|Lyric1|Text:\"$h\""));
		return 2;
	} elseif ($l2 <= 0) {//Lyrics만 있고 후속 x
		array_splice($d, $l1 + $e, 0, "|Lyric1|Text:\"$h\"");
		return 1;
	} else {
		array_splice($d, $l1 + $e, ($b? 1: 0), "|Lyric$l2|Text:\"$h\"");
		return $b? 0: 1; //"|Lyric# \n |Text:..."면 어쩌지? 2를 빼야하는 거 아냐? 
	}
}////???

function ext_empty($a, $b) {
	if (! is_array($a)) return false;
	if (empty($a)) return true;
	if ($b) {//all in /[#, [[],[[],],]]/
		foreach ($a as $i)
			if (! ext_empty($i[1], false)) return false;
		return true;
	} else {//all in /[[], [[], ], ]/
		foreach ($a as $i)
			if (! ext_empty($i, false)) return false;
		return true;
	}
}

function storeMusic(array &$data, $bd) {
	$lyr = array();
	$d = array_map("strtolower", $data); $e = 0; $x = -1;
	foreach ($d as $key => $i) { // x:staff y:time z:bar
		$i = explode("|", $i);
		while ($i[0] == "") array_shift($i);
		switch ($j = array_shift($i)) {
			case "addstaff":
				if ($x >= 0) {
					if (! empty($g)) $f[] = join("", $g);
					$lyr[] = join("", $f);
					$e += addLyric($data, join(" ", $lyr), $l1, $l2, $e, $key, $bd);
				}
				$x++; $y = $z = $o = $l1 = $l2 = $h = 0; $mea = 1; $slur = false;
				$a = $c = array_fill(0, 7, 0); $b = -6;
				$f = $g = $lyr = $tie = $ubl = array();
				break;
			case "clef":
				$o = 0;
				foreach ($i as $j) {
					$t = explode(":", $j);
					if($t[0] == "type") {
						switch ($t[1]) {
							case "treble":
								$b = -6; break;
							case "bass":
							case "percussion":
								$b = +6; break;
							case "alto":
								$b = 0; break;
							case "tenor":
								$b = +2; break;
							default:
								return "Clef type not supported: $t[1]";
						}
					} elseif ($t[0] == "octaveshift") {
						if($t[1] == "octave up") $o = +12;
						elseif($t[1] == "octave down") $o = -12;
						else "Clef type not supported: $t[1]";
					}
				} break;
			case "key" :
				foreach ($i as $j) {
					$t = explode(":", $j);
					if ($t[0] == "signature") {
						$c = array_fill(0, 7, 0);
						if (trim($t[1]) == "c") break;
						$t = explode(",", $t[1]); $r = "/[a-g][#b]/"; #'a'=97==6(%7)
						foreach ($t as $k) {
							if(!preg_match($r, $k)) "Check key signature: $k";
							$c[(ord($k[0])-1) % 7] = (strpos("b#",$k[1]) << 1) - 1;//cdefgab
						} break;
					}
				} $a = $c; break; //Get relieved: it says it's fine;
			case "rest":
				if ($m[0] > 0) $y += $m[0]; $h++;
				break;
			case "note":
			case "chord":
			case "restchord":
				$err = "Check notes on staff ".($x+1)." bar ".($z+1);
				$m = array(false, false); $n = $q = array(array(), array()); $l = 0;
				foreach ($i as $j) { #$q[0,1][pitch] = tie start? true : false
					$t = explode(":", $j);
					$p = 0; #index [0/1]
					switch ($t[0]) {
						
						case "dur2": $p = 1;
						case "dur":
							if ($m[$p] !== false) return $err;
							$t = explode("!", $t[1])[0];
							$m[$p] = getDuration($t);
							
							if ($m[$p] === false) return $err;
							if (strpos($t, "grace") !== false) $l |= 1;
							if (strpos($t, "slur") !== false) $l |= 2;
							break;
							
						case "pos2": $p = 1;
						case "pos":
							$t = explode(",", $t[1]);
							foreach($t as $k) {
								$k = explode("!", $k)[0];
								$j = getPitch($k, $b, $o, $a);
								if ($j === false) return $err;
								
								$k = (strpos($k, "^") !== false);
								if ($k) $l |= 4;
								
								if (isset($tie[$j]))
									$q[$p][$j] = $k;
								else if ($k) //자 이제 시작이야~ 내 꿈을~
									$tie[$j] = $y;
								else //멀쩡한 놈이니 덱에 넣어도 좋다
									array_unshift($n[$p], $j);
							}
							break;
					}
				}
				if ($m[0] === false) return $err;
				
				//lyric dealing
				if (!$slur && empty($g) && empty($q[0]) && empty($q[1])) { // 앞에서 이어지는 게 없다  == 과거 청산
					$lyr[] = join("", $f);
					$f = array();
				}
				if (! ($l & 1)) $slur = ($l & 2);
				
				foreach ($ubl as $note => $end) // disposing unbalanced chord
					if ($end <= $y)
						unset($ubl[$note]);

				$t = array_merge($n[0], $n[1], array_keys($tie), array_keys($ubl));
				
				if ($m[1] !== false && $m[0] != $m[1]) { //$ubl 추가~!
					$p = ($m[0] > $m[1]) ? 0: 1;
					foreach ($n[$p] as $j)
						$ubl[$j] = $y + $m[$p];
					foreach ($q[$p] as $j => $k)
						if (!$k) $ubl[$j] = $y + $m[$p];
				}
				
				$t = lyrProc( array_unique($t, SORT_REGULAR) );
				
				if ($l & 1) // grace
					$g[] = $t;
				else {
					$f[] = join("", $g) . $t;
					$g = array();
				}

				$t = ($m[1] !== false)? 2: 1; //지금 막 끝난 tie 처리
				for ($p=0; $p<$t; $p++)
					foreach ($q[$p] as $j => $k)
						if (!$k) unset($tie[$j]);
				
				if ($m[0] > 0) $y += $m[0]; $h++;
				break;
			case "bar":
				if ($h) $lyr[] = "\\r\\n";
				$h = 0; $a = $c; $z++; break;
			case "lyrics":
				$l1 = $key+1;
				$l2 = 0;
				break;
			case "lyric1":
				$l1 = $bd ? $key : $key+1;
				$l2 = $bd ? 1 : 2;
				break;
			case "lyric2":
			case "lyric3": 
			case "lyric4":
			case "lyric5": 
			case "lyric6": 
			case "lyric7": 
				$j = $j[5];
				if($bd && $l2 == $j-1) {//overwrite
					$l1 = $key; $l2 = $j;
				} elseif (!$bd && $l2 == $j) {//postpone
					$l1 = $key+1; $l2 += 1; //not .= ;)
				}
				break; //Assume sorted
		}
	}

	if ($x >= 0) {
		if (! empty($g)) $f[] = join("", $g);
		$lyr[] = join("", $f);
		addLyric($data, join(" ", $lyr), $l1, $l2, $e, $key, $bd);
	}
	return 0;
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
$br = ! empty($be); //recycling
if ($br) {//err
	echo "<h3>죄송합니다.</h3><p>";
	switch ($bf) {
		case 0:
			echo "파일 전송에 실패하였습니다."; break;
		case 1:
			echo "전송된 파일을 열지 못했습니다."; break;
		case 2:
			echo "파일 해석에 실패했습니다."; break;
		default:
			echo "예기치 못한 이유로 실패했습니다."; break;
	}
	echo "</p>$be"; 
} elseif ($rn) {?>
		<h3>성공했습니다!</h3>
		<p>시간이 지나도 자동으로 파일이 다운로드 되지 않으면 
		<a href="download.php?rn=<?php echo $rn;?>">이 링크</a>를 클릭해 직접 내려받아 주세요.</p>
		<iframe class='hide' width="1" height="1" frameborder="0" src="download.php?rn=<?php echo $rn;?>"></iframe>
<?php
}
?>
		<a class='btn right' href='yulify.html'>&lt; 돌아가기</a>
	</div>
</body>
</html>