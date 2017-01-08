<?php
$bd = isset($_POST['d']);
$bn = isset($_POST['n']);
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
	$err = storeMusic($fa, $bd, $bn);
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

function getDuration($t){
	$r = "/whole|half|(?:4|8|16|64)th|32nd/";
	if(!preg_match($r, $t, $m)) return false;
	$r = array("64th" => 0,"32nd" => 1,"16th" => 2,
			"8th" => 3,"4th" => 4,"half" => 5,"whole" => 6);
	$p = $r[$m[0]];
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
		$t = array("\xb3\xb2", "\xb9\xab", "\xc0\xc0", "\xc8\xb2", "\xb4\xeb", "\xc5\xc2",
				"\xc7\xf9", "\xb0\xed", "\xc1\xdf", "\xc0\xaf", "\xc0\xd3", "\xc0\xcc");
		return $t[$p];
		//"남", "무", "응", "황", "대", "태", "협", "고", "중", "유", "임", "이"//^^euc-kr
	}, $q);
	$l = count($q);
	if ($l > 1) return "(".join("", $q).")";
	elseif ($l == 1) return $q[0];
	else return "";//???
}

function addLyric(array &$d, $h, $L, $e, $k, $b) {
	if ($L['N'] <= 0) {//$h: 내용,  $b: $bd, $k: $key
		array_splice($d, $k + $e, 0,
				array("|Lyrics","|Lyric1|Text:\"$h\""));
		$f = 2; $L['n'] = 1; $L['N'] = $k;
	} elseif ($L['n'] <= 0) {//Lyrics만 있고 후속 x
		array_splice($d, $L['N'] + $e, 0, "|Lyric1|Text:\"$h\"");
		$f = 1; $L['n'] = 1;
	} else {
		array_splice($d, $L['N'] + $e, ($b? 1: 0), "|Lyric${L['n']}|Text:\"$h\"");
		$f = ($b? 0: 1);
	}
	
	$M = $L['M'] + 2;
	$m = $L['m'] + 2 + 4 * $L['n'];
	
	if ($L['b'] <= 0) {//both not found
		if ($L['B'] <= 0) {
			array_splice($d, $k + $e + $f, 0,
				"|StaffProperties|BoundaryTop:$M|BoundaryBottom:$m");
			return $f + 1;
		} else
			$L['b'] = $L['B'];
	} elseif ($L['B'] <= 0)
		$L['B'] = $L['b'];
		
	$h = $L['B'] + $e + ($L['B'] < $L['N']? 0: $f);
	$r = '/BoundaryTop:(\d+)/';
	if (preg_match($r, $d[$h], $b)) {
		$d[$h] = preg_replace($r, "BoundaryTop:$M", $d[$h]);
	} else
		$d[$h] .= "|BoundaryTop:$M";
	
	$h = $L['b'] + $e + ($L['b'] < $L['N']? 0: $f);
	$r = '/BoundaryBottom:(\d+)/';
	if (preg_match($r, $d[$h], $b)) {
		$d[$h] = preg_replace($r, "BoundaryBottom:$m", $d[$h]);
	} else
		$d[$h] .= "|BoundaryBottom:$m";
	
	return $f;
}
function organize ($s) { return str_replace(' ', '', strtolower($s)); }

