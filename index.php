<?php
/*******************************************************************************
twitch-rss
creation: 2014-11-30 00:00 +0000
  update: 2016-11-17 21:09 +0000
*******************************************************************************/




/******************************************************************************/
error_reporting(0);
mb_internal_encoding('utf-8');




/******************************************************************************/
$CFG_TIME = time();

$CFG_DIR_CACHE  = './cache/';
$CFG_DIR_CONFIG = './config/';

$CFG_CACHE_AGE_MAX = 300;

$CFG_LIMIT_DEFAULT = 30;

$CFG_CLIENT_ID = 'jzkbprff40iqj646a697cyrvl0zt2m6';




/******************************************************************************/
function hsc($str) {
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
}




/******************************************************************************/
function durationFormat($length=0) {
    $h = floor($length/3600);
    $m = floor($length%3600/60);
    $s = $length%3600%60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}




/****************************************************** clean old cache files */
function cacheClean() {

    global $CFG_DIR_CACHE, $CFG_TIME;

    $d = opendir($CFG_DIR_CACHE);
    while(false !== $fn = readdir($d)) {

        // file path
        $fp = $CFG_DIR_CACHE.$fn;

        // check filename prefix
        if(substr($fn,0,6) != 'cache-') {
            continue;
        }

        // file age
        $age = $CFG_TIME - filemtime($fp);

        // file too fresh (less than a day)
        if($age <= 86400) {
            continue;
        }

        // all checks passed, file can be deleted
        unlink($fp);
    }
    closedir($d);
}




/***************************************************** fetch url (with cache) */
function urlFetch(
    $urls=array()  // list of urls
    ,$data=array() // extra data
) {

    global $CFG_TIME, $CFG_DIR_CACHE, $CFG_CACHE_AGE_MAX;


    // select api version
    $apiVersion = $data['useV3']
        ? 'Accept: application/vnd.twitchtv.v3+json'
        : 'Accept: application/vnd.twitchtv.v5+json';


    // curl options
    $curl_opts = array(
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => array(
            $apiVersion,
            'Client-ID: '.$data['clientId'],
        ),
    );


    // store curl handles
    $curl_handles = array();


    // get cache or queue for fetch
    foreach($urls as $id=>&$url) {

        // cache: file name, file path, file age
        $cfn = 'cache-'.sha1($url).'.txt.gz';
        $cfp = $CFG_DIR_CACHE.$cfn;
        $cfa = $CFG_TIME - (file_exists($cfp) ? filemtime($cfp) : 0);


        // update structure
        $url = array(
            'url' => $url,
            'cfn' => $cfn,
            'cfp' => $cfp,
            'cfa' => $cfa,
        );


        // cache too old, create curl handle to renew
        if($url['cfa'] > $CFG_CACHE_AGE_MAX) {
            $curl_handles[$id] = curl_init($url['url']);
            curl_setopt_array($curl_handles[$id], $curl_opts);
        }
    }
    unset($url);


    // run curl, if necessary
    if(count($curl_handles) > 0) {

        $curl = curl_multi_init();

        foreach($curl_handles as $id=>$handle) {
            curl_multi_add_handle($curl, $handle);
        }

        $curl_running = 1;
        while($curl_running > 0) {

            curl_multi_exec($curl, $curl_running);

            curl_multi_select($curl, 2);

            while($info = curl_multi_info_read($curl)) {

                $handle = $info['handle'];

                $id = array_search($handle, $curl_handles);

                $info = curl_getinfo($handle);

                $content = curl_multi_getcontent($handle);

                if($info['http_code'] == 200 && $info['size_download'] > 0) {
                    file_put_contents($urls[$id]['cfp'], gzencode($content, 5));
                }
            }
        }

        curl_multi_close($curl);
    }


    // retrieve content from cache
    foreach($urls as $id=>&$url) {

        $url['content'] = file_exists($url['cfp'])
            ? implode(gzfile($url['cfp']))
            : null;

        $url['json'] = $url['content']
            ? json_decode($url['content'], true)
            : null;
    }
    unset($url);


    return $urls;
}




