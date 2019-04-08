## Install

To use this extension:

1. Check out these sources and symlink `KaochaUnitTestEngine.php` into `arcanist/src/extensions/`
2. Modify your `project.clj` to contain:
```edn
:profiles {:test {:dependencies [[lambdaisland/kaocha "0.0-413"]
                                 [lambdaisland/kaocha-junit-xml "0.0-70"]]}}
:aliases {"kaocha" ["with-profile" "+test" "run" "-m" "kaocha.runner"]}
```
3. Create a file `tests.edn` with following content:
```edn
#kaocha/v1
{:plugins [:kaocha.plugin/profiling
           :kaocha.plugin/capture-output
           :kaocha.plugin/junit-xml]
 :kaocha.plugin.junit-xml/target-file "target/test/junit.xml"}
```
4. Name your test namespaces `...-test` (which also happens to be the convention for Kaocha)

## Further documentation

* https://cljdoc.org/d/lambdaisland/kaocha/0.0-413/doc/readme
* https://cljdoc.org/d/lambdaisland/kaocha-junit-xml/0.0-70/doc/readme


