<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Validator Class
 * Tests all validation methods including security rules
 */
class ValidatorTest extends TestCase
{
    /**
     * Test email validation
     */
    public function testEmailValidation()
    {
        // Valid emails
        $this->assertTrue(Validator::email('test@example.com'));
        $this->assertTrue(Validator::email('user.name@domain.co.uk'));
        $this->assertTrue(Validator::email('test+tag@example.com'));
        
        // Invalid emails
        $this->assertFalse(Validator::email('invalid.email'));
        $this->assertFalse(Validator::email('@example.com'));
        $this->assertFalse(Validator::email('test@'));
        $this->assertFalse(Validator::email('test @example.com'));
    }
    
    /**
     * Test required field validation
     */
    public function testRequiredValidation()
    {
        // Valid values
        $this->assertTrue(Validator::required('test'));
        $this->assertTrue(Validator::required('0'));
        $this->assertTrue(Validator::required([1, 2, 3]));
        
        // Invalid values
        $this->assertFalse(Validator::required(''));
        $this->assertFalse(Validator::required('   '));
        $this->assertFalse(Validator::required(null));
        $this->assertFalse(Validator::required([]));
    }
    
    /**
     * Test length validations
     */
    public function testLengthValidation()
    {
        // Min length
        $this->assertTrue(Validator::minLength('hello', 5));
        $this->assertTrue(Validator::minLength('hello', 3));
        $this->assertFalse(Validator::minLength('hi', 5));
        
        // Max length
        $this->assertTrue(Validator::maxLength('hello', 5));
        $this->assertTrue(Validator::maxLength('hello', 10));
        $this->assertFalse(Validator::maxLength('hello world', 5));
    }
    
    /**
     * Test numeric validations
     */
    public function testNumericValidation()
    {
        // Numeric
        $this->assertTrue(Validator::numeric(123));
        $this->assertTrue(Validator::numeric('123'));
        $this->assertTrue(Validator::numeric(123.45));
        $this->assertTrue(Validator::numeric('123.45'));
        $this->assertFalse(Validator::numeric('abc'));
        
        // Positive
        $this->assertTrue(Validator::positive(1));
        $this->assertTrue(Validator::positive(0.1));
        $this->assertFalse(Validator::positive(0));
        $this->assertFalse(Validator::positive(-1));
        
        // Integer
        $this->assertTrue(Validator::integer(123));
        $this->assertTrue(Validator::integer('123'));
        $this->assertFalse(Validator::integer(123.45));
        $this->assertFalse(Validator::integer('123.45'));
    }
    
    /**
     * Test URL validation
     */
    public function testUrlValidation()
    {
        // Valid URLs
        $this->assertTrue(Validator::url('https://example.com'));
        $this->assertTrue(Validator::url('http://example.com/path?query=1'));
        $this->assertTrue(Validator::url('ftp://files.example.com'));
        
        // Invalid URLs
        $this->assertFalse(Validator::url('not-a-url'));
        $this->assertFalse(Validator::url('example.com'));
        $this->assertFalse(Validator::url('//example.com'));
    }
    
    /**
     * Test phone validation
     */
    public function testPhoneValidation()
    {
        // Valid phones
        $this->assertTrue(Validator::phone('+49 123 456789'));
        $this->assertTrue(Validator::phone('(123) 456-7890'));
        $this->assertTrue(Validator::phone('123-456-7890'));
        
        // Invalid phones
        $this->assertFalse(Validator::phone('abc-def-ghij'));
        $this->assertFalse(Validator::phone('phone number'));
    }
    
    /**
     * Test in array validation
     */
    public function testInValidation()
    {
        $allowed = ['apple', 'banana', 'orange'];
        
        $this->assertTrue(Validator::in('apple', $allowed));
        $this->assertTrue(Validator::in('banana', $allowed));
        $this->assertFalse(Validator::in('grape', $allowed));
        $this->assertFalse(Validator::in('Apple', $allowed)); // Case sensitive
    }
    
    /**
     * Test strong password validation
     * SECURITY TEST: Password complexity requirements
     */
    public function testStrongPasswordValidation()
    {
        // Valid strong passwords
        $this->assertTrue(Validator::strongPassword('Test123!'));
        $this->assertTrue(Validator::strongPassword('MyP@ssw0rd'));
        $this->assertTrue(Validator::strongPassword('Secure#Pass123'));
        
        // Weak passwords (should fail)
        $this->assertFalse(Validator::strongPassword('short')); // Too short
        $this->assertFalse(Validator::strongPassword('alllowercase123!')); // No uppercase
        $this->assertFalse(Validator::strongPassword('ALLUPPERCASE123!')); // No lowercase
        $this->assertFalse(Validator::strongPassword('NoNumbers!')); // No numbers
        $this->assertFalse(Validator::strongPassword('NoSpecial123')); // No special chars
    }
    
    /**
     * Test batch validation
     */
    public function testBatchValidation()
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'Test123!',
            'name' => 'John Doe',
            'age' => '25'
        ];
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8|strong_password',
            'name' => 'required|min:3|max:50',
            'age' => 'required|numeric|positive'
        ];
        
        $errors = Validator::validate($data, $rules);
        $this->assertEmpty($errors, 'Valid data should not produce errors');
    }
    
    /**
     * Test batch validation with errors
     */
    public function testBatchValidationWithErrors()
    {
        $data = [
            'email' => 'invalid-email',
            'password' => 'weak',
            'name' => '',
            'age' => '-5'
        ];
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8|strong_password',
            'name' => 'required|min:3',
            'age' => 'required|positive'
        ];
        
        $errors = Validator::validate($data, $rules);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('age', $errors);
    }
    
    /**
     * SECURITY TEST: SQL Injection attempt in validation
     */
    public function testSqlInjectionInValidation()
    {
        $maliciousInput = "'; DROP TABLE users; --";
        
        // Validation should work normally, sanitization happens elsewhere
        $this->assertTrue(Validator::required($maliciousInput));
        $this->assertTrue(Validator::minLength($maliciousInput, 5));
    }
    
    /**
     * SECURITY TEST: XSS attempt in validation
     */
    public function testXssAttemptInValidation()
    {
        $xssPayload = "<script>alert('XSS')</script>";
        
        // Validation should work normally, sanitization happens elsewhere
        $this->assertTrue(Validator::required($xssPayload));
        $this->assertFalse(Validator::email($xssPayload));
    }
}

