﻿<!DOCTYPE html>
<html lang="ko-KR">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Web App for Korean Traditional Music</title>
	<link rel="stylesheet" type="text/css" href="./kr_box.css">
<script>
function checkExt(el) {
	var ext = el.value;
	ext = ext.substring(ext.lastIndexOf('.'));
	if (ext == ".nwctxt") {
		document.getElementById('f_wrap').className = "";
		document.getElementById('f_notice').className = "hide";
	} else {
		document.getElementById('f_wrap').className = "hide";
		document.getElementById('f_notice').className = "";
		el.value = "";
	}
}
</script>
</head>
<body>
	<div class='box'>
		<h3>당신의 오선보 아래에, 율명을 적어 드립니다!</h3>
		<p>※ 현재 이 프로그램은 .nwctxt 형식을 지원합니다. .nwc 파일의 경우 다시 열어서 '다른 이름으로 저장...'을 선택한 뒤 파일 형식을 NWC Text File로 지정해 주세요.</p>
		<p class='hide' style='color:orange;' id='f_notice'>죄송합니다. nwctxt 파일을 선택해 주십시오.</p>
		<form name='p' enctype="multipart/form-data" action="kr_yulify.php" method="post">
			<p><input type="file" name="f" accept=".nwctxt" onchange="checkExt(this);"></p>
			<p class='hide' id='f_wrap'>
				<input type="checkbox" name="d" id="d" value="1">
				<label for="d">음률을 마지막 가사에 덮어써도 되나요?</label>
				<br>
				<input type="checkbox" name="utf8" id="utf8" value="1">
				<label for="utf8">UTF-8 인코딩 쓰세요? (NWC 1.7 이상이면 그럴 거예요)</label>
				<br>
				<input type="hidden" name="s" value="1">
				<a class='btn right' href='javascript: document.p.submit();'>다음 &gt;</a>
			</p>
		</form>
		<hr>
		<!-- <h3>정간보와 오선보를 자유롭게 오가며 작곡&middot;편곡할 수 있습니다!</h3>
		<p><a class='btn' href='kr_write_music.php'>이동하기 &gt;</a></p> -->
		<span class='right'>버그/오류 제보: ieay4a@kaist.ac.kr</span>
	</div>
</body>
</html>