/************************************************************** show rss feed */
if($_GET['channelid'] || $_GET['channel']) {


    // params
    $getChannelid = $_GET['channelid'];
    $getChannel   = $_GET['channel'];
    $getLimit     = $_GET['limit'];
    $getKey       = $_GET['key'];


    // default client id
    $cfgClientId = $CFG_CLIENT_ID;


    // get client id
    $fileClientId = $CFG_DIR_CONFIG.'clientid.txt';
    if(is_file($fileClientId)) {
        $cfgClientId = file_get_contents($fileClientId);
    }


    // check for key (password)
    $fileKey = $CFG_DIR_CONFIG.'key.txt';
    if(is_file($fileKey)) {
        $cfgKey = file_get_contents($fileKey);
        if($getKey != $cfgKey) {
            exit('key is invalid');
        }
    }


    // clean the cache
    cacheClean();


    /***
    url params
    ***/

    $paramOptions = array(
        'clientId' => $cfgClientId,
        'useV3'    => false,
    );


    $paramChannel = urlencode($getChannelid);


    // got username instead of id, revert to api v3
    if($getChannel) {
        $paramChannel = urlencode($getChannel);
        $paramOptions['useV3'] = true;
    }


    // max number of returned items
    $paramLimit = urlencode($getLimit != null
        ? (int)$getLimit
        : $CFG_LIMIT_DEFAULT
    );


    // urls to fetch
    $urls = array(
        'channels' => "https://api.twitch.tv/kraken/channels/$paramChannel",
        'users'    => "https://api.twitch.tv/kraken/users/$paramChannel",
        'videos'   => "https://api.twitch.tv/kraken/channels/$paramChannel/videos"
                     ."?limit=$paramLimit&offset=0&broadcasts=true",
    );


    // fetch urls data
    $data = urlFetch($urls, $paramOptions);


    $rss_items = '';

    foreach($data['videos']['json']['videos'] as $video) {

        //print_r($video);exit();

        // skip if video is still being recorded
        if($video['status'] == 'recording') {
            continue;
        }

        // timestamp of recording start
        $startStamp = strtotime($video['recorded_at']);

        // api v5 returns an array, v3 returns a url
        $videoPreview = is_array($video['preview'])
            ? $video['preview']['medium']
            : $video['preview'];

        // build item
        $rss_items .= '
        <item>
            <title>'.hsc($video['title']).'</title>
            <link>'.hsc($video['url']).'</link>
            <pubDate>'.gmdate(DATE_RSS, $startStamp).'</pubDate>
            <description>'.(
'<![CDATA[<pre>'.hsc('   Video id: '.$video['_id'].'
    Started: '.gmdate('Y-m-d H:i:s O', $startStamp).'
   Duration: '.durationformat($video['length']).'
     Status: '.$video['status'].'

      Title: '.$video['title'].'
       Game: '.$video['game'].'
Description: '.$video['description']).'</pre>

<img src="'.$videoPreview.'" alt="preview"/>]]>'
         ).'</description>
            <media:thumbnail url="'.hsc($videoPreview).'"/>
            <media:content url="" duration="'.hsc($video['length']).'"/>
        </item>';
    }


    $rss = '<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
    <channel>
        <title>'.hsc('twitch.tv - '.$data['users']['json']['display_name']).'</title>
        <description>'.hsc($data['users']['json']['bio']).'</description>
        <pubDate>'.gmdate(DATE_RSS).'</pubDate>
        <link>'.hsc($data['channels']['json']['url']).'</link>
        <image>
            <url>'.hsc($data['users']['json']['logo']).'</url>
            <title>'.hsc($data['users']['json']['display_name']).'</title>
            <link>'.hsc($data['channels']['json']['url']).'</link>
        </image>'.
        $rss_items.'
    </channel>
</rss>';


    // output
    header('content-type: application/xml');
    exit($rss);
}




/******************************************************************** default */
exit('twitch-rss<br/><a href="http://spenibus.net">spenibus.net</a>');
?>