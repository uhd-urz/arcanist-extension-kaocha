<?php

/**
 * Very basic unit test engine which runs libphutil tests.
 */
final class MidjeUnitTestEngine extends ArcanistUnitTestEngine {

  public function getEngineConfigurationName() {
    return 'midje';
  }

  protected function supportsRunAllTests() {
    return true;
  }

  public function shouldEchoTestResults() {
    return false;
  }

  public function run() {
    if ($this->getRunAllTests()) {
      $run_tests = $this->getAllTests();
    } else {
      $run_tests = $this->getTestsForPaths();
    }

    if (!$run_tests) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    $midje_config = new TempFile();
    Filesystem::writeFile($midje_config,
<<<CFG
(change-defaults
  :emitter 'midje.emission.plugins.junit
  :colorize false)
CFG
);

    try {
      list($stdout, $stderr) = execx('lein midje %C :config %s',
        implode(' ', $run_tests),
        $midje_config);
    } catch (CommandException $e) {
      // Handle only special error codes (see e.g. `man 1 bash` about
      // "EXIT STATUS")
      if ($e->getError() > 125) {
        throw $e;
      }
    }

    $results = array();
    foreach ($run_tests as $test) {
      $xunit_report = 'target/surefire-reports/TEST-'.$test.'.xml';
      $cover_report = '';

      $results[] = $this->parseTestResults(
        $test,
        $xunit_report,
        $cover_report);
    }

    return array_mergev($results);
  }

  private function parseTestResults($test, $xunit_report, $cover_report) {
    $xunit_results = Filesystem::readFile($xunit_report);
    return id(new ArcanistXUnitTestResultParser())
      ->parseTestResults($xunit_results);
  }

  private $clojureExtensions = array('clj', 'cljs', 'cljc');

  private function isSupportedFileType($path) {
    $extension = idx(pathinfo($path), 'extension');
    foreach ($this->clojureExtensions as $clojure_extension) {
      if ($extension == $clojure_extension) {
        return true;
      }
    }

    return false;
  }

  // FIXME This list should actually be retrieved from `:test-paths` in
  // "project.clj"
  private $projectTestPaths = array('test');

  private $testPrefix = 'test_';
  private $testSuffix = '';

  /**
   * Small convenience wrapper around `pathinfo()`.
   */
  private static function stripFileExtension($path) {
    $path_info = pathinfo($path);
    return $path_info['dirname'].'/'.$path_info['filename'];
  }

  /**
   * Small convenience function to remove a given prefix from a path.
   */
  private static function stripPathPrefix($path, $prefix) {
    $prefix = $prefix.'/';
    $prefix_len = strlen($prefix);
    if (substr($path, 0, $prefix_len) == $prefix) {
      return substr($path, $prefix_len);
    }
  }

  /**
   * For a given test file, figure out the namespace it contains.
   *
   * @param $path  string  Filename with extension and prefix
   * @param $path_prefixes  list<string>  List of possible prefixes the file
   *  might be in
   * @return string  Namespace
   */
  private static function pathToNamespace($path) {
    $path = self::stripFileExtension($path);
    $namespace = str_replace('_', '-', $path);
    $namespace = str_replace('/', '.', $namespace);
    return $namespace;
  }

  /**
   * For a given path, figure out the corresponding test case's namespace.
   *
   * @param $path  string  Path of the file to be tested.
   * @return string or null  Namespace of the test case, if any.
   */
  private function pathToTest($path) {
    $path_info = pathinfo($path);
    $base_test_path = $path_info['dirname'].'/'.
      $this->testPrefix.$path_info['filename'].$this->testSuffix.
      '.'.$path_info['extension'];

    while ($base_test_path) {
      foreach ($this->projectTestPaths as $test_path_prefix) {
        $test_path = $test_path_prefix.'/'.$base_test_path;
        if (is_readable($test_path)) {
          return self::pathToNamespace($base_test_path);
        }
      }

      // FIXME This could be simplified and sped up, if we had `:source-paths`
      // from "project.clj".
      $next_pos = strpos($base_test_path, DIRECTORY_SEPARATOR);
      if (!$next_pos) {
        return;
      }
      $base_test_path = substr($base_test_path, $next_pos + 1);
    }

    return;
  }

  /**
   * Retrieve all test cases.
   *
   * @return list<string>  The names of the test case namespaces to be executed.
   */
  private function getAllTests() {
    $test_paths = array();
    foreach ($this->projectTestPaths as $test_path_prefix) {
      foreach ($this->clojureExtensions as $extension) {
        // FIXME This could benefit from array_combine+array_map in PHP 5.4
        $test_paths_in_prefix = glob($test_path_prefix.'/**/*.'.$extension);
        foreach ($test_paths_in_prefix as $test_path) {
          $base_path = self::stripPathPrefix($test_path, $test_path_prefix);
          $test_paths[$base_path] = $test_path;
        }
      }
    }

    $run_tests = array();
    foreach ($test_paths as $base_path => $test_path) {
      $test = self::pathToNamespace($base_path);
      if ($test) {
        $run_tests[] = $test;
      }
    }

    return $run_tests;
  }

  /**
   * Retrieve all relevant test cases.
   *
   * For every affected file, it looks for a corresponding test case namespace
   * with a `-test` suffix.
   *
   * @return list<string>  The names of the test case namespaces to be executed.
   */
  private function getTestsForPaths() {
    $run_tests = array();
    foreach ($this->getPaths() as $path) {
      if (!$this->isSupportedFileType($path)) {
        continue;
      }

      $test = $this->pathToTest($path);
      if ($test) {
        $run_tests[] = $test;
      }
    }

    return $run_tests;
  }

}
