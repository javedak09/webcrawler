<?php
/**
 * Created by PhpStorm.
 * User: javed.khan
 * Date: 6/14/2022
 * Time: 10:08 AM
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', -1);

require_once('dbconfig.php');


class crawler
{
    protected $_url;
    protected $_depth;
    protected $_host;
    protected $_useHttpAuth = false;
    protected $_user;
    protected $_pass;
    protected $_seen = array();
    protected $_filter = array();

    public function __construct($url, $depth)
    {
        $this->_url = $url;
        $this->_depth = $depth;
        $parse = parse_url($url);
        $this->_host = $parse['host'];
    }

    public function setHttpAuth($user, $pass)
    {
        $this->_useHttpAuth = true;
        $this->_user = $user;
        $this->_pass = $pass;
    }

    public function addFilterPath($path)
    {
        $this->_filter[] = $path;
    }

    public function run()
    {
        $this->crawl_page($this->_url, $this->_depth);
    }

    public function crawl_page($url, $depth)
    {
        if (!$this->isValid($url, $depth)) {
            return;
        }
        // add to the seen URL
        $this->_seen[$url] = true;
        // get Content and Return Code
        list($content, $httpcode, $time) = $this->_getContent($url);

        // print Result for current Page
        $this->_printResult($url, $depth, $httpcode, $time);

        // process subPages
        $this->_processAnchors($content, $url, $depth);
    }

    protected function isValid($url, $depth)
    {
        if (strpos($url, $this->_host) === false
            || $depth === 0
            || isset($this->_seen[$url])
        ) {
            return false;
        }
        foreach ($this->_filter as $excludePath) {
            if (strpos($url, $excludePath) !== false) {
                return false;
            }
        }
        return true;
    }

    protected function _getContent($url)
    {
        $handle = curl_init($url);
        if ($this->_useHttpAuth) {
            curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($handle, CURLOPT_USERPWD, $this->_user . ":" . $this->_pass);
        }
        // follows 302 redirect, creates problem wiht authentication
//        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);
        // return the content
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);

        /* Get the HTML or whatever is linked in $url. */
        $response = curl_exec($handle);
        // response total time
        $time = curl_getinfo($handle, CURLINFO_TOTAL_TIME);
        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);
        return array($response, $httpCode, $time);
    }

    public function _printResult($url, $depth, $httpcode, $time)
    {
        ob_end_flush();
        $currentDepth = $this->_depth - $depth;
        $count = count($this->_seen);
        echo "N::$count,CODE::$httpcode,TIME::$time,DEPTH::$currentDepth URL::<a target='_blank' href='" . $url . "'>$url</a><br/>";

        $str = str_replace(",", "", $url);
        $str = str_replace('"', "", $str);
        $str = str_replace("'", "", $str);

        $this->InsertData($str);

        ob_start();
        flush();
    }

    protected function InsertData($url)
    {
        $host = "cls-pae-fp51764";
        $username = "sa";
        $password = "sa";
        $database = "webcrawler";
        $connectionInfo = array("Database" => $database, "UID" => $username, "PWD" => $password);
        $con = sqlsrv_connect($host, $connectionInfo);
        if ($con === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        $param = array($url);


        $qry = "insert into hrefdata(href) values('" . $url . "')";

        $result = sqlsrv_query($con, $qry, $param);

        if ($result === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        sqlsrv_close($con);
    }

    protected function _processAnchors($content, $url, $depth)
    {

        $htmlContent = file_get_contents($url);
        $DOM = new DOMDocument("1.0");
        libxml_use_internal_errors(1);
        $DOM->loadHTML($htmlContent);
        $mainHeader = $DOM->getElementsByTagName('article');


        //$Section1b = $mainHeader->item(0)->getElementsByTagName('a');
        //$Section1a = $mainHeader->item(0)->getElementsByTagName('table');
        //$HeaderSec1a = $Section1a->item(1)->getElementsByTagName('tr');

        $myArray = array();
        $myArray_ch = array();


        //for ($i = 0; $i < $mainHeader->length; $i++) {
        for ($i = 0; $i < 1; $i++) {

            $subHeader = $mainHeader->item($i)->getElementsByTagName('a');
            $myArray[$i]['name'] = $subHeader->item(0)->nodeValue;
            $myArray[$i]['href'] = $subHeader->item(0)->getAttribute('href');
            $myArray[$i]['ref'] = $subHeader->item(0)->getAttribute('ref');
            $myArray[$i]['data-ga-category'] = $subHeader->item(0)->getAttribute('data-ga-category');
            $myArray[$i]['data-ga-label'] = $subHeader->item(0)->getAttribute('data-ga-label');
            $myArray[$i]['data-full-article-url'] = $subHeader->item(0)->getAttribute('data-full-article-url');
            $myArray[$i]['data-article-id'] = $subHeader->item(0)->getAttribute('data-article-id');


            $url_new = "https://pubmed.ncbi.nlm.nih.gov" . $myArray[$i]['href'];

            $htmlContent_ch = file_get_contents($url_new);
            $DOM_ch = new DOMDocument();
            libxml_use_internal_errors(1);
            $DOM_ch->loadHTML($htmlContent_ch);
            $mainHeader_ch = $DOM_ch->getElementById('article-page');


            $index = 0;
            $heading1 = $mainHeader_ch->getElementsByTagName('h1');
            $myArray_ch[$index]['name'] = $heading1->item(0)->nodeValue;

            $myHeader = $mainHeader_ch->getElementsByTagName("header");
            $myHeaderChild = $myHeader->item(0)->getElementsByTagName("div");
            for ($i = 0; $i < $myHeaderChild->length; $i++) {
                $div_class = $myHeaderChild->item($i)->getAttribute("class");
                if ($div_class == 'authors-list') {
                    //$author=$div_class->getAttribute("a");
                }
            }
            echo "<pre>";
            print_r($myHeaderChild);
            echo "<pre>";

            //for ($i = 0; $i < $mainHeader_ch->length; $i++) {
            for ($ch = 0; $ch < 25; $ch++) {


//                $subHeader_ch = $this->getElementsByClass($DOM_ch->getElementsByTagName("header"), "div", "authors-list-item ");


                $index++;

                /*
                $myArray_ch[$i]['name'] = $subHeader_ch->item(0)->nodeValue;
                $myArray_ch[$i]['href'] = $subHeader_ch->item(0)->getAttribute('href');
                $myArray_ch[$i]['ref'] = $subHeader_ch->item(0)->getAttribute('ref');
                $myArray_ch[$i]['data-ga-category'] = $subHeader_ch->item(0)->getAttribute('data-ga-category');
                $myArray_ch[$i]['data-ga-label'] = $subHeader_ch->item(0)->getAttribute('data-ga-label');
                $myArray_ch[$i]['data-full-article-url'] = $subHeader_ch->item(0)->getAttribute('data-full-article-url');
                $myArray_ch[$i]['data-article-id'] = $subHeader_ch->item(0)->getAttribute('data-article-id');
                */


            }


            //$href = $mainHeader->item(1)->getElementsByTagName('a');
            //echo $subHeader->item(0)->nodeValue . " link " . "<br/><br/>";
        }

        /*echo "<pre>";
        print_r($subHeader_ch);
        echo "<pre>";*/

        die();

        /*$dom = new DOMDocument('1.0');
        $dom->loadHTML($content);
        $anchors = $dom->getElementsByTagName('article');

        foreach ($anchors as $element) {

            print_r($anchors . "<br/>");

            die();

            $href = $element->getAttribute('href');
            print_r($href);


            /* if (0 !== strpos($href, 'http')) {
                     $path = '/' . ltrim($href, '/');
                     if (extension_loaded('http')) {
                         $href = http_build_url($url, array('path' => $path));
                     } else {
                         $parts = parse_url($url);
                         $href = $parts['scheme'] . '://';
                         if (isset($parts['user']) && isset($parts['pass'])) {
                             $href .= $parts['user'] . ':' . $parts['pass'] . '@';
                         }
                         $href .= $parts['host'];
                         if (isset($parts['port'])) {
                             $href .= ':' . $parts['port'];
                         }
                         $href .= $path;
                     }
             }
            // Crawl only link that belongs to the start domain

            //$this->crawl_page($href, $depth - 1);

        }*/
    }

    function getElementsByClass(&$parentNode, $tagName, $className)
    {
        $nodes = array();

        $childNodeList = $parentNode->getElementsByTagName($tagName);
        for ($i = 0; $i < $childNodeList->length; $i++) {
            $temp = $childNodeList->item($i);
            if (stripos($temp->getAttribute('class'), $className) !== false) {
                $nodes[] = $temp;
            }
        }

        return $nodes;
    }


    /*function crawl_page1($url, $depth)
    {
        if ($depth > 0) {
            $html = file_get_contents($url);

            preg_match_all('~<a.*?href="(.*?)".*?>~', $html, $matches);

            foreach ($matches[1] as $newurl) {
                crawl_page1($newurl, $depth - 1);
            }

            echo $newurl . " - " . $html . " - ";

            //file_put_contents(dirname(__FILE__) . '\results.txt', $newurl . "\n\n" . $html . "\n\n", FILE_APPEND);
        }
    }*/

}
