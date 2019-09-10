<?php
//============================================================+
// File name   : class.wordphp.php
// Begin       : 2014-03-09
// Last Update : 2014-08-08
// Version     : 1.0
// License     : GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
// 	----------------------------------------------------------------------------
//  Copyright (C) 20014 Ricardo Pinto
// 	
// 	This program is free software: you can redistribute it and/or modify
// 	it under the terms of the GNU Lesser General Public License as published by
// 	the Free Software Foundation, either version 2.1 of the License, or
// 	(at your option) any later version.
// 	
// 	This program is distributed in the hope that it will be useful,
// 	but WITHOUT ANY WARRANTY; without even the implied warranty of
// 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// 	GNU Lesser General Public License for more details.
// 	
// 	You should have received a copy of the GNU Lesser General Public License
// 	along with this program.  If not, see <http://www.gnu.org/licenses/>.
// 	
//  ----------------------------------------------------------------------------
//
// Description : PHP class to read DOCX file into HTML format
//
// Author: Ricardo Pinto
//
// (c) Copyright:
//               Ricardo Pinto
//============================================================+

class WordPHP
{
	private $debug = true;
	private $rels_xml;
	private $doc_xml;
	private $last = 'none';
	private $encoding = 'utf-8';
	
	/**
	 * CONSTRUCTOR
	 * 
	 * @param Boolean $debug Debug mode or not
	 * @return void
	 */
	public function __construct($encoding="gbk", $debug_=null)
	{
		if($debug_ != null)
			$this->debug = $debug_;
		// $this->encoding = $encoding;
	}
	
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
				$f .= 'font-size:'.$reader->getAttribute("w:val").'px;';
			}
		}

		$f .= "'>";
		return $f.htmlentities(trim($xml->expand()->textContent))."</span>";
	}
	
	/**
	 * CHECKS THE ELEMENT FOR UL ELEMENTS
	 * Currently under development
	 * 
	 * @param XML $xml The XML node
	 * @return String HTML formatted code
	 */
	private function getListFormating(&$xml)
	{	
		$node = trim($xml->readOuterXML());
		
		$reader = new XMLReader();
		$reader->XML($node);
		$ret="";
		$close = "";
		while ($reader->read()){
			// if($reader->name == "w:numPr" && $reader->nodeType == XMLReader::ELEMENT ) {
				
			// }
			if($reader->name == "w:numId" && $reader->hasAttributes) {
				switch($reader->getAttribute("w:val")) {
					case 1:
						$ret['open'] = "<ol><li>";
						$ret['close'] = "</li></ol>";
						break;
					case 2:
						$ret['open'] = "<ul><li>";
						$ret['close'] = "</li></ul>";
						break;
				}
				
			}
		}
		return $ret;
	}
	
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
		
		$formatting['header'] = 0;
		// loop through docx xml dom
		while ($reader->read()) {
		// look for new paragraphs
			$paragraph = new XMLReader;
			$p = $reader->readOuterXML();
			if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name === 'w:p') {
				// set up new instance of XMLReader for parsing paragraph independantly				
				$paragraph->xml($p);

				// preg_match('/<w:rStyle\s?w:val="([8|9|10|11])"/',$p,$matches);
				// if(isset($matches[0])) {
				// 	echo $matches[0] = 8;
				// 	switch($matches[0]){
				// 		case '8': $formatting['header'] = 1; break;
				// 		case '9': $formatting['header'] = 2; break;
				// 		case '10': $formatting['header'] = 3; break;
				// 		case '11': $formatting['header'] = 4; break;
				// 	}
				// }
				// echo $formatting['header'];
				// open h-tag or paragraph
				// $text .= ($formatting['header'] > 0) ? '<h'.$formatting['header'].'>' : '<p>';
				$text .= '<p>';
				
				// loop through paragraph dom
				while ($paragraph->read()) {
					// look for elements
					if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:r') {
						if($list_format == "")
							$text .= $this->checkFormating($paragraph);
						else {
							$text .= $list_format['open'];
							$text .= $this->checkFormating($paragraph);
							$text .= $list_format['close'];
						}
						$list_format ="";
						$paragraph->next();
					}
					else if($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:pPr') { //lists
						$list_format = $this->getListFormating($paragraph);
						$paragraph->next();
					}
					else if($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:drawing') { //images
						$text .= $this->checkImageFormating($paragraph);
						$paragraph->next();
					}
					else if ($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:hyperlink') {
						$hyperlink = $this->getHyperlink($paragraph);
						$text .= $hyperlink['open'];
						$text .= $this->checkFormating($paragraph);
						$text .= $hyperlink['close'];
						$paragraph->next();
					}
				}
				// $text .= ($formatting['header'] > 0) ? '</h'.$formatting['header'].'>' : '</p>';
				$text .= '</p>';
			}
		}
		$reader->close();
		if($this->debug) {
			echo "<div style='width:100%; height: 764px;'>";
			echo iconv($this->encoding, "UTF-8",$text);
			echo "</div>";
		}
		// return $text;
	}
}
