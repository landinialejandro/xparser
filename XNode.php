<?php

namespace gymadarasz\xparser;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Symfony\Component\CssSelector\CssSelectorConverter;

class XNode {
	
	private static $maxResultsPerStartegy = 10;
	
	private static $maxRecursionInStartegy = 8;
	
	private $__xhtml = null;
	
	private $__source = null;
	
	private $__temps = [];
	
	public function __construct($xhtml = null, XNode &$source = null) {
		$this->outer($xhtml);
		$this->__source = &$source;
	}
	

	private function replace($search, $replace) {
		// replace only first occurrence cause more than one are in parent then it have to be a list instead an element
		$pos = strpos($this->__xhtml, $search);
		if ($pos !== false) {
			$__xhtml = $this->__xhtml;
			$this->__xhtml = substr_replace($this->__xhtml, $replace, $pos, strlen($search));
			if($this->__source) {
				$this->__source->replace($__xhtml, $this->__xhtml);
			}
		}
		return $this;
	}

	public function outer($xhtml = null, $restore = true) {
		if(is_null($xhtml)) {
			if($restore) {
				return $this->restored();
			}
			else {
				return $this->__xhtml;
			}
		}
		else {
			if(!is_null($this->__source) && !is_null($this->__xhtml)) {
				$this->__source->replace($this->__xhtml, $xhtml);
			}
			$this->__xhtml = $xhtml;
			$this->cleanup();
			return $this;
		}
	}
	
	private static function getInner($xhtml) {
		preg_match('/<\w+\b.*?>([\w\W]*)<\/\w+>/is', $xhtml, $match);
		return $match[1];
	}

	public function inner($xhtml = null) {
		if(is_null($xhtml)) {
			if($this->__xhtml) {
				return self::getInner($this->__xhtml);
			}
			trigger_error('XHTML Parse Error: A requested element has not inner text.', E_USER_NOTICE);
			return null;
		}
		else {
			$__xhtml = $this->__xhtml;
			
			$this->__xhtml = preg_replace('/(<\w+\b.*?>)([\w\W]*)(<\/\w+>)/is', '$1' . $xhtml . '$3', $this->__xhtml);
			
			if(!is_null($this->__source)) {
				$this->__source->replace($__xhtml, $this->__xhtml);
			}
			return $this;
		}
	}

	private function getParentXHtml() {
		if(!$this->__source) {
			throw new XParserException('You tried to get parent element but requested element has not parent node.');
		}
		$regex = '/<\w+[^<]*' . addcslashes($this->__xhtml, '()[]{}+*.^$-|?!,\\/') . '[^>]*<\/\w+>/';
		$count = preg_match_all($regex, $this->__source->__xhtml, $matches);
		if($count===false) {
			throw new XParserException('PCRE regex error: ' . preg_last_error());
		}
		if($count==0) {
			throw new XParserException('Parent not found.');
		}
		if($count>1) {
			throw new XParserException('Ambiguous parent.');
		}
		return $matches[0][0];
	}
	
	public function getParent() {
		$xhtml = $this->getParentXHtml();
		$source = $this->__source->__source ? $this->__source->__source : $this->__source;
		$xnode = new XNode($xhtml, $source);
		return $xnode;
	}
	
	public function getCount($select) {
		self::parseSelectorWord($select, $tag, $id, $classes);			
		
		if(!$tag) $tag = '\w+\b';
		if($classes) {
			$_classes = implode('|', $classes);
		}
		
		if($id && $classes) {
			return
				preg_match("/<" . $tag . "[^>]*([^-]\b)class\s*=\s*\"[\s\w]*\b($_classes)\b[\s\w]*\"[^>]*\bid\s*=\s*\"$id\"/is", $this->__xhtml) ||
				preg_match("/<" . $tag . "[^>]*\bid\s*=\s*\"$id\"[^>]*([^-]\b)class\s*=\s*\"[\s\w]*\b($_classes)\b[\s\w]*\"/is", $this->__xhtml);
		}
		else if($id && !$classes) {
			return preg_match("/<" . $tag . "[^>]*\bid\s*=\s*\"$id\"/is", $this->__xhtml);
		}
		else if(!$id && $classes) {
			return preg_match_all("/<" . $tag. "[^>]*([^-]\b)class\s*=\s*\"[\s\w]*\b($_classes)\b[\s\w]*\"/is", $this->__xhtml);
		}
		else if(!$id && !$classes) {
			return preg_match_all("/<$tag\b/is", $this->__xhtml);
		}
		throw new XParserException('incorrect selection: ' . $select);
	}
	
