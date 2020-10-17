<?php
	set_time_limit(40);
	header("Access-Control-Allow-Origin: *");
	//error_reporting(0);
	$j = [];
	$_GET['debug'] = 0;

	if(isset($_GET['data'])){ // 传入参数
		$_GET['data'] = json_decode(base64_decode(str_replace(" ","+", $_GET['data'])), 1);
	}else{
		// 测试
		$_GET['debug'] = 1;
		foreach (json_decode(file_get_contents('web.json'), true) as $key => $value) {
			if(intval($value['id']) == 45){
				$_GET['img'] = 0;
				$_GET['data'] = [
					'elephtotolist' => _g($value, 'elephtotolist'),
					'eleimgsrc' => _g($value, 'eleimgsrc'),
					'imgrule' => _g($value, 'imgrule'),
					'once' => isset($value['once']),
					'charset' => $value['charset'],
					'res' => isset($_GET['data']['res']) ? $_GET['data']['res'] : []				
				];
				//$value['test_url'] = 'https://mm.enterdesk.com/bizhi/60040.html';
				$_GET['url'] = $value['test_url'];
				break;
			}
		}
	}
	if(!isset($_GET['img'])) $_GET['img'] = true;
	//var_dump($_GET); exit();
	if(count($_GET['data']) == 0) exit();

	$_POST = [
		'id' => $_GET['id'],
		'cover' => [], // 返回的已经解析的图片
		'max' => 0, // 最大页数
		'urls' => [], // 返回的页数
	];

	// 配置参数
	$_GET['try'] = 0;
	$_GET['cnt'] = 0; // 重试次数
	$_GET['cache'] = 0;
	if(strpos($_GET['url'], '//') === 0){
		$_GET['url'] = 'http:'.$_GET['url'];
	}
	//var_dump($_GET); eixt();
	loadPage();

	// 页面规则
	function getPage($url, $rule, $page){
		$arr = explode('/', $url);
		switch ($rule['t']) {
			case 'join':
				$url = $url . str_replace('{0}', $page, $rule['s']);
				break;
			case 'end':
				array_pop($arr);
				$arr[] = str_replace('{0}', $page, $rule['s']);
				$url = implode('/', $arr);
				break;

			case 'replace':
				$url = str_replace($rule['s'][0], str_replace('{0}', $page, $rule['s'][1]), $url);
				break;
			
			default:
				break;
		}
		return $url;		
	}

	function loadPage(){
		$cnt = 0;
		$j = $_GET['data'];
		$j['host'] = '//' . explode('/', str_replace(['http://', 'https://', '//'], '', $_GET['url']))[0];
		$url = $_GET['url'];
		$rule = $_GET['data']['imgrule'];
		if($rule['m'] == -1){
			$_POST['max'] = -1; // 不知道最大页数
		}

		if($_GET['debug']) echo $url."\r\n";
		// if($_GET['cache'] && file_exists('./cache/'.md5($url).'.json')){
		// 	echo file_get_contents('./cache/'.md5($url).'.json');
		// 	exit();
		// }

		// $_GET['lastUrl'] = $url;
		if(isset($_GET['lastUrl']) && $_GET['lastUrl'] == $url){
			echoJson();
		}
		// 

		$file = './cache/'.md5($url).'.html';
		$exists = file_exists($file);
		if($_GET['cache'] && $exists){
			$content = getFile($file);
		}else{
			$ch = curl_init();
			$options =  array(
				CURLOPT_HEADER => false,
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				 CURLOPT_ENCODING => 'gzip',
				CURLOPT_TIMEOUT => 30,
				CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
				CURLOPT_HTTPHEADER => array('X-FORWARDED-FOR:'.Rand_IP(), 'CLIENT-IP:'.Rand_IP()),
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36 Edg/81.0.416.58'
			);
			if($j['curl']['proxy']){
				$options[CURLOPT_PROXY] = "127.0.0.1";
				$options[CURLOPT_PROXYPORT] = 1080;
			}
			// $options[CURLOPT_HTTPHEADER] = [
			// 	// 'origin' => $_GET['referer'],
			// 	'referer' => $j['albumurl']
			// ];
			curl_setopt_array($ch, $options);
			$content = curl_exec($ch);
		}
		if($j['charset'] != 'utf-8'){
			$content = mb_convert_encoding($content, "utf-8", $j['charset']);
		}
		//var_dump($content);
		if($_GET['debug']) var_dump(strlen($content));
		if(strlen($content) === 0){
			logError();
		}
		//var_dump($content); exit();

		if(is_array($j['elephtotolist'])){
			switch ($j['elephtotolist'][0]) {
				case 'getText_Array':
					$_POST['cover'] = getStringByStartAndEnd_array($content, $j['elephtotolist'][1], $j['elephtotolist'][2]);
					break;
			}
			echoJson();
		}

		//var_dump($content);
		include_once 'simple_html_dom.php';
		$html = str_get_html($content);
		if($html){
			$doms = $html->find(str_replace('->', ' ', $j['elephtotolist']));
			if(count($doms) == 0){
				if($_GET['debug']) echo '没有找到图片! -- '.$_GET['try']."\r\n";
				return logError();
			}
			if($_GET['debug']) var_dump('dom : '.count($doms));

			$a_cover = explode('->', $j['eleimgsrc']);	
			foreach ($doms as $k => $dom) {
				$lastTag = strtolower($dom->tag);

				$cover = checkUrl($j['host'], parseDom($lastTag, $dom, $a_cover));
				if(strpos($cover, 'background-image:url(') !== false){
					$cover = str_replace(['background-image:url(', ');'], '', $cover);
				}
				if($_GET['debug']) var_dump($cover);
				if($_GET['img']){ // 非目录,直接输出图片
					exit(header('Location: '.$cover));
				}				

				// 获取最大页数
				if($_POST['max'] !== -1 && isset($rule['m'])){

					$skip = false;
					$b = is_array($rule['m']);
					if($b){
						$s = $rule['m'][0];
						switch($s){
							case 'getText':
								$max = getStringByStartAndEnd($content, $rule['m'][1], $rule['m'][2]);
								//var_dump('getText'.'->'.$max);
								$skip = true;
								break;
						}
					}else{
						$s = $rule['m'];
					}

					if(!$skip){
						$max = parseDom('', $html, explode('->', $s));
						if($_GET['debug']) var_dump($max);
						if($b){
							if(is_array($rule['m'][1])){
								switch ($rule['m'][1][0]) {
									case 'getNumber':
										preg_match_all('/\d+/',$max,$arr);
										if(count($arr) > 0){
											$i = count($arr[0]) - 1;
											if($rule['m'][1] == -1){
												$rule['m'][1] = $i;
											}else
											if($rule['m'][1] > $i){
												$rule['m'][1] = $i;
											}
											$max = $arr[0][$rule['m'][1]];
											$skip = true;
										}
										break;
									case 'getText':
										$max = getStringByStartAndEnd($max, $rule['m'][1][1],  $rule['m'][1][2]);
										//var_dump('getText'.'->'.$max);
										$skip = true;
										break;
								}
							}

							if(!$skip) $max = str_replace($rule['m'][1], '', $max);
						}
					}
					$max = intval($max);
					if($max > $_POST['max']){
						$_POST['max'] = $max;
						if($_GET['debug']) echo 'maxPage:'.$max."\r\n";
					}

					if(isset($rule['p'])){ // 规则的图片顺序

						if(isset($rule['p']['num'])){
							$_POST['max'] *= $rule['p']['num'];
							//echo '页数x于:'.$_POST['max']."\r\n";
						}
						
						$start = isset($rule['p']['s']) ? $rule['p']['s'] : 1;
						if($start == 0) $_POST['max'] -= 1; // 调整

						if($rule['p']['t'] == 'replace'){
							if(intval($rule['p']['f'][0]) !== 0){
								$replace = substr($cover, $rule['p']['f'][0]);
							}else{
								$replace = $rule['p']['f'][0];
							}
						}
						// var_dump($replace);
						// exit();

						for($i=$start;$i<=$_POST['max'];$i++){
							$i_id = $i;
							if(isset($rule['p']['l'])){
								while(strlen($i_id) < $rule['p']['l']){
									$i_id = '0'.$i_id;
								}
							}
							switch($rule['p']['t']){
								case 'add': // 长整数递增
									$arr = explode('/', $cover);
									$index = $rule['p']['f'][0];
									if($index == -1){
										$index = count($arr) - 1;
									}else{
										$index = count($arr) - abs($index);
									}
									if(isset($rule['p']['f'][2])){
										$arr[$index] = str_replace($rule['p']['f'][2], '', $arr[$index]);
									}
									$arr[$index]+=$rule['p']['f'][1];
									if(isset($rule['p']['f'][3])){
										$arr[$index] = str_replace('{0}', str_replace(",","",number_format($arr[$index])), $rule['p']['f'][3]); // 转换科学计数法
									}
									$cover = implode('/', $arr);
									$_POST['cover'][] = $cover;
									$cnt++;
									break;
								case 'end':
									$arr = explode('/', $cover);
									$arr[count($arr)-1] = str_replace('{0}', $i_id, $rule	['p']['f']);
									$_POST['cover'][] = implode('/', $arr);
									$cnt++;
									break;

								case 'replace':
									$_POST['cover'][] = str_replace($replace, str_replace('{0}', $i_id, $rule['p']['f'][1]), $cover);
									$cnt++;
									break;
							}
						}
						$_GET['lastUrl'] = $url;
						return checkEcho();
					}
				}
				if($cover !== ''){
					$_POST['cover'][] = $cover;
					$cnt++;
				}
			}
		}
		checkEcho();
		if($cnt == 0){
			return logError();
		}else{
			$_GET['cnt'] = 0;
			$_GET['lastUrl'] = $url;
		}
		//$_GET['last_cnt'] = count($_POST['res']['cover']);

	}

	function logError(){
		if(++$_GET['cnt'] >= 3){
			echoJson();
		}else{
			loadPage();
		}
	}

	function _g($j, $s){
		if(isset($j['children'][$s])){
			return $j['children'][$s];
		}
		return $j[$s];
	}

	function checkEcho(){

		if($_GET['data']['once']){ // 只请求一次
			echoJson($_POST);
		}else{
			for($i=2;$i<=$_POST['max'];$i++){
				//var_dump('127.0.0.1/syashinn/php/getPage.php?url='.getPage($_GET['url'], $_GET['data']['imgrule'], $i).'&data='.json_encode($_GET['data'])); exit();
				$_POST['urls'][] = getPage($_GET['url'], $_GET['data']['imgrule'], $i);
			}	
			echoJson($_POST);
		}
	}

	function echoJson(){
		if(isset($_GET['data']['res'])){
			foreach ($_GET['data']['res'] as $key => $value) {
				foreach($value as $v){
					$b_save = isset($v['m']) && $v['m'];
					switch($v['t']){
						case 'remove':
							switch ($v['f'][0]) {
								case 'not_exists':
									foreach($_POST[$key] as $r_k => $r_v){
										if(strpos($_POST[$key][$r_k], $v['f'][1]) === FALSE){
											unset($_POST[$key][$r_k]);
										}
									}
									break;

								case 'exists':
									foreach($_POST[$key] as $r_k => $r_v){
										if(strpos($_POST[$key][$r_k], $v['f'][1]) !== FALSE){
											unset($_POST[$key][$r_k]);
										}
									}
									break;
							}
							break;
						case 'replace':
							foreach($_POST[$key] as $r_k => $r_v){
								if($b_save){
									if(!isset( $_POST[$key.'_'])) $_POST[$key.'_'] = [];
									 $_POST[$key.'_'][$r_k] = $r_v;
								}
								$_POST[$key][$r_k] = str_replace($v['f'][0], $v['f'][1], $_POST[$key][$r_k]);
							}
							break;

						case 'set':
							foreach($_POST[$key] as $r_k => $r_v){
								$_POST[$key][$r_k] = str_replace("{str}", $r_v, $v['f']);
							}
							break;

						case "proxy":
							foreach($_POST[$key] as $r_k => $r_v){
								//'https://neysummer-syashinn.glitch.me/
								$_POST[$key][$r_k] = './php/image.php?image='.urlencode($r_v).'&referer='.urlencode($_GET['url']);
							}
							break;
					}
				}
			}
		}
		$res = json_encode($_POST);
		file_put_contents('./cache/'.md5($_GET['url']).'.json', $res);
		echo $res;
		exit();
	}

	function getText1($text){
		return trim(explode("\n", $text)[0]);
	}

	function checkUrl($host, $url){
		if(is_null($url) || $url == '') return '';
		if(
			strpos($url, '//') === FALSE){
			if(strpos($url, '/') == 0){
				$url = $host.$url;
			}else{
				$url = $host.'/'.$url;
			}
		}
		return trim($url);
	}

	function parseDom($lastTag, $d, $arr){
		$skip = false;
		$index = 0;
		foreach ($arr as $k => $s) {
			$s = strtolower($s);
			switch($s){
				case 'text':
					return $d->plaintext;

				case 'attr':
					return isset($arr[$k+1]) ? $d->{$arr[$k+1]} : '';

				case 'parentnode':
					$d = $d->parent();
					$skip = true;
					break;

				case 'next_sibling':
					$d = $d->next_sibling();
					$skip = true;
					break;

				case 'first':
					$d = $d->first_child();
					$skip = true;
					break;

				// case 'children':
				// 	$d = $d->children ()
				// 	break;

				default:
					if($index == 0){
						$index = intval($s);
						if($index !== 0){
							$skip = true;
						}
					}
					break;
			}
			if(!$skip){
					if($s != 'div' && $lastTag !== $s){ // 如果是同个元素则不获取.但div可以互相覆盖
					$d = $d->find($s, $index);
					if($index !== 0){
						$index = 0;
					}
				}
			}
			$skip = false;
		}
		return '';
	}

	function getFile($filePath){
		$text = file_get_contents($filePath);  
        //$encodType = mb_detect_encoding($text);  
        define('UTF32_BIG_ENDIAN_BOM', chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF));  
        define('UTF32_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00));  
        define('UTF16_BIG_ENDIAN_BOM', chr(0xFE) . chr(0xFF));  
        define('UTF16_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE));  
        define('UTF8_BOM', chr(0xEF) . chr(0xBB) . chr(0xBF));  
        $first2 = substr($text, 0, 2);  
        $first3 = substr($text, 0, 3);  
        $first4 = substr($text, 0, 3);  
        $encodType = "";  
        if ($first3 == UTF8_BOM)  
            $encodType = 'UTF-8 BOM';  
        else if ($first4 == UTF32_BIG_ENDIAN_BOM)  
            $encodType = 'UTF-32BE';  
        else if ($first4 == UTF32_LITTLE_ENDIAN_BOM)  
            $encodType = 'UTF-32LE';  
        else if ($first2 == UTF16_BIG_ENDIAN_BOM)  
            $encodType = 'UTF-16BE';  
        else if ($first2 == UTF16_LITTLE_ENDIAN_BOM)  
            $encodType = 'UTF-16LE';  

        //下面的判断主要还是判断ANSI编码的·  
        if ($encodType == '') {//即默认创建的txt文本-ANSI编码的  
            $content = iconv("GBK", "UTF-8", $text);  
        } else if ($encodType == 'UTF-8 BOM') {//本来就是UTF-8不用转换  
            $content = $text;  
        } else {//其他的格式都转化为UTF-8就可以了  
            $content = iconv($encodType, "UTF-8", $text);  
        }  
        return $text;
	}
function Rand_IP(){
	srand(microtime(true));
    return round(rand(600000, 2550000) / 10000).".".round(rand(600000, 2550000) / 10000).".".round(rand(600000, 2550000) / 10000).".".round(rand(600000, 2550000) / 10000);
}

function getStringByStartAndEnd($s_text, $s_start, $s_end, $i_start = 0, $b_end = false){
	if(($i_start = strpos($s_text, $s_start, $i_start)) !== false){
		if(($i_end = strpos($s_text, $s_end, $i_start + strlen($s_start))) === false){
			if($b_end){
				$i_end = strlen($s_text);
			}else{
				return;
			}
		}
		return substr($s_text, $i_start + strlen($s_start), $i_end - $i_start - strlen($s_start));
	}
}


function getStringByStartAndEnd_array($s_text, $s_start, $s_end, $i_start = 0){
	$res = [];
	while(1){
		if(($i_start = strpos($s_text, $s_start, $i_start)) !== false){
			if(($i_end = strpos($s_text, $s_end, $i_start + strlen($s_start))) !== false){
				$res[] = substr($s_text, $i_start + strlen($s_start), $i_end - $i_start - strlen($s_start));
				$i_start = $i_end + strlen($s_end);
				continue;
			}
		}
		break;
	}
	return $res;
}