function storeMusic(array &$data, $bd, $bn) {//$bd: overwrite? $bn: detailed description?
	$lyr = array();
	$d = array_map("organize", $data);
	$e = 0; $x = -1; // x:staff y:time z:bar
	
	foreach ($d as $key => $i) {
		$i = explode("|", $i);
		while ($i[0] == "") array_shift($i);
		switch ($j = array_shift($i)) {
			case "addstaff":
				if ($x >= 0) {
					if (! empty($g)) $f[] = join("", $g);
					$lyr[] = join("", $f);
					$e += addLyric($data, join(" ", $lyr), $L, $e, $key, $bd);
					#$e: 밀리는 칸 수, $l1: , $l2: Lyric#
				}
				$x++; $y = $z = $o = $h = 0;
				$b = -6; $slur = false;
				$a = $c = array_fill(0, 7, 0);
				$f = $g = $lyr = $tie = array();
				if ($bn) $ubl = array();
				$L = array('n'=>0, 'N'=>0, 'b'=>0, 'B'=>0, 'M'=>5, 'm'=>5);
				break;
			case "staffproperties":
				if ($L['b'] <= 0) $L['b'] = $key;
				if ($L['B'] <= 0) $L['B'] = $key;
				foreach ($i as $j) {
					$t = explode(":", $j);
					if ($t[0] == "boundarybottom") {
						$L['b'] = $key; break;
					} elseif ($t[0] == "boundarytop") {
						$L['B'] = $key; break;
					}
				}
				break;
			case "clef":
				$o = 0;
				foreach ($i as $j) {
					$t = explode(":", $j);
					if ($t[0] == "type") {
						switch ($t[1]) {
							case "treble": $b = -6; break;
							case "percussion":
							case "bass": $b = +6; break;
							case "alto": $b = 0; break;
							case "tenor": $b = +2; break;
							default:
								return "Clef type not supported: $t[1]";
						}
					} elseif ($t[0] == "octaveshift") {
						if($t[1] == "octave up") $o = +12;
						elseif($t[1] == "octave down") $o = -12;
						else return "Clef type not supported: $t[1]";
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
			case "note":
			case "chord":
			case "restchord":
				$err = "Check notes on staff ".($x+1)." bar ".($z+1);
				$m = array(false, false);
				$n = array(array(), array());
				$l = ($j == "rest"? 16: 0);
				$r = 0;
				
				foreach ($i as $j) {
					$t = explode(":", $j);
					$p = 0; #index [0/1]
					switch ($t[0]) {
						
						case "dur2": $p = 1;
						case "dur":
							if ($m[$p] !== false) return $err;
							list($t) = explode("!", $t[1]);
							$m[$p] = getDuration($t);
							
							if ($m[$p] === false) return $err;
							if (strpos($t, "grace") !== false) $l |= 1;
							if (strpos($t, "slur") !== false) $l |= 2;
							break;
							
						case "pos2": $p = 1;
						case "pos":
							$t = explode(",", $t[1]);
							foreach($t as $k) {
								$j = '/^([x#nbv]?)([+-]?\d+)(\^?)/';
								if (!preg_match($j, $k, $j)) return $err;
								
								if ($j[1]) $j[1] = strpos("vbn#x", $j[1]) - 2;
								
								$j[2] = (int) $j[2];
								if ($L['M'] < $j[2]) $L['M'] = $j[2];
								elseif ($L['m'] < -$j[2]) $L['m'] = -$j[2];
								
								$j[0] = ($j[2] - $b) % 7; # distance from C
								if ($j[0] < 0) $j[0] += 7;
								
								if ($j[1] !== "") $k = $j[1];
								elseif (isset($tie[$j[2]])) $k = $tie[$j[2]][1];
								else $k = $a[$j[0]];
								$j[4] = ( isset($tie[$j[2]]) && $tie[$j[2]][1] == $k );
								
								$h = array(0, 2, 4, 5, 7, 9, 11);
								$k += (int) floor(($j[2] - $b) / 7) * 12 + $o + $h[$j[0]] + 60;
								$j[5] = $k;
								
								$n[$p][] = $j;
							}
							break;
						case "opts":
							$t = explode(",", $t[1]);
							foreach($t as $k) {
								$k = explode("=", $k);
								if ($k[0] == "lyric") {
									if ($k[1] == "never") $r = -1;
									elseif ($k[1] == "always") $r = 1;
								} elseif ($k[0] == "stem") {
									if ($k[1] == "down") $l |= 8;
								}
							}
							break;
					}
				}
				$i = ($m[1] === false? 0: 1);
				if ($m[0] === false) return $err;
				if (!$i && !empty($n[1])) return $err;

				# $n[$p] = [dia, acc, pos, tie_out, tie_in, chr]
				
				$t = $h = array ();
				for ($p = 0; $p <= $i; $p++) { # dealing with tie
					foreach ($n[$p] as $k => $j) {
						if ($j[4]) { #tie in
							$l |= 4;
							if (! $j[3]) unset($tie[$j[2]]);
						} elseif ($j[3]) #tie out
							$tie[$j[2]] = array ( $j[5],
									($j[1] === ""? $a[$j[0]]: $j[1]) );
						
						if ($j[4] && (!$bn || $j[3])) # detailed descrip'n unwanted or just useless
							unset($n[$p][$k]);
						else $t[] = $j[5];
						
						if ($j[1] !== "") {
							if (isset ($h[$j[0]])) {
								if ($l & 8) $a[$j[0]] = $j[1];
							} else
								$h[$j[0]] = $a[$j[0]] = $j[1];
						}
					}
				}
				if ($bn) {
					foreach ($tie as $j) $t[] = $j[0];
					
					
					foreach ($ubl as $note => $end) # dealing with unbalanced chord
						if ($end <= $y)
							unset($ubl[$note]);
	
					if ($i && ! ($l & 1) && $m[0] != $m[1]) { # $ubl 추가~!
						$p = ($m[0] > $m[1]) ? 0: 1;
						foreach ($n[$p] as $j)
							$ubl[$j[5]] = $y + $m[$p];
					}
				}
				
				# lyric dealing # No grace(1), No tie in(4), No slur in, No rest(16)!
				if ($r == 0) $r = (!$slur && ! ($l & 21));
				if ($r > 0) {
					$k = join("", $f);
					$lyr[] = ($k? $k: (empty($lyr)? "": "_"));
					$f = array();
				}
				if (! ($l & 1)) $slur = ($l & 2);

				if ($bn) $t = array_merge($t, array_keys($ubl));
				$t = lyrProc( array_unique($t, SORT_REGULAR) );
				
				if ($l & 1 && $r <= 0) {
					$g[] = $t;
				} else {
					$f[] = join("", $g) . $t;
					$g = array();
				}
				
				if ($m[0] > 0) $y += $m[0]; $h = 1;
				break;
			case "bar":
				if ($h && !empty($lyr)) $lyr[] = "\\r\\n";
				$h = 0; $a = $c; $z++; break;
			case "lyrics":
				$L['N'] = $key+1;
				$L['n'] = 0;
				break;
			case "lyric1":
				$L['N'] = $bd ? $key : $key+1;
				$L['n'] = $bd ? 1 : 2;
				break;
			case "lyric2":
			case "lyric3": 
			case "lyric4":
			case "lyric5": 
			case "lyric6": 
			case "lyric7": 
			case "lyric8": 
				$j = (int) $j[5];
				if($bd && $L['n'] == $j-1) {//overwrite
					$L['N'] = $key; $L['n'] = $j;
				} elseif (!$bd && $L['n'] == $j && $j < 8) {//postpone
					$L['N'] = $key+1; $L['n']++;
				}
				break; //Assume sorted
		}
	}

	if ($x >= 0) {
		if (! empty($g)) $f[] = join("", $g);
		$lyr[] = join("", $f);
		addLyric($data, join(" ", $lyr), $L, $e, $key, $bd);
	}
	return 0;
}
//////////////////////////////////////
?>

<!doctype html>
<html lang="ko-KR">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="./kr_box.css">
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
		<iframe class='hide' width="1" height="1" src="download.php?rn=<?php echo $rn;?>"></iframe>
<?php
}
?>
		<a class='btn right' href='kr_trad_music.php'>&lt; 돌아가기</a>
	</div>
</body>
</html>
