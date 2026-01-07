<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Sanitizer Class
 * SECURITY CRITICAL: Tests XSS prevention and data sanitization
 */
class SanitizerTest extends TestCase
{
    /**
     * SECURITY TEST: XSS prevention in string sanitization
     */
    public function testStringXssPrevention()
    {
        // Test basic XSS payloads
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert(1)>',
            '<svg onload=alert(1)>',
            'javascript:alert(1)',
            '<iframe src="javascript:alert(1)">',
        ];
        
        foreach ($xssPayloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            
            // Should not contain script tags or event handlers
            $this->assertStringNotContainsString('<script', $sanitized);
            $this->assertStringNotContainsString('javascript:', $sanitized);
            $this->assertStringNotContainsString('onerror=', $sanitized);
            $this->assertStringNotContainsString('onload=', $sanitized);
        }
    }
    
    /**
     * Test string sanitization removes HTML tags
     */
    public function testStringRemovesHtmlTags()
    {
        $this->assertEquals('Hello World', Sanitizer::string('<p>Hello World</p>'));
        $this->assertEquals('Bold Text', Sanitizer::string('<strong>Bold Text</strong>'));
        $this->assertEquals('Link', Sanitizer::string('<a href="#">Link</a>'));
    }
    
    /**
     * Test string sanitization encodes special characters
     */
    public function testStringEncodesSpecialCharacters()
    {
        $input = 'Test & <Test> "Test"';
        $sanitized = Sanitizer::string($input);
        
        // Should encode HTML entities
        $this->assertStringContainsString('&amp;', $sanitized);
        $this->assertStringNotContainsString('<', $sanitized);
        $this->assertStringNotContainsString('>', $sanitized);
    }
    
    /**
     * Test string trims whitespace
     */
    public function testStringTrimsWhitespace()
    {
        $this->assertEquals('test', Sanitizer::string('  test  '));
        $this->assertEquals('test', Sanitizer::string("\n\ttest\n\t"));
    }
    
    /**
     * Test email sanitization
     */
    public function testEmailSanitization()
    {
        // Valid emails should be cleaned
        $this->assertEquals('test@example.com', Sanitizer::email('test@example.com'));
        $this->assertEquals('test@example.com', Sanitizer::email('  test@example.com  '));
        
        // Invalid characters should be removed
        $dirty = 'test()@example.com';
        $clean = Sanitizer::email($dirty);
        $this->assertEquals('test@example.com', $clean);
    }
    
    /**
     * Test integer sanitization
     */
    public function testIntegerSanitization()
    {
        $this->assertEquals('123', Sanitizer::integer('123'));
        $this->assertEquals('123', Sanitizer::integer('abc123'));
        $this->assertEquals('-456', Sanitizer::integer('-456'));
        $this->assertEquals('', Sanitizer::integer('abc'));
    }
    
    /**
     * Test float sanitization
     */
    public function testFloatSanitization()
    {
        $this->assertEquals('123.45', Sanitizer::float('123.45'));
        $this->assertEquals('123.45', Sanitizer::float('abc123.45'));
        $this->assertEquals('-456.78', Sanitizer::float('-456.78'));
    }
    
    /**
     * Test URL sanitization
     */
    public function testUrlSanitization()
    {
        $this->assertEquals('https://example.com', Sanitizer::url('https://example.com'));
        $this->assertEquals('https://example.com/path', Sanitizer::url('https://example.com/path'));
        
        // Should remove dangerous characters
        $this->assertStringNotContainsString('<', Sanitizer::url('https://example.com/<script>'));
    }
    
    /**
     * Test array sanitization
     */
    public function testArraySanitization()
    {
        $data = [
            'name' => '<script>alert(1)</script>John Doe',
            'email' => '  test@example.com  ',
            'age' => '25abc',
            'price' => '99.99xyz'
        ];
        
        $rules = [
            'name' => 'string',
            'email' => 'email',
            'age' => 'integer',
            'price' => 'float'
        ];
        
        $sanitized = Sanitizer::array($data, $rules);
        
        // Check sanitization
        $this->assertStringNotContainsString('<script>', $sanitized['name']);
        $this->assertEquals('test@example.com', $sanitized['email']);
        $this->assertEquals('25', $sanitized['age']);
        $this->assertEquals('99.99', $sanitized['price']);
    }
    
    /**
     * Test SQL LIKE sanitization
     */
    public function testSqlLikeSanitization()
    {
        // Should escape SQL LIKE wildcards
        $this->assertEquals('test\\%value', Sanitizer::likeSql('test%value'));
        $this->assertEquals('test\\_value', Sanitizer::likeSql('test_value'));
        $this->assertEquals('test\\%\\_value\\%', Sanitizer::likeSql('test%_value%'));
    }
    
    /**
     * SECURITY TEST: SQL Injection prevention
     */
    public function testSqlInjectionPrevention()
    {
        $sqlInjectionPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "admin'--",
            "1' UNION SELECT * FROM users--"
        ];
        
        foreach ($sqlInjectionPayloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            
            // Characters should be encoded
            $this->assertNotEquals($payload, $sanitized);
        }
    }
    
    /**
     * SECURITY TEST: Path traversal prevention
     */
    public function testPathTraversalPrevention()
    {
        $pathTraversalPayloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            '..\\..\\..'
        ];
        
        foreach ($pathTraversalPayloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            
            // Dots and slashes should be encoded or removed
            $this->assertNotEquals($payload, $sanitized);
        }
    }
    
    /**
     * SECURITY TEST: Null byte injection
     */
    public function testNullByteInjectionPrevention()
    {
        $input = "test\0.txt";
        $sanitized = Sanitizer::string($input);
        
        // Null bytes should be removed/encoded
        $this->assertStringNotContainsString("\0", $sanitized);
    }
    
    /**
     * SECURITY TEST: Unicode attacks
     */
    public function testUnicodeAttackPrevention()
    {
        // Test various unicode attack vectors
        $unicodePayloads = [
            "\u003cscript\u003ealert(1)\u003c/script\u003e",
            "＜script＞alert(1)＜/script＞", // Full-width characters
        ];
        
        foreach ($unicodePayloads as $payload) {
            $sanitized = Sanitizer::string($payload);
            
            // Should not contain script tags
            $this->assertStringNotContainsString('script', strtolower($sanitized));
        }
    }
    
    /**
     * Test handling of normal safe inputs
     */
    public function testSafeInputsPreserved()
    {
        $safeInputs = [
            'John Doe',
            'Product Name 123',
            'Description with normal text.',
            '1234567890'
        ];
        
        foreach ($safeInputs as $input) {
            $sanitized = Sanitizer::string($input);
            $this->assertEquals($input, $sanitized);
        }
    }
}

