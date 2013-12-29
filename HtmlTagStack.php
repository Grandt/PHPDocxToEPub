<?php

class HtmlTagStack {

	public function __construct() {
		
	}
	
	private $stack = array();

	function push($tag) {
		if (is_tag($tag)) {
			$this->stack[] = $tag;
		}
	}
	
	function pop() {
		if (sizeof($this->stack) > 0) {
			return array_pop($this->stack);
		}
		return '';
	}

	function pop_end_tag() {
		return unicode(get_end_tag($this->pop()));
	}

	function spool_end() {
		$html = '';
		foreach (array_reverse($this->stack) as $tag) {
			$html .= get_end_tag($tag);
		}
		return $html;
	}

	function spool_start() {
		$html = '';
		foreach ($this->stack as $item) {
			$html .= $item;
		}
		return $html;
	}

	function spool_around_and_pop($tag) {
		$tN = get_tag_name($tag);

		$html = '';
		$htmlS = '';
		reset($this->stack);
		foreach (array_reverse($this->stack, true) as $tKey => $tTag) {
			if ($tN == get_tag_name($tTag)) {
				unset($this->stack[$tKey]);
				break;
			}
			$html .= get_end_tag($tTag);
			$htmlS .= $tTag;
		}
		return $html . $tag . $htmlS;
	}

	function has_elements() {
		return sizeof($this->stack) > 0;
	}

	function has_tag($tag) {
		$tN = is_tag($tag) ? get_tag_name($tag) : $tag;
		reset($this->stack);
		foreach ($this->stack as $sTag) {
			if ($tN == get_tag_name($sTag)) {
				return true;
			}
		}
		return false;
	}

	function get_last() {
		if (sizeof($this->stack) > 0) {
			return end($this->stack);
		}
		return '';
	}
	
	function flush() {
		unset($this->stack);
		$this->stack = array();
	}

	function get_stack() {
		return $this->stack;
	}
}

function get_end_tag($tag) {
	if (is_tag($tag)) {
		return preg_replace('#.*<([^\ >]+).*#', '</\1>', $tag);
	}
	return '';
}

function get_tag_name($tag) {
	if (is_tag($tag)) {
		return preg_replace('#</*([^\ >]+).*#', '\1', $tag);
	}
	return '';
}

function is_end_tag($tag) {
	return preg_match('#</([^\ >]+)>#', $tag) === 1;
}

function is_comment_tag($tag) {
	return preg_match('#<\!\-\-([^>]+)>#', $tag) === 1;
}

function is_closed_tag($tag) {
	return preg_match('#<(.+?)/>#', $tag) === 1;
}

function is_tag($tag) {
	return strlen($tag) > 2 && strpos($tag, '<') !== FALSE && strpos($tag, '>') !== FALSE;
}

function tag_sanitizer($html) {
	$blockTags = array(
		'address' => "", 'blockquote' => "", 'del' => "", 'div' => "", 'dl' => "",  'fieldset' => "", 
		'form' => "", 'ins' => "", 'noscript' => "", 'ol' => "", 'pre' => "", 'ul' => "",
		'table' => "", 'tr' => "", 'th' => "", 'td' => "");

	$body = '';
	$stack = new HtmlTagStack();

	preg_match_all('#(<[^>]+>)([^<]*)#', $html, $tags);

	$cTags = array();
	for ($i = 0 ; $i < sizeof($tags[0]); $i++) {
		$rTag = array();
		$rTag[0] = $tags[1][$i];
		$rTag[1] = $tags[2][$i];
		$cTags[] = $rTag;
	}

	foreach ($cTags as $rTag) {
		$name = get_tag_name($rTag[0]);
		$is_end = is_end_tag($rTag[0]);
		$is_closed = is_closed_tag($rTag[0]) || is_comment_tag($rTag[0]);

		if (array_key_exists($name, $blockTags)) {
			$body .= $rTag[0];
			$body .= $rTag[1];
		} else if ($name == 'p') {
			if ($is_end) {
				$body .= $stack->spool_end();
				$body .= $rTag[0];
				$body .= $rTag[1];
			} else if ($is_closed) {
				$body .= $rTag[0];
				$body .= $rTag[1];
			} else {
				$body .= $rTag[0];
				$body .= $stack->spool_start();
				$body .= $rTag[1];
			}
		} else {
			if ($is_end) {
				$t = $stack->get_last();
				$tn = get_tag_name($t);

				if ($tn == $name) {
					$body .= $rTag[0];
					$stack->pop();
				} else if ($stack->has_tag($rTag[0])) {
					$body .= $stack->spool_around_and_pop($rTag[0]);
				}
			} else if (!$is_closed) {
				$stack->push($rTag[0]);
				$body .= $rTag[0];
			} else {
				$body .= $rTag[0];
			}

			$body .= $rTag[1];
		}
	}
	$stack->flush();
	return $body;
}
	