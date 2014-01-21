<?php
/**
 * Created by PhpStorm.
 * User: ian
 * Date: 15/01/14
 * Time: 09:14
 */
require_once('custom/include/social/twitter/twitter_auth/twitteroauth/twitteroauth.php');
require('custom/modules/Connectors/connectors/sources/ext/rest/twitter/config.php');
require('custom/include/social/twitter/twitter_helper.php');

global $db;
global $current_user;

session_start();

$settings = array(
    'oauth_access_token' => $config['properties']['oauth_access_token'],
    'oauth_access_token_secret' => $config['properties']['oauth_access_token_secret'],
    'consumer_key' => $config['properties']['consumer_key'],
    'consumer_secret' => $config['properties']['consumer_secret'],
    'call_back_url' => $config['properties']['OAUTH_CALLBACK'],
);

if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
    if ($settings['consumer_key'] === '' || $settings['consumer_secret'] === '') {
        echo 'You need a consumer key and secret to test the sample code. Get one from <a href="https://dev.twitter.com/apps">dev.twitter.com/apps</a>';
        exit;
    }

    /* Build TwitterOAuth object with client credentials. */
    $connection = new TwitterOAuth($settings['consumer_key'], $settings['consumer_secret']);

    /* Get temporary credentials. */
    $request_token = $connection->getRequestToken($settings['call_back_url']);

    /* Save temporary credentials to session. */
    $_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
    $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

    $html ='';

    /* Build authorize URL and redirect user to Twitter. */
    $url = $connection->getAuthorizeURL($token);
    $html = "<a href='". $url ."'>Log into Twitter</a>";

}

/* Get user access tokens out of the session. */
$access_token = $_SESSION['access_token'];

/* Create a TwitterOauth object with consumer/user tokens. */
$connection = new TwitterOAuth($settings['consumer_key'], $settings['consumer_secret'], $access_token['oauth_token'], $access_token['oauth_token_secret']);

/* If method is set change API call made. Test is called by default. */
$tweets = $connection->get('statuses/home_timeline', array('screen_name' => $_SESSION['access_token']['screen_name'], 'exclude_replies ' => true));

$i = 0;

if (empty($tweets['errors'])) {
    while ($i < count($tweets)) {


        if (count($tweets[$i]['entities']['urls'][0]['url']) != '') {
            $tweets[$i]['text'] = str_replace($tweets[$i]['entities']['urls'][0]['url'], "<a href='" . $tweets[$i]['entities']['urls'][0]['expanded_url'] . "'target='_blank'>" . $tweets[$i]['entities']['urls'][0]['display_url'] . "</a> ", $tweets[$i]['text']);
            $tweets[$i]['text'] = $db->quote($tweets[$i]['text']);

        }

        $date = date("Y-m-d H:i:s", strtotime($tweets[$i]['created_at']));
        $sql_check = "SELECT * FROM sugarfeed WHERE description = '" . $tweets[$i]['text'] . "' AND date_entered = '" . $date . "'";
        $results = $db->query($sql_check);

        while ($row = $db->fetchByAssoc($results)) {
            $found_record = $row;

            break;
        }
        if (empty($found_record)) {

            $id = create_guid();

            $sql = "INSERT INTO sugarfeed (id, name, date_entered, date_modified, modified_user_id, created_by, description, deleted, assigned_user_id, related_module, related_id, link_url, link_type)
                    VALUES
                     ('" . $id . "',
                      '<b>" . $tweets[$i]['user']['name'] . " </b>',
                      '" . $date . "',
                      '" . $date . "',
                      '1',
                      '1',
                      '" . $tweets[$i]['text'] . "',
                      '0',
                      '" . $current_user->id . "',
                      'UserFeed',
                      '" . $current_user->id . "',
                      NULL,
                      NULL);";
            $results = $db->query($sql);

            $i++;
        } else {
            $i++;
        }
    }
}

require("custom/include/social/facebook/facebook.class.php");


$facebook_helper = new facebook_helper();
//get current user logged in
$user = $facebook_helper->facebook->getUser();

$user_home = check_facebook_login($facebook_helper);

if ($user) {
    $logoutUrl = $facebook_helper->get_logout_url();
} else {
    $loginUrl = $facebook_helper->get_login_url($_REQUEST['url']);
}

if ($user){
    $log = '<a href="' . $logoutUrl . '">Logout with Facebook</a>';
}else{
    $log = '<a href="' . $loginUrl .'">Login with Facebook</a>';
}

$html .=  '<div>';
$html .=  $log;
$html .= '</div>';

foreach($user_home['data'] as $single){
    data_insert($single, "facebook");
}























function check_facebook_login($facebook_helper){
    $user = $facebook_helper->facebook->getUser();

    if ($user) {

        $user_profile = $facebook_helper->get_my_user(); //get my user details

        $user_home = $facebook_helper->get_my_newsfeed(); //gets my newsfeed,
    }


    if ($user) {
        $logoutUrl = $facebook_helper->get_logout_url();
    } else {
        $loginUrl = $facebook_helper->get_login_url($url);
    }

    return $user_home;
}

function data_insert($single, $type){
    global $db;
    $id = guid_maker();
    $message = $db->quote(generate_stream($single));
    $assigned_user = '1';
    $date = date("Y-m-d H:i:s", strtotime($single['created_time']));


    $sql_check = "SELECT * FROM sugarfeed WHERE description = '" . $message . "' AND date_entered = '" . $date . "'";
    $results = $db->query($sql_check);

    while ($row = $db->fetchByAssoc($results)){
        $found_record = $row;
        break;
    }
    if(empty($found_record)){
        $sql = "INSERT INTO sugarfeed (id, name, date_entered, date_modified, modified_user_id, created_by, description, deleted, assigned_user_id, related_module, related_id, link_url, link_type)
                    VALUES
                     ('" . $id ."',
                      NULL,
                      '" . $date . "',
                      '" . $date . "',
                      '1',
                      '1',
                      '" . $message . "',
                      '0',
                      '" . $assigned_user . "',
                      '" . $type . "',
                      '" . $assigned_user ."',
                      NULL,
                      NULL);";

        if(!empty($message)){
            $results = $db->query($sql);
        }
    }
}
//print_r($user_home);
function guid_maker(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);
        $uuid = chr(123)
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12)
            .chr(125);
        return $uuid;
    }
}
function generate_stream($stream){
    //if simple post
    switch($stream['type']){
        case "":
            $string = $stream['from']['name'] . "" . substr($stream['message'], 0, 75);
            break;
        case "link";
            if(!empty($stream['name'])){
                $string = $stream['from']['name'] . " - <a href=" . $stream['link'] . ">" . substr($stream['name'], 0, 75) . "</a>";
            }else{
                //must be an article
                $string =  $stream['from']['name'] . " - <a href=" . $stream['actions']['0']['link'] . ">likes an article</a>";
            }
            break;
        case "status":
            //
            if(!empty($stream['story'])){
                $string = $stream['from']['name'] . " - <a href=" . $stream['actions']['0']['link'] . ">" . substr($stream['story'], 0, 75) . "</a>";
            }else{
                //wall post.
                $string = $stream['from']['name'] . " - <a href=" . $stream['actions']['0']['link'] . ">" . substr($stream['message'], 0, 75) . "</a>";
            }
            break;
        case "photos":
            break;
    }
    return $string;
}