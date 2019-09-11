<?php
/**
 * 读取docx压缩文件内容,并支持以下功能：
 * 1.标题1到标题6;
 * 2.字体颜色
 * 3.背景颜色
 * 4.下划线
 * 5.斜体
 * 6.加粗
 * 7.字体大小
 * 8.列表(暂不支持，如果想实现列表可自行添加序列号，缩进使用格式段落)
 * 9.读取图片(to do)
 * 10.超链接(to do)
 */
class WordPHP
{
	private $debug = true;
	private $rels_xml;
	private $doc_xml;
	private $last = 'none';
	private $encoding = 'utf-8';
	
	/**
	 * READS The Document and Relationships into separated XML files
	 * 
	 * @param String $filename The filename
	 * @return void
	 */
	private function readZipPart($filename)
	{
		$zip = new ZipArchive();
		$_xml = 'word/document.xml';
		$_xml_rels = 'word/_rels/document.xml.rels';
		
		if (true === $zip->open($filename)) {
			if (($index = $zip->locateName($_xml)) !== false) {
				$xml = $zip->getFromIndex($index);
			}
			if (($index = $zip->locateName($_xml_rels)) !== false) {
				$xml_rels = $zip->getFromIndex($index);					
			}

			$zip->close();
		//} else die('non zip file');
		}else{
		 	return "";
		}
		
		// else die('打开doc文件异常');
		
		// if (true === $zip->open($filename)) {
		// 	if (($index = $zip->locateName($_xml_rels)) !== false) {
		// 		$xml_rels = $zip->getFromIndex($index);					
		// 	}
		// 	$zip->close();
		// //} else die('non zip file');
		// }else{
		// 	return "";
		// }
		// else die('打开doc文件异常');


		$this->doc_xml = new DOMDocument();
		$this->doc_xml->encoding = mb_detect_encoding($xml);
		$this->doc_xml->preserveWhiteSpace = false;
		$this->doc_xml->formatOutput = true;
		$this->doc_xml->loadXML($xml);
		$this->doc_xml->saveXML();
		
		$this->rels_xml = new DOMDocument();
		$this->rels_xml->encoding = mb_detect_encoding($xml);
		$this->rels_xml->preserveWhiteSpace = false;
		$this->rels_xml->formatOutput = true;
		$this->rels_xml->loadXML($xml_rels);
		$this->rels_xml->saveXML();
		
		if($this->debug) {
			echo "<textarea style='width:100%; height: 664px;'>";
			echo $this->doc_xml->saveXML();
			echo "</textarea>";
			echo "<textarea style='width:100%; height: 200px;'>";
			echo $this->rels_xml->saveXML();
			echo "</textarea>";
		}
		return true;
	}

	/**
	 * CHECKS THE FONT FORMATTING OF A GIVEN ELEMENT
	 * Currently checks and formats: bold, italic, underline, background color and font family
	 * 
	 * @param XML $xml The XML node
	 * @return String HTML formatted code
	 */
	private function checkFormating(&$xml)
	{	
		$node = trim($xml->readOuterXML());
		// add <br> tags
		if (strstr($node,'<w:br ')) $text .= '<br>';					 
		// look for formatting tags
		$f = "<span style='";
		$reader = new XMLReader();
		$reader->XML($node);
		// 样式选择
		static $listNum = 0;
		$contents = $aLink = '';
		while ($reader->read()) {
			if($reader->name == "w:b")
				$f .= "font-weight: bold;";
			if(($reader->name == "w:u") && ($reader->getAttribute("w:val") == 'single'))
				$f .= "text-decoration: underline;";
			if($reader->name == "w:color")
				$f .="color: #".$reader->getAttribute("w:val").";";
			if($reader->name == "w:rFonts" && $reader->getAttribute("w:ascii"))
				$f .="font-family:".$reader->getAttribute("w:ascii")." !important;";
			if($reader->name == "w:highlight" && $reader->getAttribute("w:val") != "none" && $reader->getAttribute("w:val") != "000000"){
				// 判断下是否是16进制的数
				$val = $reader->getAttribute("w:val");
				$val = preg_match('/[0-9a-f]{6}/i', $val)? '#'.$val: $val;

				$f .="background-color: ".$val.";";
			}
			if($reader->name == "w:i"){
				$f .= 'font-style: italic;';
			}
			if($reader->name == "w:sz"){
				$f .= 'font-size:'.($reader->getAttribute("w:val")/2).'pt;';
			}
			// 首行缩进
			if($reader->name == "w:ind" && $reader->getAttribute("w:leftChars")){
				$f .= 'padding-left:'.($reader->getAttribute("w:leftChars")/100).'em;';
			}

			// 列表(当前支持的列表只能从小到大的技术，如果出现多个列表，计数不会中断,如果出现二级列表三级列表就需要自己手动操作  to do...)
			// if($reader->name == 'w:keepNext'){
			// 	$contents = ++$listNum.'.';
			// }
			// 超链接
			if($reader->name == 'w:instrText'){
				$patter = '/<w:instrtext\s+xml:space="preserve"\>\s+HYPERLINK\s+"(.*)"/i';
				preg_match($patter, $node, $match);
				$aLink = '<a href="'.$match[1].'" target="_blank">'.trim($xml->expand()->textContent).'</a>';
			}
		}
		
		$f .= "'>";

		return $f.htmlentities(trim($xml->expand()->textContent))."</span>".$aLink;
	}
	
