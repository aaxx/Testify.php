<?php

/**
 * Testify - a micro unit testing framework
 *
 * This is the main class of the framework. Use it like this:
 *
 * @see        https://github.com/marco-fiset/Testify.php
 *
 * @version    0.4.1
 * @author     Martin Angelov
 * @author     Marc-Olivier Fiset
 * @author     Fabien Salathe
 * @link       marco
 * @throws     TestifyException
 * @license    GPL
 */

class Testify {

    private $tests = array();
    private $stack = array();
    private $fileCache = array();
    private $currentTestCase;
    private $suiteTitle;
    private $suiteResults;

    private $before = null;
    private $after = null;
    private $beforeEach = null;
    private $afterEach = null;

    /**
     * A public object for storing state and other variables across test cases and method calls.
     *
     * @var StdClass
     */
    public $data = null;

    /**
     * The constructor.
     *
     * @param string $title The suite title
     */
    public function __construct($title)
    {
        $this->suiteTitle = $title;
        $this->data = new StdClass;
        $this->suiteResults = array('pass' => 0, 'fail' => 0);
    }

    /**
     * Add a test case.
     *
     * @param string $name Title of the test case
     * @param function $testCase (optional) The test case as a callback
     *
     * @return $this
     */
    public function test($name, Closure $testCase = null)
    {
        if (is_callable($name)) {
            $testCase = $name;
            $name = "Test Case #" . (count($this->tests) + 1);
        }

        $this->affirmCallable($testCase, "test");

        $this->tests[] = array("name" => $name, "testCase" => $testCase);
        return $this;
    }

    /**
     * Executed once before the test cases are run.
     *
     * @param function $callback An anonymous callback function
     */
    public function before(Closure $callback)
    {
        $this->affirmCallable($callback, "before");
        $this->before = $callback;
    }

    /**
     * Executed once after the test cases are run.
     *
     * @param function $callback An anonymous callback function
     */
    public function after(Closure $callback)
    {
        $this->affirmCallable($callback, "after");
        $this->after = $callback;
    }

    /**
     * Executed for every test case, before it is run.
     *
     * @param function $callback An anonymous callback function
     */
    public function beforeEach(Closure $callback)
    {
        $this->affirmCallable($callback, "beforeEach");
        $this->beforeEach = $callback;
    }

    /**
     * Executed for every test case, after it is run.
     *
     * @param function $callback An anonymous callback function
     */
    public function afterEach(Closure $callback)
    {
        $this->affirmCallable($callback, "afterEach");
        $this->afterEach = $callback;
    }

    /**
     * Run all the tests and before / after functions. Calls {@see report} to generate the HTML report page.
     *
     * @return $this
     */
    public function run()
    {
        $arr = array($this);

        if (is_callable($this->before)) {
            call_user_func_array($this->before, $arr);
        }

        foreach($this->tests as $test) {
            $this->currentTestCase = $test['name'];

            if (is_callable($this->beforeEach)) {
                call_user_func_array($this->beforeEach, $arr);
            }

            // Executing the testcase
            call_user_func_array($test['testCase'], $arr);

            if (is_callable($this->afterEach)) {
                call_user_func_array($this->afterEach, $arr);
            }
        }

        if (is_callable($this->after)) {
            call_user_func_array($this->after, $arr);
        }

        $this->report();

        return $this;
    }

    /**
     * Alias for {@see assertTrue} method.
     *
     * @param boolean $arg The result of a boolean expression
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     * @see Testify->assertTrue()
     *
     * @return boolean
     */
    public function assert($arg, $message = '')
    {
        return $this->recordTest($arg == true, $message, $arg, true);
    }

