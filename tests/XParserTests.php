<?php

/*
 * The MIT License
 *
 * Copyright 2015 Gyula Madarasz <gyula.madarasz at gmail.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace gymadarasz\xparser\tests;

use gymadarasz\minitest\MiniTestAbstract;
use gymadarasz\xparser\XNode;

/**
 * Description of XParserTests
 *
 * @author Gyula Madarasz <gyula.madarasz at gmail.com>
 */
class XParserTests extends MiniTestAbstract {
	
	public function run() {
		$this->start('test5');
		$this->start('test4');
		$this->start('test3');
		$this->start('test2');
		$this->start('mainTest');
	}
	
	public function test5() {
		
		$x = new XNode('<div class="hello2">
							  2
							</div>');
		$divs = $x->find('div.hello2')->getElements();
		$result = count($divs);
		$good = 1;
		$this->equ($result, $good, 'pre-test #1');
		
		
		$x = new XNode('
						  <div class="hello2">
							
						</div>');
		$divs = $x->find('div.hello2')->getElements();
		$result = count($divs);
		$good = 1;
		$this->equ($result, $good, 'pre-test #2');
		
		
		$x = new XNode('
						  <div class="hello2">
							<div class="hello2">
							  2
							</div>
						</div>');
		$divs = $x->find('div.hello2')->getElements();
		$result = count($divs);
		$good = 2;
		$this->equ($result, $good, 'pre-test #3' . PHP_EOL . htmlentities(print_r($divs, true)));
		
		
		$x = new XNode('<div class="hello2">
						  <span>1</span>
						</div>
						  <div class="hello2">
							
						</div>');
		$divs = $x->find('div.hello2')->getElements();
		$result = count($divs);
		$good = 2;
		$this->equ($result, $good, 'pre-test #4' . PHP_EOL . htmlentities(print_r($divs, true)));
		
		
		
		$x = new XNode('<div class="hello2">
						  <span>1</span>
						</div>
						  <div class="hello2">
							<div class="hello2">
							  2
							</div>
						</div>');
		$divs = $x->find('div.hello2')->getElements();
		$result = count($divs);
		$good = 3;
		$this->equ($result, $good, 'pre-test #5');
		

		
		
		$x = new XNode('<div class="hello2">
							<div class="hello2">
							  2
							</div>
						</div>
						<div class="hello2">
						  <span>1</span>
						</div>');
		$divs = $x->find('div.hello2')->getElements();
		$result = count($divs);
		$good = 3;
		$this->equ($result, $good, 'pre-test #6' . PHP_EOL . htmlentities(print_r($divs, true)));		
		
		
		$x = new XNode(
		'<html>
			<head>
				<title>Test page</title>
			</head>
			<body>
						<div class="hello2">
						  <span>1</span>
						</div>
						  <div class="hello2">
							<div class="hello2">
							  2
							</div>
						</div>
			</body>
		</html>');

		$spans = $x->find('div.hello2')->getElements();
		$result = count($spans);
		$good = 3;
		$this->equ($result, $good, '@debug it...');
	}
	
	public function test4() {
		$x = new XNode('
                  <div class="hello2"></div>
                ');
		$divs = $x->find('div');
		$this->equ(count($divs), 1);
		
		
		$x = new XNode(
'<html>
    <head>
        <title>Test page</title>
    </head>
    <body>
                <div id="hello1" class="message">
                  <div class="hello2"></div>
                </div>
    </body>
</html>');


		$good = '<div class="hello2"></div>';
		$inner = $x->find('#hello1 div')->outer();		

		$this->equ($good, $inner);
		
		$x = new XNode(
'<html>
    <head>
        <title>Test page</title>
    </head>
    <body>
                <div id="hello1" class="message">
                  <div class="hello2"></div>
                </div>
    </body>
</html>');		
		$hello1OuterGood = '<div id="hello1" class="message">
                  <div class="hello2"></div>
                </div>';
		$hello1InnerTrimmedGood = '<div class="hello2"></div>';
		$hello2OuterGood = $hello1InnerTrimmedGood;
		
		$hello1Outer = $x->find('#hello1')->outer();
		$hello1InnerTrimmed = trim($x->find('#hello1')->inner());
		$hello1DivOuter = $x->find('#hello1 div')->outer();
		$hello2Outer = $x->find('.hello2')->outer();
		
		$this->equ($hello1OuterGood, $hello1Outer);
		$this->equ($hello1InnerTrimmedGood, $hello1InnerTrimmed);
		$this->equ($hello2OuterGood, $hello2Outer);
		$this->equ($hello2Outer, $hello1InnerTrimmed);
		$this->equ($hello1DivOuter, $hello2Outer);
	}
	
	public function test3() {
$x = new XNode(
'<html>
    <head>
        <title>Test page</title>
    </head>
    <body>
                <div id="hello1" class="message">
                    <div>
                      <span>
                        ho
                      </span>
                    </div>
                </div>
    </body>
</html>');

		$good = '
                        ho
                      ';
        $inner = $x->find('#hello1 > span')->inner();

		$this->equ($good, $inner);
	}
	
	public function test2() {
		include 'vendor/autoload.php';
		$x = new XNode(
				'<html>
<head>
<title>Test page</title>
</head>
<body>
<div id="hello1" class="message">
<div>
ho
</div>
</div>
</body>
</html>');
		
		$good = '
<div>
ho
</div>
';

		$inner = $x->find('#hello1')->inner();
		
		$this->equ($good, $inner);
	}
	
	public function mainTest() {
		$tpl = new XNode(
'<html>
	<head>
		<title>Test page</title>
	</head>
	<body>
		<h1>Lorem ipsum</h1>		

		<div />

		<div>asd</div>

		<div id= "hello01" asdasdw />
<!--
		<div id= "hello02" asdasdw class="message" asdasd />
-->
		<hr>

		<div id="hello1" class="message"> Hello World! </div>

		<hr>

		<div id="hello2" class="message selected"> before <span>Hello World!</span> after </div>

		<hr>

		<div id="hello3" class="message"> before <div>Hello <span>here</span> World!</div> after </div>

		<hr>
		
		<input type="text" id="myinput1" value="my value here..">

	</body>
</html>');

		$before = $tpl->find('div#hello2.selected.message, div#hello1')->inner();
		$tpl->find('div#hello2.selected.message, div#hello1')->inner('yupeeee!');
		$after = $tpl->find('div#hello2.selected.message, div#hello1')->inner();
		$this->equ($before, ' Hello World! ');
		$this->equ($after, 'yupeeee!');

		$before = $tpl->find('html body input')->attr('value');
		$tpl->find('input')->attr('value', 'elembe!');
		$after = $tpl->find('html body input')->attr('value');
		$this->equ($before, 'my value here..');
		$this->equ($after, 'elembe!');
		
		$before = $tpl->outer();
		$this->equ(count($tpl('#hello02')->getElements()), 0);
		$after = $tpl->outer();
		$this->equ($before, $after);
		
	}
	
}
