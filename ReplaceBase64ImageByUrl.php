<?php
/**
 * { 将 含有base64图片的img上传到云并用地址替换原来图片的base64字符串}
 */
function ClearBase64Data($strWithBase64Image) {
	$upDir = '/tmp/chibi';//临时图片存放目录
	if(!file_exists($upDir)){
		mkdir($upDir,0777);
	}

	$rule = '/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i';

	$newContent = preg_replace_callback($rule, function($blob) use ($upDir){
		if (substr($blob[2], 15, 6) !== 'base64'){
			return $blob[0];
		}

		//write the base64 image to your localstorage temporarily
		$fileName = rand(1, 10000000000).time().'.png';
		$newFile = $upDir.$fileName;
		if (file_put_contents($newFile, base64_decode(substr($blob[2], 21)))) {
			$imgPath = $upDir.$fileName;
		} else {
			return $blob[0];
		}
		$file = [
			'path' => $imgPath,
			'name' => 'file',
			'type' => 'image/png'
		];

		//the auth data required by CDN provider
		$data = [
			'policy' => "your cdn policy",
			'signature' => "your valid signature",
		];

		//upload your file
		$res = postRequest("your CDN upload address", $data, $file, null, null);

		return (!is_null($res) && $res['code'] === 200) ? '<img src="//file.baixing.net'.$res['url'].'" />' : $blob[0];
	}, $strWithBase64Image);

	//clear the temporary data
	deleteDir($upDir);
}

function postRequest($url, $data, $file = null, $header = null, $cookie = null) {
	$curl = curl_init();
	curl_setopt ( $curl, CURLOPT_URL, $url);
	curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, FALSE );
	curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, FALSE );

	//携带cookie文件
	if(! empty($cookie)){
	curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie);
	}

	//携带头
	if(!empty($header)){
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	}

	//上传文件
	if(!empty($file)){
	$file_obj=new \CURLFile($file['path'],$file['type']);
	$data["{$file['name']}"] = $file_obj;
	}

	//发送post数据
	if(!empty( $data) && !isset($file_obj)) {
		curl_setopt ($curl, CURLOPT_POST, 1);
		curl_setopt ($curl, CURLOPT_POSTFIELDS, http_build_query($data) );
	} elseif (!empty($data)){
		curl_setopt ($curl, CURLOPT_POST, 1);
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
	$output= curl_exec ($curl);
	curl_close ($curl);

	return json_decode($output, true);
}

function deleteDir($dir) {
	$dh = opendir($dir);
	while ($file = readdir($dh)) {
		if($file !== "." && $file !== "..") {
			$fullPath = $dir."/".$file;
			if(!is_dir($fullPath)) {
				unlink($fullPath);
			} else {
				deldir($fullPath);
			}
		}
	}
	closedir($dh);
	if(rmdir($dir)) {
		return true;
	} else {
		return false;
	}
}