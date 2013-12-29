<?php
class Docx2ePub {
	
	private $hasHtmlTagStack = FALSE;
	private $hasHtmlPurifier = FALSE;
	private $purifier = null;
		
	public $content_start = "";
	public $content_end = "";
	public $book = null;
	public $cssData = null;
	public $cssWordData = null;

	public $bookMetadata = array();

	private $chapter = ''; 
	private $htmlBlock = ''; 
	private $chapterId = ''; 
	private $chapterParts = array();
	private $chapterNames = array();
	private $tocRef = null;
	private $tocRefWrCount = 0;
	private $toc = array();

	function __construct() {
		$this->content_start =
		"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
		. "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"   \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
		. "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
		. "<head>"
		. "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
		. "<link rel=\"stylesheet\" type=\"text/css\" href=\"styles/WordStyles.css\" />\n"
		. "<link rel=\"stylesheet\" type=\"text/css\" href=\"styles/Styles.css\" />\n"
		. "<title>Book</title>\n"
		. "</head>\n"
		. "<body>\n";

		$this->content_end = "</body>\n</html>\n";
		
		include_once 'ePub/EPub.php';
		if (file_exists('HtmlTagStack.php')) {
			include_once 'HtmlTagStack.php';
		}
		if (file_exists('./htmlpurifier/library/HTMLPurifier.auto.php')) {
			require_once './htmlpurifier/library/HTMLPurifier.auto.php';
		}

		$this->hasHtmlTagStack = class_exists("HtmlTagStack");
		$this->hasHtmlPurifier = class_exists("HTMLPurifier");
		$this->purifier = null;

		if ($this->hasHtmlPurifier) {
			$config = HTMLPurifier_Config::createDefault();
			$this->purifier = new HTMLPurifier($config);
		}
	}
	
