/**
 * (data, how_much_modified) pair
 */

function organize(s) { return s.toLowerCase().replace(' ', ''); }

function storeMusic(data, overwrite, utf8) {
	var data_obj = { data: data, lines_added: 0 };
	var lyric = [];
	var lyric_obj = { lyric_no: 0, line_index: 0 };
	var x = -1, y = 0, z = 0;				// x:staff y:time z:bar
	var clef = -6, acc, key;
	var slur = false, tie;
	var pending, grace;
	var last_index;

	data.map(organize).forEach(function (line, line_index) {
		line = line.split("|");
		while (line[0] == "")
			line.shift();		// trim

		var word = line.shift();
		switch (word) {

			case "addstaff":		//initialize
				if (x >= 0) {
					if (grace.length > 0)
						pending.push(grace.join(""));
					lyric.push(pending.join(""));
					addLyric(data_obj, lyric.join(" "), lyric_obj, line_index, overwrite);
				}
				x++;
				y = z = 0;
				clef = -6;
				slur = false;
				acc = [0, 0, 0, 0, 0, 0, 0];
				key = [0, 0, 0, 0, 0, 0, 0];
				lyric = [];
				pending = [];
				grace = [];
				tie = {};
				lyric_obj = { lyric_no: 0, line_index: 0 };
				break;

			case "clef":
				line.forEach(function (word) {
					var t = word.split(':');
					if (t[0] == "type") {
						switch (t[1]) {	// position of C4 in the staff
							case "treble": clef = -6; break;
							case "percussion":
							case "bass": clef = +6; break;
							case "alto": clef = 0; break;
							case "tenor": clef = +2; break;
							default:
								throw "Clef type not supported: " + t[1];
						}
					}
				});
				break;

			case "key":
				line.some(function (word) {
					var t = word.split(':');
					if (t[0] == "signature") {
						key = [0, 0, 0, 0, 0, 0, 0];
						if (t[1] == "c") return true;

						var re = /[a-g][#b]/; //'a'=97==6(%7)
						t = t[1].split(",");
						t.forEach(function (k) {
							if (k.match(re) == null)
								throw "Check key signature: " + k;
							key[(k.charCodeAt(0) - 1) % 7] = (k[1] == 'b' ? -1 : 1);	//cdefgab
						});
						return true;
					}
					return false;
				});
				acc = key.slice();
				break;

			case "bar":
				if (lyric.length > 0)	// 본격적으로 시작 안했으면 줄바꿈 넣지 말기
					pending.push("\\r\\n");
				acc = key.slice();
				z++; break;

			case "rest":
			case "note":
			case "chord":
			case "restchord":
				var err = "Check notes on staff " + (x + 1) + " bar " + (z + 1);
				var dur = 0;		// duration
				var pitch = [];		// pitch(es)
				var flag = (word == "rest" ? 8 : 0);	// other infos
				var visible = 0;

				//parse
				line.forEach(function (word) {
					var t = word.split(':');
					switch (t[0]) {
						case "dur":
							if (dur) throw err;
							dur = getDuration(t[1]);
							if (t[1].indexOf("grace") != -1) flag |= 1;
							if (t[1].indexOf("slur") != -1) flag |= 2;
							break;
						case "pos2":
						case "pos":
							t = t[1].split(",");
							t.forEach(function (k) {
								var re = /^([x#nbv]?)([+-]?\d+)(\^?)/;
								var match = k.match(re);
								if (match == null) throw err;
								var j = getPitch(match, clef, acc, tie);

								if (j['tie_in']) flag |= 4;	// if tie_in, dismiss!
								else pitch.push(j);
							});
							break;
						case "opts":
							t = t[1].split(",");
							visible = (t.indexOf("lyric=never") != -1 ? -1 :
								(t.indexOf("lyric=always") != -1 ? 1 : visible));
							break;
					}
				});
				if (dur == 0) throw err;
				//parse end

				var content = [];
				pitch.forEach(function (j) {
					content.push(j['chr']);
					if (j['acc'] !== "") acc[j['dia']] = j['acc'];
				});

				// Skip lyric when grace(1), tie in(4), slur in, or rest(8)!
				if (visible == 0)
					visible = (!slur && !(flag & 13));

				// flush out pent-up lyrics at the last savepoint
				if (visible == 1) {
					t = pending.join("");
					lyric.push(t ? t : (lyric.length == 0 ? "" : "_"));
					pending = [];
				}

				// pile up lyrics, starting from this one
				content = lyrProc(content, utf8);
				if (flag & 1 && visible <= 0) {
					grace.push(content);
				} else {
					pending.push(grace.join("") + content);
					grace = [];
				}

				if (!(flag & 1)) slur = (flag & 2);
				y += dur;
				break;

			case "lyrics":
				lyric_obj.line_index = line_index + 1;	// line index to insert new line
				lyric_obj.lyric_no = 0;
				break;
			case "lyric1":
				lyric_obj.line_index = overwrite ? line_index : line_index + 1;
				lyric_obj.lyric_no = overwrite ? 1 : 2;	// '#' in "lyric#" to be added/modified
				console.log("Position Lyric1) line:", line, line[0].split(""));
				break;
			case "lyric2":
			case "lyric3":
			case "lyric4":
			case "lyric5":
			case "lyric6":
			case "lyric7":
			case "lyric8":
				word = Number(word[5]);
				if (overwrite && lyric_obj.lyric_no == word - 1) {//overwrite
					lyric_obj.line_index = line_index;
					lyric_obj.lyric_no = word;
				} else if (!overwrite && lyric_obj.lyric_no == word && word < 8) {//postpone
					lyric_obj.line_index = line_index + 1;
					lyric_obj.lyric_no++;
				}
				console.log("Position Lyric", word, ") line:", line, line[0].split(""));
				break; //Assume sorted
			case "!noteworthycomposer-end":
				last_index = line_index; 
				break;
		}
	});

	if (x >= 0) {
		if (grace.length > 0) pending.push(grace.join(""));
		lyric.push(pending.join(""));
		addLyric(data_obj, lyric.join(" "), lyric_obj, last_index, overwrite);
	}
	return data_obj.data;
}

/*** sub-procedures ***/
// getDuration: string => integer (duration in 768th)
function getDuration(t) {
	var re = /whole|half|(?:4|8|16|64)th|32nd/;
	var match = t.match(re);
	if (match == null) return 0;

	var table = ["64th", "32nd", "16th", "8th", "4th", "half", "whole"];
	var dur = table.indexOf(match[0]);

	dur = (t.indexOf("triple") == -1 ? 3 : 2) << dur;

	t = t.split(",");
	if (t.indexOf("dbldotted") != -1) dur *= 7;
	else if (t.indexOf("dotted") != -1) dur *= 6;
	else dur *= 2;	 // d *3/2 dd *7/4
	return dur;
}

// getPitch: 파싱된 문자열 및 제반 정보 => 음높이 정보
function getPitch(match, clef, accidental, tie) {
	var acc = "";
	if (match[1])	// accidental
		acc = "vbn#x".indexOf(match[1]) - 2;

	// position in staff (3rd line = 0)
	var pos = Number(match[2]);

	// diatonic distance from C (0~6)
	var dia = (pos - clef) % 7;
	if (dia < 0) dia += 7;

	// tie[pos] = [acc,]; no holes
	var accfinal;
	if (acc !== "") accfinal = acc;	// explicit accidental
	else if (tie[pos] != undefined && tie[pos].length > 0)	// connected to precedent tie
		accfinal = tie[pos][0];	// (inherit)
	else accfinal = accidental[dia];		// use current setting

	// calculate the pitch
	var r = [0, 2, 4, 5, 7, 9, 11];	// distances of CDEFGAB from C
	var chr = (r[dia] + accfinal) % 12;

	// dealing with tie
	var tie_out = match[3];
	var tie_in = false;
	if (tie[pos] != undefined) {
		var tie_idx = tie[pos].indexOf(accfinal);
		if (tie_idx != -1) {
			tie_in = true;
			if (!tie_out) tie[pos].splice(tie_idx, 1);
		}
	}
	if (!tie_in && tie_out) {
		if (tie[pos] == undefined) tie[pos] = [];
		tie[pos].push(accfinal)
	}

	return { tie_in: tie_in, acc: acc, pos: pos, dia: dia, chr: chr };
}

// lyrProc: array of integer (midi pitch) => string (율명)
function lyrProc(pitches, utf8) {
	//*
	var table = [
		["\xb3\xb2", "\xb9\xab", "\xc0\xc0", "\xc8\xb2", "\xb4\xeb", "\xc5\xc2",
			"\xc7\xf9", "\xb0\xed", "\xc1\xdf", "\xc0\xaf", "\xc0\xd3", "\xc0\xcc"],
		["\xEB\x82\xA8", "\xEB\xAC\xB4", "\xEC\x9D\x91", "\xED\x99\xA9", "\xEB\x8C\x80", "\xED\x83\x9C",
			"\xED\x98\x91", "\xEA\xB3\xA0", "\xEC\xA4\x91", "\xEC\x9C\xA0", "\xEC\x9E\x84", "\xEC\x9D\xB4"]
		];		// "남", "무", "응", "황", "대", "태", "협", "고", "중", "유", "임", "이"
	table = table[utf8? 1: 0];
	/*/
	var table = ["남", "무", "응", "황", "대", "태", "협", "고", "중", "유", "임", "이"];
	//*/

	pitches = pitches.reverse();
	pitches = pitches.map(function (pitch) {
		pitch = pitch % 12;
		if (pitch < 0) pitch += 12;
		return table[pitch];
	});

	var len = pitches.length;
	if (len > 1)
		return "(" + pitches.join("") + ")";
	else if (len == 1)
		return pitches[0];
	else
		return ""; // 쉼표
}

// addLyric: 파싱된 파일에 완성된 율명 가사를 적어넣는다.
//  data_obj: data to modify;
//    data: array of strings
//    lines_added: 원본에 대해 현재 위치까지 추가/삭제된 줄수
//  content: lyric content to insert or overwrite into data_obj.data
//  lyric_obj: 가사와 관련한 기타 정보.
//    line_index: index (of original file) of line to write the content
//    lyric_no: 가사 줄번호
//  ln_idx: 현재 줄 위치 (원본 기준)
//  overwrite: 덮어쓰기 여부
function addLyric(data_obj, content, lyric_obj, ln_idx, overwrite) {
	// 가사 content를 적절한 위치에 삽입/덮어쓰기

	function write(content, lines_to_delete) {
		var insert_here = lyric_obj.line_index + data_obj.lines_added;
		data_obj.data.splice(insert_here, lines_to_delete, content);
		data_obj.lines_added += 1 - lines_to_delete;
	}

	if (lyric_obj.line_index <= 0) {
		lyric_obj.line_index = ln_idx;
		write("|Lyrics", 0);
	}
	if (lyric_obj.lyric_no <= 0) {
		lyric_obj.lyric_no = 1;
		overwrite = false;
	}
	write("|Lyric" + lyric_obj.lyric_no + "|Text:\"" + content + "\"", (overwrite ? 1 : 0));
}

