#!/usr/bin/env php
<?php

/*

Copyright (c) 1997 - 2013, dealnews.com, Inc.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
 * Neither the name of dealnews.com, Inc. nor the names of its contributors
   may be used to endorse or promote products derived from this software
   without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

*/

//    Example:
//
//    class Test {
//
//        /**
//         * Sums two numbers
//         *
//         * @param   int   $a  Some number
//         * @param   int   $b  Some number
//         * @return  int
//         *
//         * @test
//         * echo Test::test(1,2);
//         * @expects
//         * 3
//         * @end
//         */
//        public static function test($a, $b) {
//
//            return $a + $b;
//
//        }
//    }

require __DIR__."/lib/CLI.php";

$cli = new CLI(
    "Tests PHP code via test placed in doc blocks",
    array(
        "c" => array(
            "desc" => "Report files that changed and their mod times for debugging.",
            "optional" => true
        ),
        "d" => array(
            "param" => "DIRECTORY",
            "desc" => "Directory to search recursive for files to tests.",
            "optional" => true
        ),
        "f" => array(
            "param" => "FILE",
            "desc" => "The PHP file to test.",
            "optional" => true
        ),
        "n" => array(
            "desc" => "Only test if files a new. Only applies to -d.",
            "optional" => true
        ),
        "p" => array(
            "desc" => "Report passing tests.",
            "optional" => true
        ),
        "v" => array(
            "desc" => "Verbose output. Implies -p.",
            "optional" => true
        ),
        "test" => array(
            "param" => "TEST",
            "desc" => "Name of test to run. Skip others found. Only applies to -f.",
            "optional" => true
        ),
    )
);

CLI::check_pid(600, true);

$opts = $cli->opts;

define("VERBOSE", isset($opts["v"]));
define("REPORT_PASS", isset($opts["p"]));
define("REQUIRE_FILE", isset($opts["r"]));
define("INTERACTIVE", defined("STDOUT") && posix_isatty(STDOUT));

if(!empty($opts["d"])){

    $dirs = explode(",", $opts["d"]);

    $files = array();

    foreach($dirs as $DIR){

        if(!is_dir($DIR)){
            echo "$DIR is not a directory.";
            $status = 99;

        } else {

            if(substr($DIR, -1) != "/"){
                $DIR.= "/";
            }

            if(isset($opts["n"])){
                $state_file = "/tmp/doctest_state_".md5($DIR);

                $this_run_time = time();
                if(file_exists($state_file)){
                    $last_run_time = file_get_contents($state_file);
                    // test once an hour even if nothing has changed
                    // mostly this is so we keep getting nagged about
                    // any failures
                    if($this_run_time - $last_run_time < 3600){
                        $minutes = ceil(($this_run_time - $last_run_time) / 60);
                        $new_files = trim(`find $DIR -mmin -$minutes`);
                        if(empty($new_files)){
                            if(VERBOSE){
                                echo "No changes since last test run.\n";
                            }
                            exit(0);
                        } elseif(isset($cli->opts["c"])) {
                            echo "Changed files found since ".date("r", $last_run_time).":\n";
                            $new_files = explode("\n", $new_files);
                            foreach($new_files as $f){
                                echo "  $f changed at ".date("r", filemtime($f))."\n";
                            }
                        }
                    }
                }
            }

            $files = array_merge($files, explode("\n", trim(`fgrep -l "@test" \`find $DIR -type f | fgrep -v .svn | grep -P "\.php|html"\``)));
        }
    }


} elseif(!empty($opts["f"])){

    $files = array($opts["f"]);

    if(!empty($opts["test"])){
        $TESTNAME = $opts["test"];
    }

} else {
    echo "Either -d or -f is required.";
    $status = 99;
}

$status = 0;

foreach($files as $file){

    $data = @file_get_contents($file);

    if(empty($data)){
        echo "Failed to open file $file\n";
        $status = 1;
        continue;
    }

    $parts = preg_split("/(\@test.+?\@end)/s", $data, null, PREG_SPLIT_DELIM_CAPTURE);

    if(count($parts) > 1){

        if(VERBOSE){
            echo "Testing $file\n";
            echo str_repeat("=", 80)."\n";
        }

        foreach($parts as $key => $part){

            if(!VERBOSE && !REPORT_PASS && INTERACTIVE){
                CLI::spinner();
            }

            if(strpos($part, "@test") === 0){

                $part = preg_replace("/\n *\* ?/s", "\n", $part);

                // look for a named test
                if(preg_match("/@name (.+)/", $part, $match)){
                    $part = str_replace($match[0], "", $part);
                    $test_name = trim($match[1]);

                // or else find the next function name
                } elseif(preg_match("/function\s+([^ \(]+)/s", $parts[$key+1], $match)){
                    $test_name = trim($match[1]);

                // or throw error
                } else {
                    echo "\n****************************\n";
                    echo "* FAILED TO FIND TEST NAME *\n";
                    echo "****************************\n";
                    echo "File: $file\n";
                    echo "TEST:\n";
                    echo "$part\n";
                    $status = 2;
                    continue;

                }

                if(!empty($TESTNAME) && $TESTNAME != $test_name){
                    continue;
                }


                if(!preg_match("/\@test(.+?)\@expects(.+?)\@end/s", $part, $match) ||
                   empty($match[1]) || empty($match[2])){

                    echo "\n************************\n";
                    echo "* FAILED TO PARSE TEST *\n";
                    echo "************************\n";
                    echo "File: $file\n";
                    echo "Test: $test_name\n";
                    echo "TEST:\n";
                    echo "$part\n";
                    $status = 3;
                    continue;

                } else {

                    $tmpfile = tempnam("/tmp", "test_file");
                    $file_data = "<?php \n ";
                    $file_data.= "require_once '$file';\n";
                    $file_data.= "$match[1]\n";
                    $file_data.= "?>";
                    file_put_contents($tmpfile, $file_data);

                    $res = trim(`php $tmpfile`);

                    if($res == trim($match[2])){
                        if(REPORT_PASS || VERBOSE){
                            echo "[PASS]   Test $test_name in $file\n";
                        }
                    } else {

                        if(VERBOSE){
                            echo "\n************************\n";
                            echo "*     TEST FAILED      *\n";
                            echo "************************\n";
                            echo "File: $file\n";
                            echo "Test: $test_name\n";
                            echo "TEST:\n";
                            echo "$part\n";
                            echo "RESPONSE:\n";
                            echo $res;
                            echo "\n\n";
                        } else {
                            echo "[FAILED] Test $test_name in $file\n";
                        }

                        $status = 4;

                    }

                    unlink($tmpfile);

                }

            }

        }

    } else {

        if(VERBOSE){
            echo "No tests found in $file\n";
        }
    }

    if(VERBOSE){
        echo "\n";
    }

}

if(!empty($this_run_time) && $status == 0){
    file_put_contents($state_file, $this_run_time);
}

if(INTERACTIVE){
    echo "\r";
}

exit($status);

?>