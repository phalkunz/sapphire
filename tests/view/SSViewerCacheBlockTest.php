<?php

// Not actually a data object, we just want a ViewableData object that's just for us
class SSViewerCacheBlockTest_Model extends DataObject implements TestOnly {
	
	public function Test($arg = null) {
		return $this;
	}
	
	public function Foo() {
		return 'Bar';
	}

	public function True() {
		return true;
	}

	public function False() {
		return false;
	}
}

class SSViewerCacheBlockTest extends SapphireTest {
	
	protected $extraDataObjects = array('SSViewerCacheBlockTest_Model');
	
	protected $data = null;
	
	protected function _reset($cacheOn = true) {
		$this->data = new SSViewerCacheBlockTest_Model();
		
		SS_Cache::factory('cacheblock')->clean();
		SS_Cache::set_cache_lifetime('cacheblock', $cacheOn ? 600 : -1);
	}
	
	protected function _runtemplate($template, $data = null) {
		if ($data === null) $data = $this->data;
		if (is_array($data)) $data = $this->data->customise($data);
		
		$viewer = SSViewer::fromString($template);
		return $viewer->process($data);
	}
	
	public function testParsing() {

		// ** Trivial checks **

		// Make sure an empty cached block parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% cached %><% end_cached %>'), '');

		// Make sure an empty cacheblock block parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% cacheblock %><% end_cacheblock %>'), '');

		// Make sure an empty uncached block parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% uncached %><% end_uncached %>'), '');

		// ** Argument checks **

		// Make sure a simple cacheblock parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% cached %>Yay<% end_cached %>'), 'Yay');

		// Make sure a moderately complicated cacheblock parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% cached \'block\', Foo, "jumping" %>Yay<% end_cached %>'), 'Yay');
		
		// Make sure a complicated cacheblock parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate(
			'<% cached \'block\', Foo, Test.Test(4).Test(jumping).Foo %>Yay<% end_cached %>'), 'Yay');

		// ** Conditional Checks **

		// Make sure a cacheblock with a simple conditional parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% cached if true %>Yay<% end_cached %>'), 'Yay');

		// Make sure a cacheblock with a complex conditional parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate('<% cached if Test.Test(yank).Foo %>Yay<% end_cached %>'), 'Yay');

		// Make sure a cacheblock with a complex conditional and arguments parses
		$this->_reset();
		$this->assertEquals($this->_runtemplate(
			'<% cached Foo, Test.Test(4).Test(jumping).Foo if Test.Test(yank).Foo %>Yay<% end_cached %>'), 'Yay');
	}

	/**
	 * Test that cacheblocks actually cache
	 */
	public function testBlocksCache() {
		// First, run twice without caching, to prove we get two different values
		$this->_reset(false);
				
		$this->assertEquals($this->_runtemplate('<% cached %>$Foo<% end_cached %>', array('Foo' => 1)), '1');
		$this->assertEquals($this->_runtemplate('<% cached %>$Foo<% end_cached %>', array('Foo' => 2)), '2');
		
		// Then twice with caching, should get same result each time
		$this->_reset(true);
				
		$this->assertEquals($this->_runtemplate('<% cached %>$Foo<% end_cached %>', array('Foo' => 1)), '1');
		$this->assertEquals($this->_runtemplate('<% cached %>$Foo<% end_cached %>', array('Foo' => 2)), '1');
	}

	/**
	 * Test that cacheblocks conditionally cache with if
	 */
	public function testBlocksConditionallyCacheWithIf() {
		// First, run twice with caching
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate('<% cached if True %>$Foo<% end_cached %>', array('Foo' => 1)), '1');
		$this->assertEquals($this->_runtemplate('<% cached if True %>$Foo<% end_cached %>', array('Foo' => 2)), '1');

		// Then twice without caching
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate('<% cached if False %>$Foo<% end_cached %>', array('Foo' => 1)), '1');
		$this->assertEquals($this->_runtemplate('<% cached if False %>$Foo<% end_cached %>', array('Foo' => 2)), '2');

		// Then once cached, once not (and the opposite)
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate(
			'<% cached if Cache %>$Foo<% end_cached %>', array('Foo' => 1, 'Cache' => true )), '1');
		$this->assertEquals($this->_runtemplate(
			'<% cached if Cache %>$Foo<% end_cached %>', array('Foo' => 2, 'Cache' => false)), '2');

		$this->_reset(true);

		$this->assertEquals($this->_runtemplate(
			'<% cached if Cache %>$Foo<% end_cached %>', array('Foo' => 1, 'Cache' => false)), '1');
		$this->assertEquals($this->_runtemplate(
			'<% cached if Cache %>$Foo<% end_cached %>', array('Foo' => 2, 'Cache' => true )), '2');
	}

	/**
	 * Test that cacheblocks conditionally cache with unless
	 */
	public function testBlocksConditionallyCacheWithUnless() {
		// First, run twice with caching
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate(
			'<% cached unless False %>$Foo<% end_cached %>', array('Foo' => 1)), '1');
		$this->assertEquals($this->_runtemplate(
			'<% cached unless False %>$Foo<% end_cached %>', array('Foo' => 2)), '1');

		// Then twice without caching
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate(
			'<% cached unless True %>$Foo<% end_cached %>', array('Foo' => 1)), '1');
		$this->assertEquals($this->_runtemplate(
			'<% cached unless True %>$Foo<% end_cached %>', array('Foo' => 2)), '2');
	}

	/**
	 * Test that nested uncached blocks work
	 */
	public function testNestedUncachedBlocks() {
		// First, run twice with caching, to prove we get the same result back normally
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate(
			'<% cached %> A $Foo B <% end_cached %>', array('Foo' => 1)), ' A 1 B ');
		$this->assertEquals($this->_runtemplate(
			'<% cached %> A $Foo B <% end_cached %>', array('Foo' => 2)), ' A 1 B ');

		// Then add uncached to the nested block
		$this->_reset(true);

		$this->assertEquals($this->_runtemplate(
			'<% cached %> A <% uncached %>$Foo<% end_uncached %> B <% end_cached %>', array('Foo' => 1)), ' A 1 B ');
		$this->assertEquals($this->_runtemplate(
			'<% cached %> A <% uncached %>$Foo<% end_uncached %> B <% end_cached %>', array('Foo' => 2)), ' A 2 B ');
	}

	/**
	 * Test that nested blocks with different keys works
	 */
	public function testNestedBlocks() {
		$this->_reset(true);

		$template = '<% cached Foo %> $Fooa <% cached Bar %>$Bara<% end_cached %> $Foob <% end_cached %>';

		// Do it the first time to load the cache
		$this->assertEquals($this->_runtemplate($template, 
			array('Foo' => 1, 'Fooa' => 1, 'Foob' => 3, 'Bar' => 1, 'Bara' => 2)), ' 1 2 3 ');

		// Do it again, the input values are ignored as the cache is hit for both elements
		$this->assertEquals($this->_runtemplate($template, 
			array('Foo' => 1, 'Fooa' => 9, 'Foob' => 9, 'Bar' => 1, 'Bara' => 9)), ' 1 2 3 ');

		// Do it again with a new key for Bar, Bara is picked up, Fooa and Foob are not
		$this->assertEquals($this->_runtemplate($template, 
			array('Foo' => 1, 'Fooa' => 9, 'Foob' => 9, 'Bar' => 2, 'Bara' => 9)), ' 1 9 3 ');

		// Do it again with a new key for Foo, Fooa and Foob are picked up, Bara are not
		$this->assertEquals($this->_runtemplate($template, 
			array('Foo' => 2, 'Fooa' => 9, 'Foob' => 9, 'Bar' => 2, 'Bara' => 1)), ' 9 9 9 ');
	}

	public function testNoErrorMessageForControlWithinCached() {
		$this->_reset(true);
		$this->_runtemplate('<% cached %><% control Foo %>$Bar<% end_control %><% end_cached %>');
	}
	
	/**
     * @expectedException SSTemplateParseException
     */
	public function testErrorMessageForCachedWithinControlWithinCached() {
		$this->_reset(true);
		$this->_runtemplate(
			'<% cached %><% control Foo %><% cached %>$Bar<% end_cached %><% end_control %><% end_cached %>');
	}

	public function testNoErrorMessageForCachedWithinControlWithinUncached() {
		$this->_reset(true);
		$this->_runtemplate(
			'<% uncached %><% control Foo %><% cached %>$Bar<% end_cached %><% end_control %><% end_uncached %>');
	}

	/**
     * @expectedException SSTemplateParseException
     */
	public function testErrorMessageForCachedWithinIf() {
		$this->_reset(true);
		$this->_runtemplate('<% cached %><% if Foo %><% cached %>$Bar<% end_cached %><% end_if %><% end_cached %>');
	}

	/**
     * @expectedException SSTemplateParseException
     */
	public function testErrorMessageForInvalidConditional() {
		$this->_reset(true);
		$this->_runtemplate('<% cached Foo if %>$Bar<% end_cached %>');
	}

}