	function parse($path) {
		$this->formatting['bold'] = 'closed'; 
		$this->formatting['italic'] = 'closed'; 
		$this->formatting['underline'] = 'closed'; 
		$this->formatting['header'] = 0;
		$this->formatting['align'] = "both";

		$this->chapter = ''; 
		$this->chapterId = ''; 
		$this->chapterNames = array();
		$this->chapterParts = array();
		$this->tocRef = null;
		$this->tocRefWrCount = 0;
		$this->toc = array();

		$this->book = new EPub();
		if (!isset($this->cssData)) {
			$this->cssData = file_get_contents("Resources/WordStyles.css");
		}
		if (!isset($this->cssWordData)) {
			$this->cssWordData = file_get_contents("Resources/Styles.css");
		}
		$this->book->addCSSFile("styles/WordStyles.css", "wordcss1", $this->cssWordData);
		$this->book->addCSSFile("styles/Styles.css", "css1", $this->cssData);

		$documentFile = "";
		$relsFile = "";
		$metaFile = "";

		$relations = array();
		$images = array();

		$zip = new ZipArchive;
		$res = $zip->open($path);
		if ($res === TRUE) {
			$documentFile = $zip->getFromName("word/document.xml");
			$relsFile = $zip->getFromName("word/_rels/document.xml.rels");
			$metaFile = $zip->getFromName("docProps/core.xml");
		} else {
			echo "<p>File $path could not be oppened</p>\n";
			exit();
		}

		$relsReader = new XMLReader;
		$relsReader->XML($relsFile);
		while ($relsReader->read()) { 
			if ($relsReader->nodeType == XMLREADER::ELEMENT && $relsReader->name === 'Relationship') {
				$node = trim($relsReader->readOuterXml());
				$rel = new Relationship();
				$rel->id = $relsReader->getAttribute('Id');
				$rel->type = $relsReader->getAttribute('Type');
				$rel->target = $relsReader->getAttribute('Target');

				$relations[$rel->id] = $rel;
			}
		}	

		if (strlen(trim($metaFile)) > 0) {
			$metaReader = new XMLReader;
			$metaReader->XML($metaFile);
			$metaReader->read();
			while ($metaReader->read()) { 
				if ($metaReader->nodeType == XMLREADER::ELEMENT) {
					$name = explode(':', $metaReader->name);
					$data = trim($metaReader->readString());
					if (strlen($data) > 0) {
						$this->bookMetadata[$name[1]] = $data;
					} else {
						$this->bookMetadata[$name[1]] = null;
					}
				}
			}
		}
		
		// set location of docx text content file
		$reader = new XMLReader;
		$reader->XML($documentFile);

		$tableCount = 0;
		
		$tableCols = array();
		$tableRowSpan = array();
		$tableX = 0;
		$tableY = 0;
		$inTable = false;
		$inTableCell = false;

		// loop through docx xml dom
		while ($reader->read()) { 
			// look for new paragraphs
			if ($reader->nodeType == XMLREADER::END_ELEMENT) {
				// echo "<pre>*Node name: &lt;/" . $reader->name . "&gt;</pre>\n";
				switch($reader->name) {
					case 'w:tbl':
						$inTable = false;
						$this->chapter .= "</table>\n";
						foreach ($tableRowSpan as $col => $count) {
							$this->chapter = str_replace('rowspan="[' . $col . ']', 'rowspan="' . $count, $this->chapter);
						}
						break;
					case 'w:tr':
						$this->chapter .= "</tr>\n";
						break;
					case 'w:tc':
						$inTableCell = false;
						if (array_key_exists('r'.$tableX, $tableRowSpan) && 
								$tableRowSpan['r'.$tableX] > 1) {
							break;
						}
						
						$this->chapter .= "</td>\n";
						break;
				case 'w:tblGrid':
					$this->chapter .= "</colgroup>\n";
					$totalWidth = 0;
					foreach($tableCols as $w) {
						$totalWidth += $w;
					}
					$widthPct = $totalWidth / 100;
					$tableX = 0;
					foreach($tableCols as $w) {
						$tableX++;
						$pct = (int)($w/$widthPct);
						$this->chapter = str_replace('width="[col' . $tableX . ']', 'width="' . $pct, $this->chapter);
						$totalWidth += $w;
					}
					break;
				}
			} 
			if ($reader->nodeType != XMLREADER::ELEMENT) {
				continue;
			}
			
			switch($reader->name) {
				case 'w:tbl':
					$tableCount++;
					$this->chapter .= "<table id=\"docxTable$tableCount\">\n";
					$tableCols = array();
					$tableRowSpan = array();
					$tableX = 0;
					$tableY = 0;
					$inTable = true;
					$inTableCell = false;
					break;
				case 'w:tblGrid':
					$tableX = 0;
					$this->chapter .= "<colgroup>\n";
					break;
				case 'w:gridCol':
					$tableX++;
					$this->chapter .= "<col width=\"[col$tableX]%\" />\n";
					$tableCols[] = $reader->getAttribute("w:w");
					break;
				case 'w:tr':
					$tableX = 0;
					$tableY++;
					$this->chapter .= "<tr>\n";
					break;
				case 'w:tc':
					$inTableCell = true;
					$tableX++;
					$td = "<td";
					$addTd = true;
					$p = $reader->readInnerXML();
					if (strstr($p, 'w:gridSpan ')) {
						preg_match('/w:gridSpan.+?w:val="([^"]+)"/', $p, $matches);
						if (sizeof($matches) == 2) {
							$td .= ' colspan="' . $matches[1] . '"';
						}
					}
					
					if (strstr($p, 'w:vMerge')) {
						preg_match('/w:vMerge.+?w:val="([^"]+)"/', $p, $matches);
						if (sizeof($matches) == 2 && $matches[1] == "restart") {
							if (array_key_exists('r'.$tableX, $tableRowSpan)) {
								$this->chapter = str_replace('rowspan="[r' . $tableX . ']', 'rowspan="' . $tableRowSpan['r'.$tableX], $this->chapter);
								unset($tableRowSpan['r'.$tableX]);
							}
							$td .= ' rowspan="[r' . $tableX . ']"';
							$tableRowSpan['r'.$tableX] = 1;
						} else {
							$addTd = false;
							$tableRowSpan['r'.$tableX]++;
						}
					} else {
						if (array_key_exists('r'.$tableX, $tableRowSpan)) {
							$this->chapter = str_replace('rowspan="[r' . $tableX . ']', 'rowspan="' . $tableRowSpan['r'.$tableX], $this->chapter);
							unset($tableRowSpan['r'.$tableX]);
						}

					}
					if ($addTd) {
						$this->chapter .= $td . ">\n";
					}
					break;
			}
			
			
			if ($reader->name === 'w:p') {
				$elementId = null;
				$title = "";

				// set up new instance of XMLReader for parsing paragraph independantly
				$paragraph = new XMLReader;
				$p = $reader->readOuterXML();
				$paragraph->xml($p);

				if (strstr($p, 'w:bookmarkStart ')) {
					// <w:bookmarkStart w:id="4" w:name="_Toc362253443"/>
					preg_match('/w:bookmarkStart.+?w:name="([^"]+)"/', $p, $matches);
					if (sizeof($matches) == 2) {
						$wName = $matches[1];

						$this->addChapter($this->chapterId, $this->chapter);

						$elementId = $wName;
						$this->chapterId = $wName;
						$this->chapter = "\n<!-- EPUB::BOOKMARK='" . $wName . "' -->\n"; 
					}
				}

				// search for heading
				preg_match('/<w:pStyle w:val="(Heading.*?[1-6])"/', $p, $matches);
				if (sizeof($matches) >= 2) {
					switch($matches[1]){
						case 'Heading1': 
							$this->formatting['header'] = 1; 
							break;
						case 'Heading2': 
							$this->formatting['header'] = 2; 
							break;
						case 'Heading3': 
							$this->formatting['header'] = 3; 
							break;
						case 'Heading4': 
							$this->formatting['header'] = 4; 
							break;
						case 'Heading5': 
							$this->formatting['header'] = 5; 
							break;
						case 'Heading6': 
							$this->formatting['header'] = 6; 
							break;
						default:  
							$this->formatting['header'] = 0; 
							break;
					}
				} else {
					$this->formatting['header'] = 0; 
				}

				// open h-tag or paragraph
				if (isset($this->htmlBlock) && !($inTable && !$inTableCell)) {
					$this->chapter .= $this->htmlBlock;
					unset($this->htmlBlock);
				}
				$this->htmlBlock = new HtmlBlock();
				
				$this->htmlBlock->name =  ($this->formatting['header'] > 0) ? 'h'.$this->formatting['header'] : 'p';
				if (isset($elementId)) {
					$this->htmlBlock->attr['id'] = $elementId;
				}

				// loop through paragraph dom
				while ($paragraph->read()) {

					// look for elements
					if ($paragraph->nodeType == XMLREADER::END_ELEMENT) {
				//		echo "<pre>Node name: &lt;/" . $paragraph->name . "&gt;</pre>\n";
					} 
					if ($paragraph->nodeType != XMLREADER::ELEMENT) {
						continue;
					}
					$paragraphName = $paragraph->name;
					//echo "<pre>Node name: &lt;" . $paragraph->name . "&gt;</pre>\n";
					
					if($paragraphName === 'w:hyperlink') {
						$node = trim($paragraph->readOuterXml());

						preg_match('/.*w:hyperlink.+w:anchor="([^"]+)"/',$node,$matches);
						if (sizeof($matches) == 2) {

							$this->tocRefWrCount = substr_count($node, '<w:r ');

							$this->tocRef = $matches[1];
							$this->htmlBlock->body .= '<a href="#' . $this->tocRef . '">';
							$this->toc[$this->tocRef] = $this->tocRef;
						}
					} else if ($paragraphName === 'pic:pic') {
						$picName = null;
						$picId = null;

						$picNode = new XMLReader;
						$p = $paragraph->readOuterXML();
						$picNode->xml($p);
						while ($picNode->read()) {
							if ($picNode->nodeType != XMLREADER::ELEMENT) {
								continue;
							}
							$nodeName = $picNode->name;

							if ($nodeName === 'pic:cNvPr') {
								$picName = $picNode->getAttribute("name");
							} else if ($nodeName === 'a:blip') {
								$picId = $picNode->getAttribute("r:embed");
							} 
						}

						if (isset($picId) && array_key_exists($picId, $relations)) {
							$relation = $relations[$picId];

							$image = $zip->getFromName("word/" . $relation->target);

							if (isset($picName)) {
								$this->htmlBlock->body .= '<img src="images/' . $picName . '"  id ="' . $picId . '" alt="Image"/>';
								$images[$picId] = 'images/' . $picName;
							} else {
								$this->htmlBlock->body .= '<img src="' . $relation->target . '"  id ="' . $picId . '" alt="Image"/>';
								$images[$picId] = $relation->target;
							}
							$this->book->addFile($images[$picId], $picId, $image, $this->book->getMimeTypeFromUrl($images[$picId]));
						}
					} else if ($paragraphName === 'w:jc') {
						// w:val="right"
						preg_match('/w:jc.*?w:val="([^"]+)"/', $p, $matches);
						if (sizeof($matches) == 2) {
							$this->htmlBlock->attr['class'] = $matches[1];

						}
					} else if ($paragraphName === 'w:r') {
						$this->tocRefWrCount--;
						$node = trim($paragraph->readInnerXML());

						if (strstr($node,'<w:webHidden')) {
							continue;
						}
						if (strstr($node,'<w:instrText ')) {
							continue;
						}

						// add <br> tags
						if (strstr($node,'<w:br')) {
							$breakType = null;
							$breakClear = "";
							$break = TRUE;
							preg_match('/w:br.*?w:type="([^"]+)"/', $p, $matches);
							if (sizeof($matches) == 2) {
								$breakType = $matches[1];
							}
							if (isset($breakType) && $breakType == "page" && !$inTable) {
								$this->chapter .= $this->htmlBlock;
								unset($this->htmlBlock);

								$this->addChapter($this->chapterId, $this->chapter, true);
								
								$this->htmlBlock = new HtmlBlock();
								$this->htmlBlock->name =  ($this->formatting['header'] > 0) ? 'h'.$this->formatting['header'] : 'p';
								if (isset($elementId)) {
									$this->htmlBlock->attr['id'] = $elementId;
								}

								$this->chapter = "\n<!-- EPUB::PAGEBREAK -->\n"; 
								$break = FALSE;
							}
							preg_match('/w:br.*?w:clear="([^"]+)"/', $p, $matches);
							if (sizeof($matches) == 2) {
								$breakClear = $matches[1];
								$breakClear = 'style="clear: ' . ($breakClear == 'all' ? 'both' : $breakClear) . '"';
							}
							if ($break) {
								$this->htmlBlock->body .= "<br $breakClear/>\n";
							}
						}

						$spanClass = "";
						$isBold = strstr($node,'<w:b/>');
						$isItalic = strstr($node,'<w:i/>');

						if (strstr($node,'<w:rtl')) {
							$this->htmlBlock->attr['dir'] = "rtl";
						}
						
						if (strstr($node,'<w:u ')) {
							$spanClass .= 'underline';
						}
						if (strstr($node,'<w:caps')) {
							$spanClass .= ' caps';
						}
						if (strstr($node,'<w:smallCaps')) {
							$spanClass .= ' smallCaps';
						}
						if (strstr($node,'<w:strike')) {
							$spanClass .= ' strike';
						}
						if (strstr($node,'<w:dstrike')) {
							$spanClass .= ' dstrike';
						}
						if (strstr($node,'<w:shadow')) {
							$spanClass .= ' shadow';
						}
						if (strstr($node,'<w:emboss')) {
							$spanClass .= ' emboss';
						}
						if (strstr($node,'<w:vertAlign ')) {
							preg_match('/w:vertAlign.*?w:val="([^"]+)"/', $p, $matches);
							if (sizeof($matches) == 2 && $matches[1] != 'baseline') {
								$spanClass .= ' ' . $matches[1];
							}
						}
						
						$span = null;
						if (strlen($spanClass) > 1) {
							$span = '<span class="' . trim($spanClass) . '">';
						}

						// build text string of doc
						$this->htmlBlock->body .=
								($isBold ? '<strong>' : '').
								($isItalic ? '<em>' : '').
								(isset($span) ? $span : '').
								htmlentities($paragraph->expand()->textContent, ENT_COMPAT, 'UTF-8').
								(isset($span) ? '</span>' : '').
								($isItalic ? '</em>' : '').
								($isBold ? '</strong>' : '');

						if (isset($elementId)) {
							//$title .= iconv('UTF-8', 'ASCII//TRANSLIT',$paragraph->expand()->textContent);
							$title .= $paragraph->expand()->textContent;
						}

						if (isset($this->tocRef) && $this->tocRefWrCount <= 0) {
							$this->htmlBlock->body .= '</a>';
							unset($this->tocRef);
						}

						// reset formatting variables
						foreach ($this->formatting as $key=>$format){
							if ($format == 'open') {
								$this->formatting[$key] = 'opened';
							}
							if ($format == 'close') {
								$this->formatting[$key] = 'closed';
							}
						}
					}
				}        
				if (isset($this->tocRef)) {
					$this->htmlBlock->body .= '</a>';
					unset($this->tocRef);
				}

				if (!($inTable && !$inTableCell)) {
					$this->chapter .= $this->htmlBlock;
					unset($this->htmlBlock);
				}
				if (isset($title)) {
					$this->chapterNames[$elementId] = $title;
					unset($title);
				}
			}
		}
		$reader->close();
		$zip->close();

		$this->addChapter($this->chapterId, $this->chapter);
		return $this->book;
	
	}