    /**
     * Passes if given a truthfull expression.
     *
     * @param boolean $arg The result of a boolean expression
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertTrue($arg, $message = '')
    {
        return $this->recordTest($arg == true, $message, $arg, true);
    }

    /**
     * Passes if given a falsy expression.
     *
     * @param boolean $arg The result of a boolean expression
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertFalse($arg, $message = '')
    {
        return $this->recordTest($arg == false, $message, $arg, false);
    }

    /**
     * Passes if $arg1 == $arg2.
     *
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertEquals($arg1, $arg2, $message = '')
    {
        return $this->recordTest($arg1 == $arg2, $message, $arg1, $arg2);
    }

    /**
     * Passes if $arg1 == json_decode($arg2).
     *
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertJson(array $arg1, $arg2, $message = '')
    {
        $arr2 = NULL;
        if (!is_array($arg2)) {
            $arr2 = @json_decode($arg2, true);
            if (!$arr2 || !is_array($arr2)) {
                $arr2 = array();
            }
        } else {
            $arr2 = $arg2;
        }
        return $this->recordTest(count(@array_diff_assoc($arg1, $arr2)) == 0, $message, $arg1, $arg2);
    }

    /**
     * Passes if $arg1 != $arg2.
     *
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertNotEquals($arg1, $arg2, $message = '')
    {
        return $this->recordTest($arg1 != $arg2, $message, $arg1, $arg2);
    }

    /**
     * Passes if $arg1 === $arg2.
     *
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertSame($arg1, $arg2, $message = '')
    {
        return $this->recordTest($arg1 === $arg2, $message, $arg1, $arg2);
    }

    /**
     * Passes if $arg1 !== $arg2.
     *
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertNotSame($arg1, $arg2, $message = '')
    {
        return $this->recordTest($arg1 !== $arg2, $message, $arg1, $arg2);
    }

    /**
     * Passes if $arg is an element of $arr.
     *
     * @param mixed $arg
     * @param array $arr
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertInArray($arg, array $arr, $message = '')
    {
        return $this->recordTest(in_array($arg, $arr), $message, $arg1, $arg2);
    }

    /**
     * Passes if $arg is not an element of $arr.
     *
     * @param mixed $arg
     * @param array $arr
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertNotInArray($arg, array $arr, $message = '')
    {
        return $this->recordTest(!in_array($arg, $arr), $message, $arg1, $arg2);
    }

    /**
     * Unconditional pass.
     *
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function pass($message = '')
    {
        return $this->recordTest(true, $message);
    }

    /**
     * Unconditional fail.
     *
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function fail($message = '')
    {
        // This check fails every time
        return $this->recordTest(false, $message);
    }
    
    /**
     * Calculate the percentage of success for a test
     *
     * @param array $suiteResults
     * @return float Percent
     */
    function percent($suiteResults) {
        $sum = $suiteResults['pass'] + $suiteResults['fail'];
        return round($suiteResults['pass'] * 100 / max($sum, 1), 2);
    }

    function array2string($data){
        $log_a = "";
        foreach ($data as $key => $value) {
            if(is_array($value))    $log_a .= "[".$key."] => (". array2string($value). ") \n";
            else                    $log_a .= "[".$key."] => ".$value."\n";
        }
        return $log_a;
    }

    /**
     * Generates a pretty CLI or HTML5 report of the test suite status. Called implicitly by {@see run}.
     *
     * @return $this
     */
    public function report()
    {
        $title = $this->suiteTitle;
        $suiteResults = $this->suiteResults;
        $cases = $this->stack;

        if (php_sapi_name() === 'cli') {
            // include dirname(__FILE__) . '/testify.report.cli.php';
            
            $result = $suiteResults['fail'] === 0 ? 'pass' : 'fail';
            
            echo str_repeat('-', 80)."\n", " $title  [$result]\n";

            foreach($cases as $caseTitle => $case) {
              $result = $case['fail'] === 0 ? 'pass' : 'fail';
              echo
                "\n".str_repeat('-', 80)."\n",
                "[$result] $caseTitle  {pass {$case['pass']} / fail {$case['fail']}}\n\n";
                foreach ($case['tests'] as $test) {
                    echo
                    "[{$test['result']}] {$test['type']}()\n",
                    str_repeat(' ', 7)."line {$test['line']}, {$test['file']}\n",
                    str_repeat(' ', 7)."{$test['source']}\n";

                    if ($test['result'] == 'fail' && (isset($test['expect']) || isset($test['actual']))) {
                        $exp = NULL;
                        if (is_array($test['expect'])) {
                            // $exp = $this->array2string($test['expect']);
                            $exp = json_encode($test['expect']);
                        } else {
                            $exp = $test['expect'];
                        }

                        $act = NULL;
                        if (is_array($test['actual'])) {
                            // $act = $this->array2string($test['actual']);
                            $exp = json_encode($test['actual']);
                        } else {
                            $act = $test['actual'];
                        }

                        echo str_repeat(' ', 7)."-------------------------\n";
                        echo str_repeat(' ', 7)."Expect: ".$exp."\n";
                        echo str_repeat(' ', 7)."Actual: ".$act."\n";
                        echo str_repeat(' ', 7)."-------------------------\n";
                    }
                }
            }
            echo
                str_repeat('=', 80)."\n",
                "Tests: [$result], {pass {$suiteResults['pass']} / fail {$suiteResults['fail']}}, ",
                $this->percent($suiteResults)."% success\n";
        } else {
            // include dirname(__FILE__) . '/testify.report.html.php';
        }

        return $this;
    }

