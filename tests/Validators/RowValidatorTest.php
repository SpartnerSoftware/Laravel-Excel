<?php

namespace Maatwebsite\Excel\Tests\Validators;

use Illuminate\Contracts\Validation\Factory;
use Maatwebsite\Excel\Tests\TestCase;
use Maatwebsite\Excel\Validators\RowValidator;

class RowValidatorTest extends TestCase
{
    /**
     * The RowValidator instance.
     */
    protected $validator;

    /**
     * Set up the test.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->validator = new RowValidator(app(Factory::class));
    }

    public function test_format_rule_with_array_input()
    {
        $rules = ['rule1', 'rule2'];

        $result = $this->callPrivateMethod('formatRule', [$rules]);

        $this->assertEquals($rules, $result);
    }

    public function test_format_rule_with_object_input()
    {
        $rule = new \stdClass();

        $result = $this->callPrivateMethod('formatRule', [$rule]);

        $this->assertEquals($rule, $result);
    }

    public function test_format_rule_with_callable_input()
    {
        $rule = function () {
            return 'callable';
        };

        $result = $this->callPrivateMethod('formatRule', [$rule]);

        $this->assertEquals($rule, $result);
    }

    public function test_format_rule_with_required_without_all()
    {
        $rule = 'required_without_all:first_name,last_name';

        $result = $this->callPrivateMethod('formatRule', [$rule]);

        $this->assertEquals('required_without_all:*.first_name,*.last_name', $result);
    }

    public function test_format_rule_with_required_without()
    {
        $rule = 'required_without:first_name';

        $result = $this->callPrivateMethod('formatRule', [$rule]);

        $this->assertEquals('required_without:*.first_name', $result);
    }

    public function test_format_rule_with_string_input_not_matching_pattern()
    {
        $rule = 'rule';

        $result = $this->callPrivateMethod('formatRule', [$rule]);

        $this->assertEquals($rule, $result);
    }

    /**
     * Call a private function.
     *
     * @param  string  $name
     * @param  array  $args
     * @return mixed
     */
    public function callPrivateMethod(string $name, array $args)
    {
        $method = new \ReflectionMethod(RowValidator::class, $name);
        $method->setAccessible(true);

        return $method->invokeArgs($this->validator, $args);
    }
}
