<?php

class HTTPChecker {

    public $is_parent = true;

    public $children = 0;

    private $next_url = "";

    private $workers;

    private $urls;


    public function __construct() {

        $this->urls = array(
            "http://dealnews.com/",
            "http://www.google.com/",
            "http://www.yahoo.com/",
            "http://www.facebook.com/",
            "http://www.myspace.com/",
            "http://www.twitter.com/"
        );

        $this->children = count($this->urls);

        $this->workers = array();

    }


    public function __destruct() {


    }


    public function before_fork() {

        $this->next_url = array_shift($this->urls);
        echo "Setting next URL to $this->next_url\n";

    }


    public function after_fork($pid) {

        $this->workers[$pid] = $this->next_url;

        echo "Worker $pid is monitoring $this->next_url\n";

        $this->next_url = "";

    }


    public function after_death($pid) {

        echo "Worker $pid has died.  Putting ".$this->workers[$pid]." back into the monitoring loop.\n";

        // push the URL back onto the stack so that we can get another worker on it.
        array_push($this->urls, $this->workers[$pid]);

    }



    public function fork() {

        // Setup headers - I used the same headers from Firefox version 2.0.0.6
        // below was split up because php.net said the line was too long. :/
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: "; // browsers keep this blank.

        $start = time();

        while(time() - $start < 30){

            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $this->next_url);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.0.7) Gecko/2009021906 Firefox/3.0.7');
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
            curl_setopt($curl, CURLOPT_AUTOREFERER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);

            $html = curl_exec($curl);

            $info = curl_getinfo($curl);

            echo "$this->next_url:\n";
            echo "    Status: $info[http_code]\n";
            echo "    Time:   $info[total_time]\n";

            sleep(10);

        }

    }

}

?>