    /**
     * A helper method for recording the results of the assertions in the internal stack.
     *
     * @param boolean $pass If equals true, the test has passed, otherwise failed
     * @param string $message (optional) Custom message
     * @param mixed $expected (optional) What exepected to have
     * @param mixed $actual (optional) What actually got
     *
     * @return boolean
     */
    private function recordTest($pass, $message = '', $expected = NULL, $actual = NULL)
    {
        if (!array_key_exists($this->currentTestCase, $this->stack) ||
              !is_array($this->stack[$this->currentTestCase])) {

            $this->stack[$this->currentTestCase]['tests'] = array();
            $this->stack[$this->currentTestCase]['pass'] = 0;
            $this->stack[$this->currentTestCase]['fail'] = 0;
        }

        $bt = debug_backtrace();
        $source = $this->getFileLine($bt[1]['file'], $bt[1]['line'] - 1);
        $bt[1]['file'] = basename($bt[1]['file']);

        $result = $pass ? "pass" : "fail";
        $this->stack[$this->currentTestCase]['tests'][] = array(
            "name"      => $message,
            "type"      => $bt[1]['function'],
            "result"    => $result,
            "line"      => $bt[1]['line'],
            "file"      => $bt[1]['file'],
            "source"    => $source,
            "expect"    => $expected,
            "actual"    => $actual
        );

        $this->stack[$this->currentTestCase][$result]++;
        $this->suiteResults[$result]++;

        return $pass;
    }

    /**
     * Internal method for fetching a specific line of a text file. With caching.
     *
     * @param string $file The file name
     * @param integer $line The line number to return
     *
     * @return string
     */
    private function getFileLine($file, $line)
    {
        if (!array_key_exists($file, $this->fileCache)) {
            $this->fileCache[$file] = file($file);
        }

        return trim($this->fileCache[$file][$line]);
    }

    /**
     * Internal helper method for determine whether a variable is callable as a function.
     *
     * @param mixed $callback The variable to check
     * @param string $name Used for the error message text to indicate the name of the parent context
     * @throws TestifyException if callback argument is not a function
     */
    private function affirmCallable(&$callback, $name)
    {
        if (!is_callable($callback)) {
            throw new TestifyException("$name(): is not a valid callback function!");
        }
    }

    /**
     * Alias for {@see assertEquals}.
     *
     * @deprecated Not recommended, use {@see assertEquals}
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertEqual($arg1, $arg2, $message = '')
    {
        return $this->recordTest($arg1 == $arg2, $message, $arg1, $arg2);
    }

    /**
     * Alias for {@see assertSame}.
     *
     * @deprecated Not recommended, use {@see assertSame}
     * @param mixed $arg1
     * @param mixed $arg2
     * @param string $message (optional) Custom message. SHOULD be specified for easier debugging
     *
     * @return boolean
     */
    public function assertIdentical($arg1, $arg2, $message = '')
    {
        return $this->recordTest($arg1 === $arg2, $message, $arg1, $arg2);
    }

    /**
     * Alias for {@see run} method.
     *
     * @see Testify->run()
     *
     * @return $this
     */
    public function __invoke()
    {
        return $this->run();
    }

    // Extras
    
    function httpGet($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.154 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_TRY);
        $outch = curl_exec($ch);
        curl_close($ch);
        return $outch;
    }

    function httpPost($url, array $data) {
        $content = http_build_query($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.154 Safari/537.36');
        $outch = curl_exec($ch);
        curl_close($ch);
        return $outch;
    }

    function httpHead($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.154 Safari/537.36');
        $outch = curl_exec($ch);
        curl_close($ch);
        return $outch;
    }

    function execRun($command, &$pipes) {
        $proc = proc_open($command,
            array(
                array("pipe","r"),
                array("pipe","w"),
                array("pipe","w")
            ),
            $pipes
        );
        if (!$proc) {
            throw new TestifyException("Failed to exec command '$command'");
        }
        return $proc;
    }

    function execStdOut(array $pipes) {
        return stream_get_contents($pipes[1]);
    }

    function execStdErr(array $pipes) {
        return stream_get_contents($pipes[2]);
    }

    function execStop($proc, array $pipes) {
        fclose($pipes[1]);
        fclose($pipes[2]);
        @proc_close($proc);
    }

    function assertExecActive($proc, $message) {
        $status = @proc_get_status($proc);
        $val = $status && isset($status['pid']) && isset($status['running']) && $status['running'];

        return $this->recordTest($val == true, $message);
    }

    function assertExecStopped($proc, $message = "Process shall be stopped") {
        $status = @proc_get_status($proc);
        $val = !$status || !$status['running'];

        return $this->recordTest($val == true, $message);
    }

    function assertExecExitCode($proc, $code, $message) {
        $status = @proc_get_status($proc);
        $val = $status && isset($status['exitcode'])? $status['exitcode'] : NULL;

        return $this->recordTest($val == $code, $message);
    }
}

/**
 * TestifyException class
 */
class TestifyException extends Exception {
}