	function addChapter($chapterId, $chapter, $chapterPart = FALSE) {
		//echo "<pre>Chapter:\n" . htmlentities($chapter) . "</pre>\n";
		if (strlen(trim($chapter)) > 0) {
			$data = $chapter;
			// echo "<pre>" . htmlentities($chapter) . "</pre>\n";
			if ($this->hasHtmlTagStack) {
				$data = tag_sanitizer($data);
			}
			if ($this->hasHtmlPurifier) {
				$data = $this->purifier->purify($data);
			}
			//echo "<pre>Chapter 2:\n" . htmlentities($data) . "</pre>\n";

			$chapterData = $this->content_start . $data . $this->content_end;
			if ($chapterPart) {
				$this->chapterParts[] = $chapterData;
			} else {
				if (sizeof($this->chapterParts) > 0) {
					$this->chapterParts[] = $chapterData;
					$this->book->addChapter($this->chapterNames[$chapterId], "C_$chapterId.xhtml", $this->chapterParts);
					unset($this->chapterParts);
					$this->chapterParts = array();
				} else {
					$this->book->addChapter($this->chapterNames[$chapterId], "C_$chapterId.xhtml", $chapterData);
				}
			}
		}
	}

	function setCSS($cssData) {
		$this->cssData = $cssData;
	}

	function setHtmlHead($htmlHead) {
		$this->content_start = $htmlHead;
	}
	
	function setHtmlFooter($htmlFooter = "\n</body>\n</html>\n") {
		$this->content_end = $htmlFooter;
	}
}
		
	
class Relationship {
	public $id = null;
	public $type = null;
	public $target = null;

	public function __toString() {
		return 'Relationship: Id: ' . $this->id . '; Type: ' . $this->type . '; Target: ' . $this->target;
	}
}

class HtmlBlock {
	public $name = null;
	public $attr = array();
	public $body = null;

	public function __toString() {
		$html = "<$this->name";
		if (sizeof($this->attr) > 0) {
			foreach ($this->attr as $key => $value) {
				$html .= ' ' . $key . '="' . $value. '"';
			}
		}
		return $html . '>' . $this->body . '</' . $this->name . ">\n";
	}
}