	private function getPossibleTags() {
		// todo : order the result by occurrence rate for more performance!
		preg_match_all('/<(\w+)\b/si', $this->__xhtml, $matches);
		return array_unique($matches[1]);
	}
	
	private function getElementFirst($tag = null, $attr = '\w*', $value = '\w*') {
		return $this->getElementsArray($tag, $attr, $value, true);
	}
	
	private function getElementsArray($tag = null, $attr = '\w*', $value = '\w*', $one = false, &$deep = 0) {
		$deep++;
		$max = self::$maxResultsPerStartegy; // todo: measure the correction
		$maxDeep = self::$maxRecursionInStartegy;
		if($deep>$maxDeep) {
			throw new XParserException('Recursion detected in dom tree.');
		}
		$founds = [];
		
		if(is_null($tag)) {
			foreach($this->getPossibleTags() as $tag) {
				$elems = $this->getElementsArray($tag, $attr, $value, $one, $deep);
				foreach($elems as $elem) {
					$founds[] = $elem;
				}
			}
		}
		else {
			
 			$simples = ['\!doctype', 'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
			$simple = in_array(strtolower($tag), $simples);	
			
			$singles = ['\!doctype', 'html', 'body', 'head', 'title']; // todo more?
			$single = in_array(strtolower($tag), $singles);			
			$one = $single || $attr == 'id';

			if($attr == '\w*' || $value == '\w*') {
				
				if($simple) {
					$regex = '/<' . $tag . '\b[^>]*[^>\/]*?>/is';
					if($one && preg_match($regex, $this->__html, $match) ) {
						return $match;
					}
					preg_match_all($regex, $this->__xhtml, $matches);
					$founds = $matches[0];
				}
				else {
				
					// todo : it's valid but not too propabilities do may it have to be the last check
//					$regex = '/<' . $tag . '\b[^>]*[^>\/]*?\/>/is';
//					preg_match_all($regex, $this->__xhtml, $matches);
//					$founds = $matches[0];
//					if($one && $founds) return [$founds[0]];

					$regex = '/<' . $tag . '\b[^>]*[^>\/]*?\>.*<\/' . $tag . '>/is';
					if($one && preg_match($regex, $this->__xhtml, $match)) {
						return $match;
					}
					preg_match_all($regex, $this->__xhtml, $matches);
					foreach($matches[0] as $match) {
						if(!in_array($match, $founds) && self::isValidClosure($match, true)) {
							$founds[] = $match;
						}
						else {							
							$x = new XNode(substr($match, 1));
							$more = $x->getElementsArray($tag, $attr, $value, $one, $deep);
							$founds = array_merge($founds, $more);
							if(count($founds) >= $max) {
								throw new XParserException('Too many element found, searching limit is ' . $max . ', please change your query to a more definitely selector.');
							}

							$x = new XNode(substr($match, 0, -1));
							$more = $x->getElementsArray($tag, $attr, $value, $one, $deep);
							$founds = array_merge($founds, $more);
							if(count($founds) >= $max) {
								throw new XParserException('Too many element found, searching limit is ' . $max . ', please change your query to a more definitely selector.');
							}
						}
					}
				}
			}
			
			if($simple) {
				$regex = '/<' . $tag . '\b[^>]*([^-]\b)' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?>/is';
				if($one && preg_match($regex, $this->__html, $match)) {
					return $match;
				}
				preg_match_all($regex, $this->__xhtml, $matches);
				$founds = array_merge($founds, $matches[0]);
			}
			else {

				// todo : it's valid but not too propabilities do may it have to be the last check
//				$regex = '/<' . $tag . '\b[^>]*\b' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?\/>/is';
//				preg_match_all($regex, $this->__xhtml, $matches);
//				$founds = array_merge($founds, $matches[0]);
//				if($one && $founds) return [$founds[0]];

				$regex = '/<' . $tag . '\b[^>]*([^-]\b)' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?>.*?<\/' . $tag . '>/is';
				if(preg_match($regex, $this->__xhtml, $matches)) {
					if(self::isValidClosure($matches[0], true)) {
						if(!in_array($matches[0], $founds)) {
							if($one) return [$matches[0]];
							$founds[] = $matches[0];
						}
					}
				}

				if(!$single) {

					$regex = '/<' . $tag . '\b[^>]*([^-]\b)' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?>(\R|.*?<\/' . $tag . '>).*<\/' . $tag . '>/is';
					if(preg_match($regex, $this->__xhtml, $matches)) {
						// todo : duplicated code, separate this for an other function
						if(self::isValidClosure($matches[0], true)) {
							if(!in_array($matches[0], $founds)) {
								if($one) return [$matches[0]];
								$founds[] = $matches[0];
							}
						}

						$x = new XNode(substr($matches[0], 1));
						$more = $x->getElementsArray($tag, $attr, $value, $one, $deep);
						$founds = array_merge($founds, $more);
						if(count($founds) >= $max) {
							throw new XParserException('Too many element found, searching limit is ' . $max . ', please change your query to a more definitely selector.');
						}

						$x = new XNode(substr($matches[0], 0, -1));
						$more = $x->getElementsArray($tag, $attr, $value, $one, $deep);
						$founds = array_merge($founds, $more);
						if(count($founds) >= $max) {
							throw new XParserException('Too many element found, searching limit is ' . $max . ', please change your query to a more definitely selector.');
						}

					}

				}
			
			}
			
		}

		$deep--;
		// TODO : may array_merge or array_unique function is not necessary...
		$founds = array_unique($founds);
		return $founds;
	}
	
	private static function isValidClosure($xhtml, $onlyone = false, $limit = 100) {
		$simples = '\!doctype|area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr';
		if($open = preg_match_all('/<\w+\b/i', $xhtml)) {
			$simpleTags = preg_match_all('/<(' . $simples . ')\b/i', $xhtml);
			$open -= $simpleTags;
			$close = preg_match_all('/<\/\w+>/i', $xhtml);
			if($open == $close && $open !== false) {
				
				if($onlyone) {  // todo : may dont have to check for "only one needed" if every case we'll need it
					$all = preg_match_all('/(<\w+.*?>|<\/\w+>)/', $xhtml, $matches);
					$deep = 0;
					$max = 0;
					$end = count($matches[0])-1;
					for($i=0; $i<$end; $i++) {
						if(!self::isSimpleElement($matches[0][$i])) {
							if($matches[0][$i][1] == '/') {
								$deep--;
							}
							else {
								$deep++;
								$max = $deep;
								if($max>$limit) {
									throw new XParserException('Too deep DOM tree selection, maximum deep is ' . $limit. ', please change your query to a more definitely selector.');
									//return false;
								}
							}
							if($deep==0) {
								return false;
							}
						}
					}
					if($deep!=1) {
						return false;
					}
					return $max;
				}
						
				return true;
			}
		}
		return false;
	}
	
	private static function isSimpleElement($xhtml) {
		// todo : use here and everywhere a self constant as imploded by '|' char...
		$simples = ['\!doctype', 'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
		return preg_match('/^<(' . implode('|', $simples) . ')\b/', $xhtml);
	}


	public function getElements($tag = null, $attr = '\w*', $value = '\w*') {

		$founds = $this->getElementsArray($tag, $attr, $value);
	
		return new XNodeList($founds, $this);
	}
	
	public function getElementById($id) {
		return new XNode($this->getElementFirst(null, 'id', $id)[0], $this);
	}

	private function getElementsByClassArray($class) {
		return $this->getElementsByTagAndClassArray(null, $class);
	}
	
	private function getElementsByTagAndClassArray($tag, $class) {
		$results = $this->getElementsArray($tag, 'class', '[\w\s]*\b' . $class . '\b[\w\s]*');
		return $results;
	}

	public function getElementsByClass($class) {
		return new XNodeList($this->getElementsByClassArray($class), $this);
	}	
	
	private static function parseSelectorWord($word, &$tag, &$id, &$classes = []) {
		preg_match_all('/([\.\#]?)([\w-]+)|()([\w-]+)/is', $word, $parse);
		$tag = null;
		$ids = null;
		$classes = [];
		foreach($parse[1] as $key => $type) {
			switch($type) {
				case '':
					$tag = $parse[2][$key];
				break;
				case '#':
					$id = $parse[2][$key];
				break;
				case '.':
					$classes[] = $parse[2][$key];
				break;
				default:
					throw new XParserException('Invalid CSS selector: ' . $select);
				break;
			}
		}
		
	}
	
	private function getInnerHTML(DOMNode $node ) { 
		$innerHTML= ''; 
		$children = $node->childNodes; 
		foreach ($children as $child) { 
			$innerHTML .= $child->ownerDocument->saveXML( $child ); 
		} 

		return html_entity_decode($innerHTML); 
	} 
	
	private function findViaXPath($select, $html = null) {
		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML(is_null($html) ? $this->__xhtml : $html);
		$converter = new CssSelectorConverter();
		$xpath = new DOMXPath($document);
		$elems = $xpath->query($converter->toXPath($select));
		return $elems;
	}
	
	private function getDOMNodeOuterHTML(DOMNode $elem) {		
		$tag = $elem->nodeName;
		$id = $elem->getAttribute('id');
		$class = $elem->getAttribute('class');
		$elemInnerHtml = $this->getInnerHtml($elem);
		$parentInnerHtml = $this->getInnerHTML($elem->parentNode);
		$parentXNode = new XNode($parentInnerHtml);
		$select = '';
		if($tag) {
			$select .= $tag;
		}
		if($id) {
			$select .= '#' . $id;
		}
		if($class) {
			$select .= '.' . preg_replace('/\s+/', '.', $class);
		}		
		
		$elemsCount = $parentXNode->getCount($select);
		
		if($elemsCount == 1) {
			return $parentXNode($select, 0)->outer();
		}
		if($elemsCount > 1) {
			$possibles = [];
			foreach($parentXNode($select) as $_elem) {
				if($_elem->inner() == $elemInnerHtml) {
					$possibles[] = $_elem;
				}
			}
			if(count($possibles) != 1) {
				trigger_error('DOMNode parse error: ambiguous parent-child elements. (selected ' . count($possibles) . ' elements by "' . $select . '" query) ', E_USER_WARNING);
			}
			return $possibles[0]->outer();
		}
		if($elemsCount == 0) {
			throw new XParserException('DOMNode parse error.');
		}
	}
	
	private function findViaSymfony($select, $index = null) {
		$ret = new XNodeList([], $this);
		$elems = $this->findViaXPath($select);
		
		foreach($elems as $elem) {
			
			$outer = $this->getDOMNodeOuterHTML($elem);
			$newXNode = new XNode($outer);
			$ret->addElement($newXNode);
			
		}
		return $ret;
	}

	public function find($select, $index = null) {
		$ret = new XNodeList([], $this);
		if(!preg_match('/^[\.\#\w\s\,]+$/is', $select)){
			return $this->findViaSymfony($select, $index);
		}
		$selects = preg_split('/\s*,\s*/', $select);
		foreach($selects as $select) {
			$words = preg_split('/\s+/', trim($select));
			$founds = [];
			foreach($words as $wkey => $word) {
				self::parseSelectorWord($word, $tag, $id, $classes);

				if(!$id && !$classes) {
					$founds = $this->getElementsArray($tag);
				}
				else if($id && !$classes) {
					//foreach($ids as $id) {
						$founds = array_merge($founds, $this->getElementsArray($tag, 'id', $id));
					//}
				}
				else if(!$id && $classes) {					
					foreach($classes as $class) {
						$foundsByClass[$class] = $this->getElementsByTagAndClassArray($tag, $class);
					}				
					if(count($foundsByClass)>1) {
						$founds = array_merge($founds, call_user_func_array('array_intersect', $foundsByClass));
					}
					else {
						$founds = array_merge($founds, $foundsByClass[$class]);
					}
					
				}
				else if($id && $classes) {				
					$foundsById = [];
					//foreach($ids as $id) {
						$foundsById = array_merge($foundsById, $this->getElementsArray($tag, 'id', $id));
					//}
					$foundsByClass = [];
					foreach($classes as $class) {				
						$foundsByClass = array_merge($foundsByClass, $this->getElementsByClassArray($class));
					}
					$founds = array_intersect($foundsById, $foundsByClass);
				}
				else {
					// hmmm.. interesting.
					throw new XParserException('?');
				}
				
				if(!$founds) {
					break;
				}
				
				if(isset($words[$wkey+1])) {
					$rest = implode(' ', array_slice($words, $wkey+1));
					foreach($founds as $found) {
						$inner = self::getInner($found);
						$innerElement = new XNode($inner, $this);
						$restElements = $innerElement->find($rest);
						foreach($restElements as $restElement) {
							$ret->addElement($restElement);
						}
					}
					return $ret;
				}
				
			}
			$ret->addElementsArray($founds, $this);
		}
		if(!is_null($index)) {
			return $ret->getElement($index);
		}
		return $ret;
	}
	
	public function attr($attr, $value = null) {
		$regex = '/(^[^>]*\b' . $attr . '\s*=\s*)"(.*?)"/is';
		if(is_null($value)) {
			preg_match($regex, $this->__xhtml, $matches);
			return isset($matches[2]) ? $matches[2] : null;
		}
		else {
			$xhtml = $this->__xhtml;
			
			$this->__xhtml = preg_replace($regex, '$1"' . $value . '"', $this->__xhtml, 1);
			
			if(!is_null($this->__source)) {
				$this->__source->replace($xhtml, $this->__xhtml);
			}			
			return $this;
		}		
	}
	
	private function cleanup($removeComments = false) {
		if(is_null($this->__source)) {
			$outer = $this->__xhtml;
			$regex = '/<\!--(.*?)-->/s';
			if($removeComments) {
				$outer = preg_replace($regex, '', $outer);
			}
			else {
				preg_match_all($regex, $outer, $matches);
				$this->__temps = $matches[0];
			}

			//$this->__temps = array_merge($this->__temps, $this->getElementsArray('script'), $this->getElementsArray('style'));

			foreach($this->__temps as $key => $temp) {
				$outer = str_replace($temp, '[XPARSER TEMP #' . $key . ']', $outer);
			}

			$this->__xhtml = $outer;
		}
		return $this;
	}
	
	private function restored() {
		$__xhtml = $this->__xhtml;
		if(is_null($this->__source)) {
			foreach($this->__temps as $key => $temp) {
				$__xhtml = preg_replace('/\[XPARSER TEMP \#' . $key . '\]/', $temp, $__xhtml);
			}
		}
		return $__xhtml;
	}
	
	public function __toString() {
		return $this->outer() . '';
	}
	
	public function __set($name, $value) {
		if(!property_exists($this, $name)) {
			$this->attr($name, $value);
		}
		else {
			$this->$name = $value;
		}
	}
	
	public function __get($name) {
		if(!property_exists($this, $name)) {
			$value = $this->attr($name);
		}
		else {
			$value = $this->$name;
		}
		return $value;
	}

	public function __invoke($select, $indexOrFunc = null) {
		if(is_null($indexOrFunc) || is_numeric($indexOrFunc)) {
			return $this->find($select, $indexOrFunc);
		}
		$this->each($select, $indexOrFunc);
	}
	
	public function validate() {
		return self::isValidClosure($this->__xhtml);
	}
	
	public function each($select, callable $callback) {
		$elems = $this->find($select);
		foreach($elems as $key => $elem) {
			$callback($elems->getElement($key));
		}
	}

}
