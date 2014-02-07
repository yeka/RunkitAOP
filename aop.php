<?php

class ABC
{
    // Original hello implementation
    public function hello()
    {
        echo "Hello";
    }

}

class BAC extends ABC
{
    public function hello()
    {
        return parent::hello().' Kitty';
    }
}


class ABC_Aspect
{
    public $pointcuts = [
        ['beforeHello', 'before', ['ABC', 'hello']],
        ['afterHello', 'after', ['ABC', 'hello']],
    ];

    public static function beforeHello($obj)
    {
        echo "A ";
    }

    public static function afterHello($obj)
    {
        echo " for me!";
    }
}

class RunkitAOP
{
    private static $instance;

    public static function create()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    protected $configs = [];

    public function register($aspect)
    {
        foreach ($aspect->pointcuts as $pointcut) {
            list($method, $position, $original) = $pointcut;
            list($original_class, $original_method) = $original;
            if (!isset($this->configs[$original_class][$original_method])) {
                $new_name = $original_method.'_'.sha1($original_method);
                runkit_method_rename($original_class, $original_method, $new_name);
                runkit_method_add(
                    $original_class,
                    $original_method,
                    '',
                    '
                    $aop_unique_variable = RunkitAOP::create();
                    return $aop_unique_variable->go($this, func_get_args(), "'.$original_class.'", "'.$original_method.'");
                    '
                );
            }
            $this->configs[$original_class][$original_method][$position][] = [$aspect, $method];
        }
    }

    public function go($obj, $args, $original_class, $original_method)
    {
        foreach ($this->configs[$original_class][$original_method]['before'] as $advice) {
            $result = call_user_func_array($advice, [$obj, $args]);
            if (!is_null($result)) {
                return $result;
            }
        }

        $new_name = $original_method.'_'.sha1($original_method);
        $original_return = call_user_func_array([$obj, $new_name], $args);

        foreach ($this->configs[$original_class][$original_method]['after'] as $advice) {
            $result = call_user_func_array($advice, [$obj, $args]);
            if (!is_null($result)) {
                return $result;
            }
        }
        return $original_return;
    }

    public function run(array $advice, $obj)
    {
        call_user_func_array([$this->aspects[$advice[0]], $advice[1]], [$obj]);
    }
}

if (!extension_loaded('runkit')) {
    die('You need Runkit extension!');
}
$aop = RunkitAOP::create();
$aop->register(new ABC_Aspect());

$abc = new BAC();
echo $abc->hello();