	/**
	 * CHECKS THE ELEMENT FOR UL ELEMENTS
	 * Currently under development
	 * 
	 * @param XML $xml The XML node
	 * @return String HTML formatted code
	 */
	// private function getListFormating(&$xml)
	// {	
	// 	$node = trim($xml->readOuterXML());
		
	// 	$reader = new XMLReader();
	// 	$reader->XML($node);
	// 	$ret="";
	// 	$close = "";
	// 	while ($reader->read()){
	// 		// if($reader->name == "w:numPr" && $reader->nodeType == XMLReader::ELEMENT ) {
				
	// 		// }
	// 		if($reader->name == "w:numId" && $reader->hasAttributes) {
	// 			switch($reader->getAttribute("w:val")) {
	// 				case 1:
	// 					$ret['open'] = "<ol><li>";
	// 					$ret['close'] = "</li></ol>";
	// 					break;
	// 				case 2:
	// 					$ret['open'] = "<ul><li>";
	// 					$ret['close'] = "</li></ul>";
	// 					break;
	// 			}
				
	// 		}
	// 	}
	// 	return $ret;
	// }
	
	/**
	 * CHECKS IF THERE IS AN IMAGE PRESENT
	 * Currently under development
	 * 
	 * @param XML $xml The XML node
	 * @return String HTML formatted code
	 */
	private function checkImageFormating(&$xml) {
		
	}
	
	/**
	 * CHECKS IF ELEMENT IS AN HYPERLINK
	 *  
	 * @param XML $xml The XML node
	 * @return Array With HTML open and closing tag definition
	 */
	private function getHyperlink(&$xml)
	{
		$ret = array('open'=>'<ul>','close'=>'</ul>');
		$link ='';
		if($xml->hasAttributes) {
			$attribute = "";
			while($xml->moveToNextAttribute()) {
				if($xml->name == "r:id")
					$attribute = $xml->value;
			}
			
			if($attribute != "") {
				$reader = new XMLReader();
				$reader->XML($this->rels_xml->saveXML());
				
				while ($reader->read()) {
					if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name=='Relationship') {
						if($reader->getAttribute("Id") == $attribute) {
							$link = $reader->getAttribute('Target');
							break;
						}
					}
				}
			}
		}
		
		if($link != "") {
			$ret['open'] = "<a href='".$link."' target='_blank'>";
			$ret['close'] = "</a>";
		}
		
		return $ret;
	}
	
	/**
	 * READS THE GIVEN DOCX FILE INTO HTML FORMAT
	 *  
	 * @param String $filename The DOCX file name
	 * @return String With HTML code
	 */
	public function readDocument($filename) {
		$returnZip = $this->readZipPart($filename);
		// 当zip出现错误的时候不能直接显示在客户端，而是返回空字符串，增加用户体验
		if(!$returnZip) return ""; //null
		$reader = new XMLReader();
		
		$reader->XML($this->doc_xml->saveXML());
		$text = ''; $list_format="";
		
		$formatting = 0;
		// loop through docx xml dom
		while ($reader->read()) {
			// look for new paragraphs
			$paragraph = new XMLReader;
			$p = $reader->readOuterXML();
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name === 'w:p') {
				// set up new instance of XMLReader for parsing paragraph independantly				
				$paragraph->xml($p);
				// 匹配w:pStyle标签用作h标签[只支持h1-h6]
				preg_match('/<w:pStyle\s?w:val="(\d*)"/',$p,$matches);
				if(isset($matches[1])) {
					switch($matches[1]){
						case '2': $formatting = 1; break;
						case '3': $formatting = 2; break;
						case '4': $formatting = 3; break;
						case '5': $formatting = 4; break;
						case '6': $formatting = 5; break;
						case '7': $formatting = 6; break;
					}
					// 获取到h标签
					$text .= ($formatting > 0)? '<h'.$formatting.'>':'<p>';
				}
				// 组装到text变量中
				$text .= '<p>';
				
				// loop through paragraph dom
				while ($paragraph->read()) {
					// look for elements
					if ($paragraph->nodeType == XMLREADER::ELEMENT && ($paragraph->name === 'w:r' || $paragraph->name === 'w:pPr')) {
						if($list_format == ""){
							$text .= $this->checkFormating($paragraph);
						}else{
							$text .= $list_format['open'];
							$text .= $this->checkFormating($paragraph);
							$text .= $list_format['close'];
						}
						$list_format ="";
						$paragraph->next();
					}
					// else if($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:pPr') { //lists
					// 	$list_format = $this->getListFormating($paragraph);
					// 	$paragraph->next();
					// }
					else if($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:drawing') { //images
						$text .= $this->checkImageFormating($paragraph);
						$paragraph->next();
					}
					// else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:instrText') {
					// 	$hyperlink = $this->getHyperlink($paragraph);
					// 	$text .= $hyperlink['open'];
					// 	$text .= $this->checkFormating($paragraph);
					// 	$text .= $hyperlink['close'];
					// 	$paragraph->next();
					// }
				}
				$text .= ($formatting > 0)? '</h'.$formatting.'>': '</p>';
			}
		}
		$reader->close();
		if($this->debug) {
			echo "<div style='width:100%; height: 764px;'>";
			echo iconv($this->encoding, "UTF-8",$text);
			echo "</div>";
		}
		return $text;
	}
}
